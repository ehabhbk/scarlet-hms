<?php

namespace Tests\Unit;

use App\Domains\Integration\Dicom\DicomDataset;
use App\Domains\Integration\Services\DeviceMessageIngestService;
use App\Domains\Integration\Services\DicomStoreService;
use Tests\TestCase;

class DicomParsersTest extends TestCase
{
    public function test_it_reads_explicit_and_implicit_little_endian_elements(): void
    {
        $explicit = $this->explicitElement(0x0008, 0x0050, 'UI', 'RAD-000001')
            .$this->explicitElement(0x0010, 0x0020, 'LO', 'SC-000001')
            .$this->explicitSequenceWithReport('No acute findings');

        $elements = DicomDataset::parse($explicit, DicomDataset::EXPLICIT_VR_LITTLE_ENDIAN);

        $this->assertSame('RAD-000001', DicomDataset::value($elements, 0x0008, 0x0050));
        $this->assertSame('SC-000001', DicomDataset::value($elements, 0x0010, 0x0020));
        $this->assertContains('No acute findings', DicomDataset::textValues($elements));

        $implicit = pack('v', 0x0010).pack('v', 0x0020).pack('V', 9).'SC-000001';
        $implicitElements = DicomDataset::parse($implicit, DicomDataset::IMPLICIT_VR_LITTLE_ENDIAN);

        $this->assertSame('SC-000001', DicomDataset::value($implicitElements, 0x0010, 0x0020));
    }

    public function test_it_accepts_a_c_store_association(): void
    {
        $body = pack('v', 0x0001).str_repeat("\x00", 2);
        $body .= str_pad('SCARLET', 32, "\x00").str_pad('XR-01', 32, "\x00").str_repeat("\x00", 16);
        $body .= $this->item(0x10, '1.2.840.10008.3.1.1.1');

        $presentation = chr(1).chr(0).chr(0).chr(0x30);
        $presentation .= $this->subItem(0x30, '1.2.840.10008.5.1.4.1.1.1.1');
        $presentation .= $this->subItem(0x40, DicomDataset::IMPLICIT_VR_LITTLE_ENDIAN);
        $body .= $this->item(0x20, $presentation);
        $body .= $this->item(0x50, $this->subItem(0x51, pack('N', 16384)));

        $pdu = "\x01\x00".pack('N', strlen($body)).$body;

        $service = new DicomStoreService(new DeviceMessageIngestService());
        $accepted = $service->acceptAssociation($pdu);

        $this->assertSame("\x02", $accepted['response'][0]);
        $this->assertSame(1, $accepted['context_id']);
        $this->assertSame(DicomDataset::IMPLICIT_VR_LITTLE_ENDIAN, $accepted['transfer_syntax']);
        $this->assertSame('XR-01', $accepted['calling_ae']);
        $this->assertStringContainsString('1.2.840.10008.5.1.4.1.1.1.1', $accepted['response']);
    }

    private function explicitElement(int $group, int $element, string $vr, string $value): string
    {
        if (strlen($value) % 2 !== 0) {
            $value .= "\x00";
        }

        $length = in_array($vr, ['OB', 'OW', 'OF', 'SQ', 'UT', 'UN'], true)
            ? str_repeat("\x00", 2).pack('N', strlen($value))
            : pack('v', strlen($value));

        return pack('v', $group).pack('v', $element).$vr.$length.$value;
    }

    private function explicitSequenceWithReport(string $report): string
    {
        $value = $this->explicitElement(0x0040, 0xA160, 'ST', $report);
        $item = pack('v', 0xFFFE).pack('v', 0xE000).pack('V', 0xFFFFFFFF).$value;
        $item .= pack('v', 0xFFFE).pack('v', 0xE00D).pack('V', 0);

        return $this->explicitElement(0x0040, 0xA730, 'SQ', $item)
            .pack('v', 0xFFFE).pack('v', 0xE0DD).pack('V', 0);
    }

    private function item(int $type, string $value): string
    {
        return chr($type).chr(0).pack('v', strlen($value)).$value;
    }

    private function subItem(int $type, string $value): string
    {
        return chr($type).chr(0).pack('v', strlen($value)).$value;
    }
}
