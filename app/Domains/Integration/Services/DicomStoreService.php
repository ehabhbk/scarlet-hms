<?php

namespace App\Domains\Integration\Services;

use App\Domains\Integration\Dicom\DicomDataset;
use App\Domains\Radiology\Models\RadiologyDeviceMessage;
use App\Domains\Radiology\Models\RadiologyImage;
use App\Domains\Radiology\Models\RadiologyOrder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class DicomStoreService
{
    public const C_STORE_RQ = 0x0001;
    public const C_STORE_RSP = 0x8001;

    public function __construct(private readonly DeviceMessageIngestService $ingest) {}

    /**
     * Parse an A-ASSOCIATE-RQ body and build an A-ASSOCIATE-AC PDU.
     *
     * @return array{response: string, context_id: int, transfer_syntax: string, calling_ae: string, called_ae: string}
     */
    public function acceptAssociation(string $pdu): array
    {
        $body = substr($pdu, 6);
        $calledAe = trim(substr($body, 4, 32));
        $callingAe = trim(substr($body, 36, 32));
        $offset = 84;
        $applicationContext = '1.2.840.10008.3.1.1.1';
        $contexts = [];
        $maxPdu = (int) config('devices.dicom.max_pdu', 16384);

        while ($offset + 4 <= strlen($body)) {
            $type = ord($body[$offset]);
            $length = unpack('v', substr($body, $offset + 2, 2))[1];
            $value = substr($body, $offset + 4, $length);
            $offset += 4 + $length;

            if ($type === 0x10) {
                $applicationContext = $value;
            }

            if ($type === 0x20 && strlen($value) >= 8) {
                $contextId = ord($value[0]);
                $parsed = $this->parsePresentationContext(substr($value, 4));
                $contexts[] = [
                    'id' => $contextId,
                    'abstract' => $parsed['abstract'],
                    'transfers' => $parsed['transfers'],
                ];
            }

            if ($type === 0x50) {
                foreach ($this->parseSubItems($value) as $subItem) {
                    if ($subItem['type'] === 0x51 && strlen($subItem['value']) >= 4) {
                        $maxPdu = min($maxPdu, unpack('N', substr($subItem['value'], 0, 4))[1]);
                    }
                }
            }
        }

        $context = null;
        foreach ($contexts as $candidate) {
            if ($candidate['abstract'] === '' || $candidate['transfers'] === []) {
                continue;
            }

            if (! str_starts_with($candidate['abstract'], '1.2.840.10008.5.1.4.1.1')) {
                continue;
            }

            $context = $candidate;
            break;
        }

        if (! $context) {
            throw new RuntimeException('No supported C-STORE presentation context was proposed.');
        }

        $transferSyntax = $this->preferTransferSyntax($context['transfers']);

        return [
            'response' => $this->buildAssociationAccept(
                $calledAe,
                $callingAe,
                $applicationContext,
                $context['id'],
                $context['abstract'],
                $transferSyntax,
                max(8192, min($maxPdu, 65536)),
            ),
            'context_id' => $context['id'],
            'transfer_syntax' => $transferSyntax,
            'calling_ae' => $callingAe,
            'called_ae' => $calledAe,
        ];
    }

    public function buildReleaseResponse(): string
    {
        return "\x06\x00".pack('N', 4).str_repeat("\x00", 4);
    }

    /**
     * @param  array<int, array{group: int, element: int, vr: string, value: string}>  $command
     */
    public function buildStoreResponse(int $contextId, array $command, string $sopInstanceUid, int $status = 0x0000): string
    {
        $sopClass = DicomDataset::value($command, 0x0000, 0x0002) ?? '1.2.840.10008.5.1.4.1.1.7';
        $messageId = DicomDataset::unsignedShort($command, 0x0000, 0x0110) ?? 1;

        $response = '';
        $response .= $this->commandElement(0x0000, 0x0002, 'UI', $sopClass);
        $response .= $this->commandElement(0x0000, 0x0100, 'US', pack('v', self::C_STORE_RSP));
        $response .= $this->commandElement(0x0000, 0x0120, 'US', pack('v', $messageId));
        $response .= $this->commandElement(0x0000, 0x0800, 'US', pack('v', 0x0101));
        $response .= $this->commandElement(0x0000, 0x0900, 'US', pack('v', $status));
        $response .= $this->commandElement(0x0000, 0x1000, 'UI', $sopInstanceUid);

        $pdvLength = 1 + strlen($response);
        $payload = chr($contextId).substr(pack('N', $pdvLength), 1)."\x01".$response;

        return "\x04\x00".pack('N', strlen($payload)).$payload;
    }

    /**
     * @param  array<int, array{group: int, element: int, vr: string, value: string}>  $command
     */
    public function isStoreRequest(array $command): bool
    {
        return DicomDataset::unsignedShort($command, 0x0000, 0x0100) === self::C_STORE_RQ;
    }

    /**
     * @return array{message: mixed, stored_path: string, report: string}
     */
    public function ingest(
        string $datasetBytes,
        string $transferSyntax,
        string $callingAe,
        array $command = [],
    ): array {
        $elements = DicomDataset::parse($datasetBytes, $transferSyntax);
        $report = $this->extractReport($elements);
        $sopUid = DicomDataset::value($elements, 0x0008, 0x0018)
            ?? DicomDataset::value($command, 0x0000, 0x1000)
            ?? 'DICOM-'.Str::upper(Str::random(12));

        $path = $this->storeFile($datasetBytes, $sopUid);
        $hl7 = $this->buildHl7($elements, $callingAe, $report, $sopUid);

        $message = null;
        try {
            $message = $this->ingest->ingestRadiology($hl7, [
                'device_code' => $callingAe,
                'protocol' => 'HL7',
            ]);
        } finally {
            // Keep an index of every received DICOM object so the radiology
            // workspace can list the image even when HL7 matching fails.
            $this->recordImage($path, $elements, $sopUid, $message);
        }

        return [
            'message' => $message,
            'stored_path' => $path,
            'report' => $report,
        ];
    }

    /**
     * @param  array<int, array{group: int, element: int, vr: string, value: string}>  $elements
     */
    private function extractReport(array $elements): string
    {
        $parts = DicomDataset::textValues($elements);

        if ($parts === []) {
            $fallback = array_filter([
                DicomDataset::value($elements, 0x0008, 0x1030),
                DicomDataset::value($elements, 0x0008, 0x103E),
            ]);

            $parts = array_values($fallback);
        }

        return trim(implode("\n", array_values(array_unique(array_filter($parts)))));
    }

    /**
     * @param  array<int, array{group: int, element: int, vr: string, value: string}>  $elements
     */
    private function buildHl7(array $elements, string $callingAe, string $report, string $sopUid): string
    {
        $mrn = DicomDataset::value($elements, 0x0010, 0x0020) ?? '';
        $orderNumber = DicomDataset::value($elements, 0x0008, 0x0050) ?? '';
        $study = DicomDataset::value($elements, 0x0008, 0x1030)
            ?? DicomDataset::value($elements, 0x0008, 0x103E)
            ?? 'DICOM';
        $modality = DicomDataset::value($elements, 0x0008, 0x0060) ?? '';

        $clean = static fn (?string $value): string => str_replace(
            ['|', '^', '~', '\\', "\r", "\n"],
            [' ', ' ', ' ', ' ', ' ', ' '],
            (string) $value,
        );

        $timestamp = now()->format('YmdHis');
        $controlId = 'DICOM-'.Str::upper(substr(preg_replace('/[^A-Za-z0-9]/', '', $sopUid) ?: Str::random(10), 0, 20));
        $lines = [
            'MSH|^~\\&|'.$clean($callingAe).'|'.$clean($callingAe).'|SCARLET|HMS|'.$timestamp.'||ORU^R01|'.$controlId.'|P|2.5',
            'PID|1||'.$clean($mrn).'||DICOM^Patient',
            'OBR|1|'.$clean($orderNumber).'||'.$clean($study).'|||'.$timestamp,
        ];

        if ($modality !== '') {
            $lines[] = 'OBX|1|ST|MODALITY^Modality|1|'.$clean($modality).'|||N|||F';
            $lines[] = 'OBX|2|ST|REPORT^Report|1|'.$clean($report).'|||N|||F';
        } else {
            $lines[] = 'OBX|1|ST|REPORT^Report|1|'.$clean($report).'|||N|||F';
        }

        return implode("\r", $lines);
    }

    /**
     * @param  array<int, array{group: int, element: int, vr: string, value: string}>  $elements
     */
    private function recordImage(
        string $path,
        array $elements,
        string $sopUid,
        ?RadiologyDeviceMessage $message,
    ): void {
        $orderId = $message
            ? RadiologyOrder::query()->where('source_message_id', $message->id)->value('id')
            : null;

        $accession = DicomDataset::value($elements, 0x0008, 0x0050);
        if (! $orderId && filled($accession)) {
            $orderId = RadiologyOrder::query()->where('order_number', $accession)->value('id');
        }

        RadiologyImage::query()->updateOrCreate(
            ['sop_uid' => $sopUid],
            [
                'radiology_order_id' => $orderId,
                'radiology_device_id' => $message?->radiology_device_id,
                'radiology_device_message_id' => $message?->id,
                'disk' => (string) config('devices.dicom.storage_disk', 'local'),
                'path' => $path,
                'study_uid' => DicomDataset::value($elements, 0x0008, 0x0016),
                'modality' => DicomDataset::value($elements, 0x0008, 0x0060),
                'mime_type' => 'application/dicom',
                'received_at' => now(),
            ],
        );
    }

    private function storeFile(string $bytes, string $sopUid): string
    {
        $disk = (string) config('devices.dicom.storage_disk', 'local');
        $name = preg_replace('/[^A-Za-z0-9_-]/', '', $sopUid) ?: Str::random(16);
        $path = 'dicom/'.now()->format('Y/m/d').'/'.Str::limit($name, 80).'.dcm';

        Storage::disk($disk)->put($path, $bytes);

        return $path;
    }

    private function preferTransferSyntax(array $syntaxes): string
    {
        foreach ([DicomDataset::IMPLICIT_VR_LITTLE_ENDIAN, DicomDataset::EXPLICIT_VR_LITTLE_ENDIAN] as $preferred) {
            if (in_array($preferred, $syntaxes, true)) {
                return $preferred;
            }
        }

        return $syntaxes[0];
    }

    /**
     * @return array{abstract: string, transfers: array<int, string>}
     */
    private function parsePresentationContext(string $value): array
    {
        $abstract = '';
        $transfers = [];

        foreach ($this->parseSubItems($value) as $item) {
            if ($item['type'] === 0x30) {
                $abstract = $item['value'];
            }

            if ($item['type'] === 0x40) {
                $transfers[] = $item['value'];
            }
        }

        return ['abstract' => $abstract, 'transfers' => $transfers];
    }

    /** @return array<int, array{type: int, value: string}> */
    private function parseSubItems(string $value): array
    {
        $items = [];
        $offset = 0;

        while ($offset + 4 <= strlen($value)) {
            $type = ord($value[$offset]);
            $length = unpack('v', substr($value, $offset + 2, 2))[1];
            $items[] = [
                'type' => $type,
                'value' => substr($value, $offset + 4, $length),
            ];
            $offset += 4 + $length;
        }

        return $items;
    }

    private function buildAssociationAccept(
        string $calledAe,
        string $callingAe,
        string $applicationContext,
        int $contextId,
        string $abstractSyntax,
        string $transferSyntax,
        int $maxPdu,
    ): string {
        $body = pack('v', 0x0001).str_repeat("\x00", 2);
        $body .= str_pad(substr($calledAe, 0, 32), 32, "\x00");
        $body .= str_pad(substr($callingAe, 0, 32), 32, "\x00");
        $body .= str_repeat("\x00", 16);
        $body .= $this->item(0x10, $applicationContext);

        $presentation = chr($contextId).chr(0).chr(0).chr(0);
        $presentation .= $this->subItem(0x30, $abstractSyntax);
        $presentation .= $this->subItem(0x40, $transferSyntax);
        $body .= $this->item(0x20, $presentation);

        $userInfo = $this->subItem(0x51, pack('N', $maxPdu));
        $userInfo .= $this->subItem(0x52, '1.2.826.0.1.3680043.10.999');
        $userInfo .= $this->subItem(0x55, 'SCARLET_DICOM_1');
        $body .= $this->item(0x50, $userInfo);

        return "\x02\x00".pack('N', strlen($body)).$body;
    }

    private function item(int $type, string $value): string
    {
        return chr($type).chr(0).pack('v', strlen($value)).$value;
    }

    private function subItem(int $type, string $value): string
    {
        return chr($type).chr(0).pack('v', strlen($value)).$value;
    }

    private function commandElement(int $group, int $element, string $vr, string $value): string
    {
        if (strlen($value) % 2 !== 0) {
            $value .= "\x00";
        }

        $length = in_array($vr, ['OB', 'OW', 'OF', 'SQ', 'UT', 'UN'], true)
            ? str_repeat("\x00", 2).pack('N', strlen($value))
            : pack('v', strlen($value));

        return pack('v', $group).pack('v', $element).$vr.$length.$value;
    }
}
