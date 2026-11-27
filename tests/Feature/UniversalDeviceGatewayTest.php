<?php

namespace Tests\Feature;

use App\Domains\Integration\Models\ClinicalDevice;
use App\Domains\Integration\Models\ClinicalDeviceMessage;
use App\Domains\Integration\Models\DevicePollLog;
use App\Domains\Integration\Parsers\UniversalParser;
use App\Domains\Integration\Services\DeviceFrameExtractor;
use App\Domains\Integration\Services\DevicePollerService;
use App\Domains\Patients\Models\Patient;
use Tests\Feature\Api\ApiTestCase;

class UniversalDeviceGatewayTest extends ApiTestCase
{
    public function test_universal_parser_detects_and_normalises_common_formats(): void
    {
        $parser = new UniversalParser();

        $hl7 = $parser->parse(implode("\r", [
            'MSH|^~\&|CBC-01|SCARLET|LIS|HMS|20261126190000||ORU^R01|M-1|P|2.5',
            'PID|1||MRN-1||Test^Patient',
            'OBR|1|4||CBC^CBC|||20261126185500',
            'OBX|1|NM|WBC^WBC|1|7.4|10^9/L|4-11|N|||F',
        ]));
        $this->assertSame('HL7', $parser->detect($hl7['order_number'] ? 'MSH|^~\&|X' : 'MSH|^~\&|X'));
        $this->assertSame('4', $hl7['order_number']);
        $this->assertSame('WBC', $hl7['tests'][0]['parameters'][0]['code']);

        $json = $parser->parse(json_encode([
            'sample' => '4',
            'patient_mrn' => 'MRN-1',
            'tests' => [['code' => 'CBC', 'values' => ['HB' => ['value' => '13.7', 'unit' => 'g/dL']]]],
        ]));
        $this->assertSame('JSON', $json['protocol']);
        $this->assertSame('4', $json['order_number']);
        $this->assertSame('13.7', $json['tests'][0]['parameters'][0]['value']);

        $fhir = $parser->parse(json_encode([
            'resourceType' => 'Observation',
            'id' => 'obs-1',
            'subject' => ['reference' => 'Patient/1'],
            'code' => ['coding' => [['code' => '8867-4']], 'text' => 'Heart rate'],
            'valueQuantity' => ['value' => 78, 'unit' => '/min'],
        ]));
        $this->assertSame('FHIR', $fhir['protocol']);
        $this->assertSame('8867-4', $fhir['tests'][0]['code']);
        $this->assertSame('78', (string) $fhir['tests'][0]['parameters'][0]['value']);

        $bedside = $parser->parse("SAMPLE=4\nMRN=MRN-1\nTEST=MONITOR\nHR=78\nSPO2=98");
        $this->assertSame('KEY_VALUE', $bedside['protocol']);
        $this->assertSame('4', $bedside['order_number']);
        $this->assertSame('HR', $bedside['tests'][0]['parameters'][0]['code']);
    }

    public function test_frame_extractor_splits_mllp_astm_and_json_frames(): void
    {
        $extractor = new DeviceFrameExtractor();

        $buffer = "\x0BMSH|^~\\&|A|B|C|D||ORU^R01|1|P|2.5\r\x1C\r";
        $this->assertCount(1, $extractor->extract($buffer));
        $this->assertSame('', $buffer);

        $buffer = "\x02H|\^&||\rP|1||MRN-1\rO|1|4||CBC\rR|1|WBC|7.4\rL|1|N\x03";
        $this->assertCount(1, $extractor->extract($buffer));
        $this->assertSame('', $buffer);

        $buffer = '{"sample":"4","values":{"WBC":7.4}}{"sample":"5","values":{"WBC":8.1}}';
        $this->assertCount(2, $extractor->extract($buffer));
        $this->assertSame('', $buffer);
    }

    public function test_clinical_device_feed_is_stored_and_linked_to_patient(): void
    {
        config()->set('devices.ingest_token', 'gateway-test-token');
        $patient = Patient::factory()->create();

        $this->withHeader('X-Device-Token', 'gateway-test-token')
            ->postJson('/api/v1/device-ingest/clinical', [
                'device_code' => 'ICU-MONITOR-1',
                'category' => 'icu',
                'raw_message' => "SAMPLE=4\nMRN=".$patient->mrn."\nHR=78\nSPO2=98",
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'received');

        $message = ClinicalDeviceMessage::query()->firstOrFail();
        $this->assertSame($patient->id, $message->patient_id);
        $this->assertSame('4', $message->reference);
        $this->assertSame('HR', $message->canonical_json['tests'][0]['parameters'][0]['code']);
        $this->assertSame('icu', $message->device->category);
    }

    public function test_lab_device_can_be_configured_for_active_polling(): void
    {
        $this->actingAsRole('super_admin');

        $this->postJson('/api/v1/lab-devices', [
            'code' => 'POLL-CBC',
            'name' => 'محلل صورة الدم',
            'protocol' => 'AUTO',
            'host' => '192.168.0.6',
            'port' => 5100,
            'direction' => 'pull',
            'poll_enabled' => true,
            'poll_interval_seconds' => 30,
            'poll_query' => '\x05',
            'poll_query_encoding' => 'literal',
        ])->assertCreated();

        $this->assertDatabaseHas('lab_devices', [
            'code' => 'POLL-CBC',
            'direction' => 'pull',
            'poll_enabled' => 1,
            'poll_query' => '\x05',
        ]);
    }

    public function test_poller_records_a_failed_attempt_without_a_device_endpoint(): void
    {
        $device = ClinicalDevice::query()->create([
            'code' => 'WARD-1',
            'name' => 'مونيتور الجناح',
            'category' => 'ward',
            'direction' => 'pull',
            'poll_enabled' => true,
        ]);

        $result = app(DevicePollerService::class)->poll($device, 'clinical');

        $this->assertFalse($result['ok']);
        $this->assertSame(0, $result['frames']);
        $this->assertNotNull($device->fresh()->last_error);

        $log = DevicePollLog::query()->firstOrFail();
        $this->assertSame('error', $log->status);
        $this->assertSame('clinical', $log->device_type);
    }
}
