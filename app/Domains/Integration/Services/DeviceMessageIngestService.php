<?php

namespace App\Domains\Integration\Services;

use App\Domains\Integration\Parsers\AstmParser;
use App\Domains\Integration\Parsers\Hl7Parser;
use App\Domains\Laboratory\Models\LabDevice;
use App\Domains\Laboratory\Models\LabDeviceMessage;
use App\Domains\Laboratory\Models\LabOrder;
use App\Domains\Laboratory\Models\LabOrderItem;
use App\Domains\Laboratory\Models\LabResultValue;
use App\Domains\Laboratory\Models\LabTest;
use App\Domains\Patients\Models\Patient;
use App\Domains\Radiology\Models\RadiologyDevice;
use App\Domains\Radiology\Models\RadiologyDeviceMessage;
use App\Domains\Radiology\Models\RadiologyOrder;
use App\Domains\Radiology\Models\RadiologyStudy;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class DeviceMessageIngestService
{
    private readonly Hl7Parser $hl7;

    private readonly AstmParser $astm;

    public function __construct()
    {
        $this->hl7 = new Hl7Parser();
        $this->astm = new AstmParser();
    }

    /**
     * @param  array<string, mixed>  $context  device_id, device_code, protocol, remote_host
     */
    public function ingestLab(string $raw, array $context = []): LabDeviceMessage
    {
        $parsed = $this->parse($raw, $context['protocol'] ?? null);
        $device = $this->resolveLabDevice($parsed, $context);

        $message = LabDeviceMessage::create([
            'lab_device_id' => $device->id,
            'raw_message' => $raw,
            'status' => 'received',
            'parsed_summary' => $this->summarize($parsed),
            'received_at' => now(),
        ]);

        try {
            $imported = DB::transaction(fn (): int => $this->importLabResults($message, $device, $parsed));

            $message->update([
                'status' => $imported > 0 ? 'imported' : 'unmatched',
                'parsed_summary' => $this->summarize($parsed).' | imported='.$imported,
            ]);
        } catch (Throwable $exception) {
            Log::error('Lab device message import failed', [
                'message_id' => $message->id,
                'error' => $exception->getMessage(),
            ]);

            $message->update([
                'status' => 'error',
                'parsed_summary' => $this->summarize($parsed).' | error='.Str::limit($exception->getMessage(), 120),
            ]);
        }

        return $message->fresh(['device']);
    }

    /**
     * @param  array<string, mixed>  $context  device_id, device_code, protocol, remote_host
     */
    public function ingestRadiology(string $raw, array $context = []): RadiologyDeviceMessage
    {
        $parsed = $this->parse($raw, $context['protocol'] ?? null);
        $device = $this->resolveRadiologyDevice($parsed, $context);

        $message = RadiologyDeviceMessage::create([
            'radiology_device_id' => $device->id,
            'raw_message' => $raw,
            'status' => 'received',
            'message_id' => $parsed['message_id'],
            'parsed_summary' => $this->summarize($parsed),
            'received_at' => now(),
        ]);

        try {
            $imported = DB::transaction(fn (): int => $this->importRadiologyResult($message, $device, $parsed));

            $message->update([
                'status' => $imported > 0 ? 'imported' : 'unmatched',
                'parsed_summary' => $this->summarize($parsed).' | imported='.$imported,
            ]);
        } catch (Throwable $exception) {
            Log::error('Radiology device message import failed', [
                'message_id' => $message->id,
                'error' => $exception->getMessage(),
            ]);

            $message->update([
                'status' => 'error',
                'parsed_summary' => $this->summarize($parsed).' | error='.Str::limit($exception->getMessage(), 120),
            ]);
        }

        return $message->fresh(['device']);
    }

    public function reprocessLabMessage(LabDeviceMessage $message): LabDeviceMessage
    {
        $parsed = $this->parse($message->raw_message, $this->inferProtocol($message->raw_message));

        try {
            $imported = DB::transaction(fn (): int => $this->importLabResults($message, $message->device, $parsed));

            $message->update([
                'status' => $imported > 0 ? 'imported' : 'unmatched',
                'parsed_summary' => $this->summarize($parsed).' | reprocessed, imported='.$imported,
            ]);
        } catch (Throwable $exception) {
            Log::error('Lab device message reprocess failed', [
                'message_id' => $message->id,
                'error' => $exception->getMessage(),
            ]);

            $message->update([
                'status' => 'error',
                'parsed_summary' => $this->summarize($parsed).' | error='.Str::limit($exception->getMessage(), 120),
            ]);
        }

        return $message->fresh(['device']);
    }

    public function reprocessRadiologyMessage(RadiologyDeviceMessage $message): RadiologyDeviceMessage
    {
        $parsed = $this->parse($message->raw_message, $this->inferProtocol($message->raw_message));

        try {
            $imported = DB::transaction(fn (): int => $this->importRadiologyResult($message, $message->device, $parsed));

            $message->update([
                'status' => $imported > 0 ? 'imported' : 'unmatched',
                'parsed_summary' => $this->summarize($parsed).' | reprocessed, imported='.$imported,
            ]);
        } catch (Throwable $exception) {
            Log::error('Radiology device message reprocess failed', [
                'message_id' => $message->id,
                'error' => $exception->getMessage(),
            ]);

            $message->update([
                'status' => 'error',
                'parsed_summary' => $this->summarize($parsed).' | error='.Str::limit($exception->getMessage(), 120),
            ]);
        }

        return $message->fresh(['device']);
    }

    private function inferProtocol(string $raw): string
    {
        return str_contains($raw, 'MSH|') ? 'HL7' : 'ASTM';
    }

    /**
     * Extract only the values that belong to one order item, for technician review.
     * Nothing here writes to the database or approves a result.
     *
     * @return array<int, array{parameter_id: int, code: string, name_ar: string, value: string, unit: ?string, flag: ?string}>
     */
    public function valuesForItem(LabDeviceMessage $message, LabOrderItem $item): array
    {
        $parsed = $this->parse($message->raw_message, $this->inferProtocol($message->raw_message));
        $values = [];

        foreach ($parsed['tests'] ?? [] as $testData) {
            if (! $this->messageTestMatchesItem($item, $testData)) {
                continue;
            }

            foreach ($testData['parameters'] ?? [] as $parameterData) {
                $parameter = $this->findLabParameter($item, $parameterData);

                if (! $parameter || ! filled($parameterData['value'] ?? null)) {
                    continue;
                }

                $values[] = [
                    'parameter_id' => (int) $parameter->id,
                    'code' => (string) $parameter->code,
                    'name_ar' => (string) $parameter->name_ar,
                    'value' => (string) $parameterData['value'],
                    'unit' => $parameterData['unit'] ?? null,
                    'flag' => $this->normalizeFlag($parameterData['flag'] ?? null),
                ];
            }
        }

        if ($values === [] && $item->test->parameters()->doesntExist()) {
            // Single-value tests have no configured parameters; expose the raw result
            // with parameter_id 0 so the UI can fill the single result field.
            foreach ($parsed['tests'] ?? [] as $testData) {
                if (! $this->messageTestMatchesItem($item, $testData)) {
                    continue;
                }

                $first = collect($testData['parameters'] ?? [])
                    ->first(fn ($parameterData) => filled($parameterData['value'] ?? null));

                if (! $first) {
                    continue;
                }

                $values[] = [
                    'parameter_id' => 0,
                    'code' => 'RESULT',
                    'name_ar' => 'النتيجة',
                    'value' => (string) $first['value'],
                    'unit' => $first['unit'] ?? null,
                    'flag' => $this->normalizeFlag($first['flag'] ?? null),
                ];

                break;
            }
        }

        return $values;
    }

    /**
     * Find ready values for one order item by its sample number and the device
     * mapped to that test. Read-only: nothing is saved or approved here.
     *
     * @return array{lab_order_item_id: int, device: array<string, mixed>|null, message: array<string, mixed>|null, values: array<int, array<string, mixed>>}|null
     */
    public function readyValuesForItem(LabOrderItem $item): ?array
    {
        $test = $item->test ?: LabTest::query()->findOrFail($item->lab_test_id);
        $order = $item->order ?: LabOrder::query()->findOrFail($item->lab_order_id);

        $item->setRelation('test', $test);
        $item->setRelation('order', $order);

        $deviceIds = $test->devices()
            ->where('lab_devices.status', 'active')
            ->pluck('lab_devices.id')
            ->all();

        if ($deviceIds === []) {
            return null;
        }

        $orderNumber = (string) $order->order_number;
        $mrn = $order->patient?->mrn;

        $messages = LabDeviceMessage::query()
            ->with('device:id,code,name,protocol,host,port,status')
            ->whereIn('lab_device_id', $deviceIds)
            ->where(function ($query) use ($orderNumber): void {
                $query->where('parsed_summary', 'like', '%ORDER='.$orderNumber.'%')
                    ->orWhere('raw_message', 'like', '%'.$orderNumber.'%');
            })
            ->orderByDesc('received_at')
            ->limit(50)
            ->get();

        foreach ($messages as $message) {
            $parsed = $this->parse($message->raw_message, $this->inferProtocol($message->raw_message));
            $messageOrder = filled($parsed['order_number'] ?? null)
                ? (string) $parsed['order_number']
                : null;

            if ($messageOrder !== null && $messageOrder !== $orderNumber) {
                continue;
            }

            if ($messageOrder === null) {
                if (! filled($mrn) || (string) ($parsed['patient_mrn'] ?? '') !== (string) $mrn) {
                    continue;
                }
            }

            $values = $this->valuesForItem($message, $item);

            if ($values === []) {
                continue;
            }

            return [
                'lab_order_item_id' => (int) $item->id,
                'device' => $message->device?->only(['id', 'code', 'name', 'protocol', 'host', 'port', 'status']),
                'message' => [
                    'id' => (int) $message->id,
                    'status' => (string) $message->status,
                    'received_at' => $message->received_at,
                    'parsed_summary' => $message->parsed_summary,
                ],
                'values' => $values,
            ];
        }

        return null;
    }

    /**
     * Collect every ready result for one sample from each test's mapped device.
     *
     * @return array<int, array<string, mixed>>
     */
    public function readyValuesForOrder(LabOrder $order): array
    {
        $order->loadMissing(['items.test', 'patient']);

        $results = [];

        foreach ($order->items as $item) {
            if ($item->sample_collected_at === null || $item->status === LabOrderItem::STATUS_APPROVED) {
                continue;
            }

            $ready = $this->readyValuesForItem($item);

            if ($ready !== null) {
                $results[] = $ready;
            }
        }

        return $results;
    }

    /** @param  array<string, mixed>  $testData */
    private function messageTestMatchesItem(LabOrderItem $item, array $testData): bool
    {
        $code = filled($testData['code'] ?? null) ? (string) $testData['code'] : null;
        $name = filled($testData['name'] ?? null) ? (string) $testData['name'] : null;

        if (! $code && ! $name) {
            return false;
        }

        if ($code && strcasecmp($code, (string) $item->test->code) === 0) {
            return true;
        }

        return $name && (
            strcasecmp($name, (string) $item->test->name_ar) === 0 ||
            strcasecmp($name, (string) $item->test->name_en) === 0
        );
    }

    /** @return array<string, mixed> */
    private function parse(string $raw, ?string $protocol = null): array
    {
        $protocol = strtoupper(trim((string) $protocol));

        if ($protocol === '') {
            $protocol = str_contains($raw, 'MSH|') ? 'HL7' : 'ASTM';
        }

        if (str_starts_with($protocol, 'HL7') || str_starts_with($protocol, 'LIS')) {
            return $this->hl7->parse($raw);
        }

        return $this->astm->parse($raw);
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $context
     */
    private function resolveLabDevice(array $parsed, array $context): LabDevice
    {
        $query = LabDevice::query();

        $identifiers = array_values(array_filter([
            $parsed['sending_application'] ?? null,
            $parsed['sending_facility'] ?? null,
            $parsed['message_id'] ?? null,
        ]));

        if (! empty($context['device_id'])) {
            $query->where('id', $context['device_id']);
        } elseif (! empty($context['device_code'])) {
            $query->where('code', $context['device_code']);
        } elseif ($identifiers !== []) {
            $query->where(function ($q) use ($identifiers, $context): void {
                $q->whereIn('code', $identifiers)
                    ->orWhereIn('name', $identifiers);

                if (! empty($context['remote_host'])) {
                    $q->orWhere('host', $context['remote_host']);
                }
            });
        } elseif (! empty($context['remote_host'])) {
            $query->where('host', $context['remote_host']);
        }

        $device = (clone $query)->where('status', 'active')->first();

        if (! $device && ! empty($context['device_id'])) {
            throw ValidationException::withMessages(['device' => 'The selected lab device is not active.']);
        }

        if (! $device) {
            $protocol = strtoupper((string) ($parsed['message_type'] ?? ''));
            $single = LabDevice::query()->where('status', 'active')
                ->when($protocol !== '', fn ($q) => $q->where('protocol', $protocol))
                ->get();

            if ($single->count() === 1) {
                $device = $single->first();
            }
        }

        if (! $device) {
            throw ValidationException::withMessages([
                'device' => 'Device is not registered or its code/host does not match a configured lab device.',
            ]);
        }

        return $device;
    }

    /**
     * @param  array<string, mixed>  $parsed
     * @param  array<string, mixed>  $context
     */
    private function resolveRadiologyDevice(array $parsed, array $context): RadiologyDevice
    {
        $query = RadiologyDevice::query();

        $identifiers = array_values(array_filter([
            $parsed['sending_application'] ?? null,
            $parsed['sending_facility'] ?? null,
            $parsed['message_id'] ?? null,
        ]));

        if (! empty($context['device_id'])) {
            $query->where('id', $context['device_id']);
        } elseif (! empty($context['device_code'])) {
            $query->where(function ($q) use ($context): void {
                $q->where('code', $context['device_code'])
                    ->orWhere('ae_title', $context['device_code']);
            });
        } elseif ($identifiers !== []) {
            $query->where(function ($q) use ($identifiers, $context): void {
                $q->whereIn('code', $identifiers)
                    ->orWhereIn('name', $identifiers)
                    ->orWhereIn('ae_title', $identifiers);

                if (! empty($context['remote_host'])) {
                    $q->orWhere('host', $context['remote_host']);
                }
            });
        } elseif (! empty($context['remote_host'])) {
            $query->where('host', $context['remote_host']);
        }

        $device = (clone $query)->where('status', 'active')->first();

        if (! $device && ! empty($context['device_id'])) {
            throw ValidationException::withMessages(['device' => 'The selected radiology device is not active.']);
        }

        if (! $device) {
            $single = RadiologyDevice::query()->where('status', 'active')->get();

            if ($single->count() === 1) {
                $device = $single->first();
            }
        }

        if (! $device) {
            throw ValidationException::withMessages([
                'device' => 'Device is not registered or its code/host does not match a configured radiology device.',
            ]);
        }

        return $device;
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function importLabResults(LabDeviceMessage $message, LabDevice $device, array $parsed): int
    {
        $imported = 0;

        foreach ($parsed['tests'] ?? [] as $testData) {
            $item = $this->findLabOrderItem(
                $testData['order_number'] ?? $parsed['order_number'] ?? null,
                $parsed['patient_mrn'] ?? null,
                $testData['code'] ?? null,
                $testData['name'] ?? null,
            );

            if (! $item || $item->status === LabOrderItem::STATUS_APPROVED || $item->sample_collected_at === null) {
                continue;
            }

            $values = [];
            foreach ($testData['parameters'] ?? [] as $parameterData) {
                $parameter = $this->findLabParameter($item, $parameterData);

                if ($parameter && filled($parameterData['value'] ?? null)) {
                    $values[] = [
                        'parameter' => $parameter,
                        'value' => (string) $parameterData['value'],
                        'flag' => $this->normalizeFlag($parameterData['flag'] ?? null),
                        'unit' => $parameterData['unit'] ?? null,
                    ];
                }
            }

            if ($values === []) {
                // Single-value tests have no configured parameters; use the first OBX/ASTM result.
                $first = $testData['parameters'][0] ?? null;

                if (! $first || ! filled($first['value'] ?? null) || $item->test->parameters()->exists()) {
                    continue;
                }

                $item->update([
                    'status' => LabOrderItem::STATUS_ENTERED,
                    'result_value' => (string) $first['value'],
                    'result_unit' => $first['unit'] ?? $item->result_unit,
                    'flag' => $this->normalizeFlag($first['flag'] ?? null),
                    'notes' => 'نتيجة مستوردة من الجهاز؛ بانتظار الاعتماد.',
                    'entered_at' => now(),
                    'source' => 'device',
                    'source_device_id' => $device->id,
                    'source_message_id' => $message->id,
                    'imported_at' => now(),
                ]);

                $imported++;

                continue;
            }

            foreach ($values as $value) {
                $item->values()->updateOrCreate(
                    ['lab_test_parameter_id' => $value['parameter']->id],
                    ['value' => $value['value'], 'flag' => $value['flag']],
                );
            }

            $summary = collect($values)
                ->map(fn (array $value): string => $value['parameter']->name_ar.': '.$value['value'].($value['flag'] ? ' ['.$value['flag'].']' : ''))
                ->join('؛ ');

            $item->update([
                'status' => LabOrderItem::STATUS_ENTERED,
                'result_value' => $summary,
                'result_unit' => collect($values)->first()['unit'] ?? $item->result_unit,
                'flag' => collect($values)->first()['flag'] ?? null,
                'notes' => 'نتيجة مستوردة من الجهاز؛ بانتظار الاعتماد.',
                'entered_at' => now(),
                'source' => 'device',
                'source_device_id' => $device->id,
                'source_message_id' => $message->id,
                'imported_at' => now(),
            ]);

            $imported++;
        }

        if ($imported > 0) {
            $device->update(['last_sync_at' => now()]);
        }

        return $imported;
    }

    private function findLabOrderItem(
        ?string $orderNumber,
        ?string $mrn,
        ?string $testCode,
        ?string $testName,
    ): ?LabOrderItem {
        $testQuery = LabTest::query();

        if (filled($testCode)) {
            $testQuery->where('code', $testCode);
        } elseif (filled($testName)) {
            $testQuery->where('name_ar', $testName)->orWhere('name_en', $testName);
        } else {
            return null;
        }

        $test = $testQuery->first();
        if (! $test) {
            return null;
        }

        if (filled($orderNumber)) {
            $order = LabOrder::query()->where('order_number', $orderNumber)->first();

            if ($order) {
                $item = $order->items()
                    ->where('lab_test_id', $test->id)
                    ->whereNotNull('sample_collected_at')
                    ->where('status', '!=', LabOrderItem::STATUS_APPROVED)
                    ->latest('id')
                    ->first();

                if ($item) {
                    return $item;
                }
            }
        }

        if (filled($mrn)) {
            $patient = Patient::query()->where('mrn', $mrn)->first();

            if ($patient) {
                return LabOrderItem::query()
                    ->whereHas('order', fn ($q) => $q->where('patient_id', $patient->id))
                    ->where('lab_test_id', $test->id)
                    ->whereNotNull('sample_collected_at')
                    ->where('status', '!=', LabOrderItem::STATUS_APPROVED)
                    ->latest('id')
                    ->first();
            }
        }

        return null;
    }

    /** @param  array<string, mixed>  $parameterData */
    private function findLabParameter(LabOrderItem $item, array $parameterData): mixed
    {
        $query = $item->test->parameters();

        if (filled($parameterData['code'] ?? null)) {
            $found = $query->where('code', $parameterData['code'])->first();

            if ($found) {
                return $found;
            }
        }

        $name = $parameterData['name'] ?? null;
        if (! filled($name)) {
            return null;
        }

        return $item->test->parameters()
            ->where('name_ar', $name)
            ->orWhere('name_en', $name)
            ->first();
    }

    private function normalizeFlag(mixed $flag): ?string
    {
        if (! filled($flag)) {
            return null;
        }

        $flag = strtoupper(trim((string) $flag));

        return in_array($flag, ['H', 'L', 'N', 'HH', 'LL', 'A', 'NA'], true) ? $flag : null;
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private function importRadiologyResult(
        RadiologyDeviceMessage $message,
        RadiologyDevice $device,
        array $parsed,
    ): int {
        $reportParts = [];
        foreach ($parsed['tests'] ?? [] as $testData) {
            foreach ($testData['parameters'] ?? [] as $parameterData) {
                if (filled($parameterData['value'] ?? null)) {
                    $reportParts[] = trim((string) $parameterData['value']);
                }
            }
        }

        $report = implode("\n", array_values(array_unique(array_filter($reportParts))));

        if ($report === '') {
            return 0;
        }

        $firstTest = $parsed['tests'][0] ?? [];
        $orderNumber = $firstTest['order_number'] ?? $parsed['order_number'] ?? null;
        $studyCode = $firstTest['code'] ?? null;
        $study = filled($studyCode)
            ? RadiologyStudy::query()->where('code', $studyCode)->first()
            : null;

        if (! $study && filled($firstTest['name'] ?? null)) {
            $study = RadiologyStudy::query()
                ->where('name_ar', $firstTest['name'])
                ->orWhere('name_en', $firstTest['name'])
                ->first();
        }

        $query = RadiologyOrder::query()
            ->whereIn('status', [RadiologyOrder::STATUS_ORDERED, RadiologyOrder::STATUS_RESULT_RECEIVED]);

        if (filled($orderNumber)) {
            $query->where('order_number', $orderNumber);
        } elseif (filled($parsed['patient_mrn'] ?? null)) {
            $patient = Patient::query()->where('mrn', $parsed['patient_mrn'])->first();
            if (! $patient) {
                return 0;
            }
            $query->where('patient_id', $patient->id);
        } else {
            return 0;
        }

        if ($study) {
            $query->where(function ($q) use ($study): void {
                $q->where('radiology_study_id', $study->id)
                    ->orWhere('study_description', $study->name_ar)
                    ->orWhere('study_description', $study->name_en)
                    ->orWhere('study_description', $study->code)
                    ->orWhere('modality', $study->modality);
            });
        }

        $order = $query->latest('id')->first();
        if (! $order) {
            return 0;
        }

        $order->update([
            'report' => $report,
            'status' => RadiologyOrder::STATUS_RESULT_RECEIVED,
            'result_received_at' => now(),
            'source' => 'device',
            'source_device_id' => $device->id,
            'source_message_id' => $message->id,
            'radiology_study_id' => $study?->id,
            'modality' => $study?->modality ?: $order->modality,
        ]);

        $device->update(['last_sync_at' => now()]);

        return 1;
    }

    /** @param  array<string, mixed>  $parsed */
    private function summarize(array $parsed): string
    {
        $parts = array_filter([
            $parsed['message_type'] ?? null,
            $parsed['message_id'] ?? null,
            filled($parsed['patient_mrn'] ?? null) ? 'MRN='.$parsed['patient_mrn'] : null,
            filled($parsed['order_number'] ?? null) ? 'ORDER='.$parsed['order_number'] : null,
        ]);

        foreach ($parsed['tests'] ?? [] as $test) {
            $parts[] = ($test['code'] ?? 'TEST').'('.count($test['parameters'] ?? []).')';
        }

        return Str::limit(implode(' | ', $parts), 500);
    }
}
