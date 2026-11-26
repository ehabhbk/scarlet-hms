<?php

use App\Domains\Integration\Parsers\AstmParser;
use App\Domains\Integration\Parsers\Hl7Parser;
use PHPUnit\Framework\TestCase;

class DeviceMessageParsersTest extends TestCase
{
    public function test_parses_an_hl7_oru_result_message(): void
    {
        $message = implode("\r", [
            'MSH|^~\\&|LIS|SCARLET|ANALYZER|LAB|20261126120000||ORU^R01|MSG-1001|P|2.5',
            'PID|1||SC-000001||Ahmad^Mohammed',
            'OBR|1|LAB-000001||CBC^Complete Blood Count|||20261126115500',
            'OBX|1|NM|HB^Hemoglobin|1|14.1|g/dL|12.0 - 16.0|N|||F',
            'OBX|2|NM|WBC^White Blood Cells|1|7.2|10^9/L|4.0 - 11.0|N|||F',
        ]);

        $parsed = (new Hl7Parser())->parse("\x0B".$message."\x1C\r");

        $this->assertSame('MSG-1001', $parsed['message_id']);
        $this->assertSame('SC-000001', $parsed['patient_mrn']);
        $this->assertSame('LAB-000001', $parsed['order_number']);
        $this->assertCount(1, $parsed['tests']);
        $this->assertSame('CBC', $parsed['tests'][0]['code']);
        $this->assertCount(2, $parsed['tests'][0]['parameters']);
        $this->assertSame('HB', $parsed['tests'][0]['parameters'][0]['code']);
        $this->assertSame('14.1', $parsed['tests'][0]['parameters'][0]['value']);
    }

    public function test_parses_astm_result_records(): void
    {
        $message = implode("\r", [
            'H|\\^&|||HOST^SCARLET|||||P|1|MSG-2001',
            'P|1|||SC-000002||Fatima^Ali',
            'O|1|LAB-000002||CBC',
            'R|1|HB^Hemoglobin|13.5|g/dL|12.0 - 16.0|N',
            'L|1|N',
        ]);

        $parsed = (new AstmParser())->parse($message);

        $this->assertSame('MSG-2001', $parsed['message_id']);
        $this->assertSame('SC-000002', $parsed['patient_mrn']);
        $this->assertSame('CBC', $parsed['tests'][0]['code']);
        $this->assertSame('13.5', $parsed['tests'][0]['parameters'][0]['value']);
    }
}
