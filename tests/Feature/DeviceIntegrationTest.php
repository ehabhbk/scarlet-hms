<?php

namespace Tests\Feature;

use App\Domains\Integration\Dicom\DicomDataset;
use App\Domains\Integration\Services\DeviceConnectionTester;
use App\Domains\Integration\Services\DeviceMessageIngestService;
use App\Domains\Integration\Services\DicomStoreService;
use App\Domains\Insurance\Models\InsuranceProvider;
use App\Domains\Insurance\Models\PatientInsurance;
use App\Domains\Laboratory\Models\LabDevice;
use App\Domains\Laboratory\Models\LabDeviceMessage;
use App\Domains\Laboratory\Models\LabOrder;
use App\Domains\Laboratory\Models\LabOrderItem;
use App\Domains\Laboratory\Models\LabTest;
use App\Domains\Laboratory\Models\LabTestParameter;
use App\Domains\Patients\Models\Patient;
use App\Domains\Radiology\Models\RadiologyDevice;
use App\Domains\Radiology\Models\RadiologyOrder;
use App\Domains\Radiology\Models\RadiologyStudy;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Api\ApiTestCase;

class DeviceIntegrationTest extends ApiTestCase
{
    public function test_it_imports_an_hl7_lab_result_without_approving_it(): void
    {
        $test = LabTest::query()->create([
            'code' => 'CBC-DEVICE',
            'name_ar' => 'صورة دم كاملة',
            'name_en' => 'Complete Blood Count',
            'price' => 100,
            'is_active' => true,
        ]);
        $parameter = LabTestParameter::query()->create([
            'lab_test_id' => $test->id,
            'code' => 'HB',
            'name_ar' => 'الهيموجلوبين',
            'name_en' => 'Hemoglobin',
            'unit' => 'g/dL',
            'reference_range' => '12.0 - 16.0',
            'sort_order' => 1,
        ]);
        $patient = Patient::factory()->create();
        $order = LabOrder::query()->create([
            'order_number' => 'LAB-900001',
            'patient_id' => $patient->id,
            'branch_id' => $this->branch->id,
            'status' => LabOrder::STATUS_ORDERED,
            'ordered_at' => now(),
        ]);
        $item = $order->items()->create([
            'lab_test_id' => $test->id,
            'status' => LabOrderItem::STATUS_PENDING,
            'sample_collected_at' => now(),
        ]);

        LabDevice::query()->create([
            'code' => 'CBC-01',
            'name' => 'محلل صورة الدم',
            'protocol' => 'HL7',
            'status' => 'active',
        ]);

        $raw = implode("\r", [
            'MSH|^~\\&|ANALYZER|SCARLET|LIS|HMS|20261126120000||ORU^R01|MSG-LAB-1|P|2.5',
            'PID|1||'.$patient->mrn.'||Test^Patient',
            'OBR|1|LAB-900001||CBC-DEVICE^Complete Blood Count|||20261126115500',
            'OBX|1|NM|HB^Hemoglobin|1|14.1|g/dL|12.0 - 16.0|N|||F',
        ]);

        $message = app(DeviceMessageIngestService::class)->ingestLab($raw, ['device_code' => 'CBC-01']);

        $this->assertSame('imported', $message->status);
        $item->refresh();
        $this->assertSame(LabOrderItem::STATUS_ENTERED, $item->status);
        $this->assertSame('device', $item->source);
        $this->assertNull($item->entered_by);
        $this->assertNull($item->approved_by);
        $this->assertNotNull($item->sample_collected_at);
        $this->assertDatabaseHas('lab_result_values', [
            'lab_order_item_id' => $item->id,
            'lab_test_parameter_id' => $parameter->id,
            'value' => '14.1',
            'flag' => 'N',
        ]);
    }

    public function test_it_can_reprocess_an_unmatched_message_after_sample_collection(): void
    {
        $test = LabTest::query()->create([
            'code' => 'GLU-REPROCESS',
            'name_ar' => 'سكر الدم',
            'name_en' => 'Glucose',
            'price' => 50,
            'is_active' => true,
        ]);
        $patient = Patient::factory()->create();
        $order = LabOrder::query()->create([
            'order_number' => 'LAB-900002',
            'patient_id' => $patient->id,
            'branch_id' => $this->branch->id,
            'status' => LabOrder::STATUS_ORDERED,
            'ordered_at' => now(),
        ]);
        $item = $order->items()->create([
            'lab_test_id' => $test->id,
            'status' => LabOrderItem::STATUS_PENDING,
        ]);

        LabDevice::query()->create([
            'code' => 'CHEM-01',
            'name' => 'محلل الكيمياء',
            'protocol' => 'HL7',
            'status' => 'active',
        ]);

        $raw = implode("\r", [
            'MSH|^~\&|ANALYZER|CHEM-01|SCARLET|HMS|20261126124500||ORU^R01|MSG-REPROCESS|P|2.5',
            'PID|1||'.$patient->mrn.'||Test^Patient',
            'OBR|1|LAB-900002||GLU-REPROCESS^Glucose|||20261126124000',
            'OBX|1|NM|GLU^Glucose|1|95|mg/dL||N|||F',
        ]);

        $message = app(DeviceMessageIngestService::class)->ingestLab($raw, ['device_code' => 'CHEM-01']);
        $this->assertSame('unmatched', $message->status);

        $item->update(['sample_collected_at' => now()]);
        $this->actingAsRole('super_admin');
        $response = $this->postJson("/api/v1/lab-device-messages/{$message->id}/reprocess");

        $response->assertOk();
        $this->assertSame('imported', $message->fresh()->status);
        $this->assertSame(LabOrderItem::STATUS_ENTERED, $item->fresh()->status);
        $this->assertSame('device', $item->fresh()->source);
    }

    public function test_it_imports_a_radiology_result_and_requires_explicit_confirmation(): void
    {
        $study = RadiologyStudy::query()->create([
            'code' => 'XR-CHEST',
            'name_ar' => 'أشعة سينية للصدر',
            'name_en' => 'Chest X-Ray',
            'modality' => 'X-Ray',
            'price' => 100,
            'is_active' => true,
        ]);
        $patient = Patient::factory()->create();
        $order = RadiologyOrder::query()->create([
            'order_number' => 'RAD-900001',
            'patient_id' => $patient->id,
            'branch_id' => $this->branch->id,
            'modality' => 'X-Ray',
            'study_description' => $study->name_ar,
            'radiology_study_id' => $study->id,
            'status' => RadiologyOrder::STATUS_ORDERED,
            'ordered_at' => now(),
        ]);

        RadiologyDevice::query()->create([
            'code' => 'XR-01',
            'name' => 'جهاز الأشعة السينية',
            'protocol' => 'HL7',
            'modality' => 'X-Ray',
            'status' => 'active',
        ]);

        $raw = implode("\r", [
            'MSH|^~\\&|RIS|XR-01|PACS|HMS|20261126120500||ORU^R01|MSG-RAD-1|P|2.5',
            'PID|1||'.$patient->mrn.'||Test^Patient',
            'OBR|1|RAD-900001||XR-CHEST^Chest X-Ray|||20261126115000',
            'OBX|1|ST|REPORT^Report|1|لا يوجد ارتشاح رئوي حاد|||N|||F',
        ]);

        $message = app(DeviceMessageIngestService::class)->ingestRadiology($raw, ['device_code' => 'XR-01']);

        $this->assertSame('imported', $message->status);
        $order->refresh();
        $this->assertSame(RadiologyOrder::STATUS_RESULT_RECEIVED, $order->status);
        $this->assertStringContainsString('ارتشاح', (string) $order->report);
        $this->assertNull($order->reported_by);
        $this->assertNotNull($order->result_received_at);

        $user = $this->actingAsRole('super_admin');
        $response = $this->postJson("/api/v1/radiology-orders/{$order->id}/confirm-result", [
            'report' => $order->report,
        ]);

        $response->assertOk();
        $order->refresh();
        $this->assertSame(RadiologyOrder::STATUS_COMPLETED, $order->status);
        $this->assertSame($user->id, $order->reported_by);
    }

    public function test_it_imports_a_dicom_c_store_report(): void
    {
        $study = RadiologyStudy::query()->create([
            'code' => 'XR-CHEST',
            'name_ar' => 'أشعة سينية للصدر',
            'name_en' => 'Chest X-Ray',
            'modality' => 'X-Ray',
            'price' => 100,
            'is_active' => true,
        ]);
        $patient = Patient::factory()->create();
        $order = RadiologyOrder::query()->create([
            'order_number' => 'RAD-900003',
            'patient_id' => $patient->id,
            'branch_id' => $this->branch->id,
            'modality' => 'X-Ray',
            'study_description' => $study->name_ar,
            'radiology_study_id' => $study->id,
            'status' => RadiologyOrder::STATUS_ORDERED,
            'ordered_at' => now(),
        ]);

        RadiologyDevice::query()->create([
            'code' => 'XR-DICOM',
            'name' => 'جهاز الأشعة الرقمي',
            'protocol' => 'DICOM',
            'ae_title' => 'XR-DICOM',
            'modality' => 'X-Ray',
            'status' => 'active',
        ]);

        $element = static function (int $group, int $element, string $value): string {
            if (strlen($value) % 2 !== 0) {
                $value .= "\x00";
            }

            return pack('v', $group).pack('v', $element).pack('V', strlen($value)).$value;
        };

        $dataset = $element(0x0008, 0x0018, '1.2.826.0.1.3680043.10.9001')
            .$element(0x0008, 0x0050, 'RAD-900003')
            .$element(0x0008, 0x0060, 'XR')
            .$element(0x0008, 0x1030, 'Chest X-Ray')
            .$element(0x0010, 0x0020, $patient->mrn)
            .$element(0x0040, 0xA160, 'No acute cardiopulmonary abnormality');

        $result = app(DicomStoreService::class)->ingest(
            $dataset,
            DicomDataset::IMPLICIT_VR_LITTLE_ENDIAN,
            'XR-DICOM',
        );

        $this->assertSame('imported', $result['message']->status);
        $this->assertSame('XR-DICOM', $result['message']->device->code);
        $this->assertSame(RadiologyOrder::STATUS_RESULT_RECEIVED, $order->fresh()->status);
        $this->assertStringContainsString('No acute', (string) $order->fresh()->report);
        Storage::disk('local')->assertExists($result['stored_path']);
    }

    public function test_worklist_exposes_collected_orders_with_patient_and_insurance_markers(): void
    {
        $test = LabTest::query()->create([
            'code' => 'CBC-WORKLIST',
            'name_ar' => 'صورة دم كاملة',
            'price' => 100,
            'is_active' => true,
        ]);
        LabTestParameter::query()->create([
            'lab_test_id' => $test->id,
            'code' => 'HB',
            'name_ar' => 'الهيموجلوبين',
            'sort_order' => 1,
        ]);
        $patient = Patient::factory()->create();
        $order = LabOrder::query()->create([
            'order_number' => 'LAB-900010',
            'patient_id' => $patient->id,
            'branch_id' => $this->branch->id,
            'status' => LabOrder::STATUS_ORDERED,
            'ordered_at' => now(),
        ]);
        $order->items()->create([
            'lab_test_id' => $test->id,
            'status' => LabOrderItem::STATUS_PENDING,
            'sample_collected_at' => now(),
        ]);

        $provider = InsuranceProvider::query()->create([
            'code' => 'INS-TEST',
            'name' => 'تأمين تجريبي',
            'is_active' => true,
        ]);
        PatientInsurance::query()->create([
            'patient_id' => $patient->id,
            'insurance_provider_id' => $provider->id,
            'policy_number' => 'POL-100',
            'status' => 'active',
        ]);

        $this->actingAsRole('laboratory_technician');
        $response = $this->getJson('/api/v1/lab-worklist?limit=6');

        $response->assertOk()
            ->assertJsonPath('data.0.order_number', 'LAB-900010')
            ->assertJsonPath('data.0.patient.mrn', $patient->mrn)
            ->assertJsonPath('data.0.tests_count', 1)
            ->assertJsonPath('data.0.insurance.provider.name', 'تأمين تجريبي')
            ->assertJsonPath('data.0.items.0.test.parameters.0.code', 'HB');
    }

    public function test_device_values_are_extracted_for_review_without_writing_results(): void
    {
        $test = LabTest::query()->create([
            'code' => 'CBC-VALUES',
            'name_ar' => 'صورة دم كاملة',
            'price' => 100,
            'is_active' => true,
        ]);
        $parameter = LabTestParameter::query()->create([
            'lab_test_id' => $test->id,
            'code' => 'HB',
            'name_ar' => 'الهيموجلوبين',
            'sort_order' => 1,
        ]);
        $patient = Patient::factory()->create();
        $order = LabOrder::query()->create([
            'order_number' => 'LAB-900011',
            'patient_id' => $patient->id,
            'branch_id' => $this->branch->id,
            'status' => LabOrder::STATUS_ORDERED,
            'ordered_at' => now(),
        ]);
        $item = $order->items()->create([
            'lab_test_id' => $test->id,
            'status' => LabOrderItem::STATUS_PENDING,
            'sample_collected_at' => now(),
        ]);
        $device = LabDevice::query()->create([
            'code' => 'CBC-VALUES-01',
            'name' => 'محلل صورة الدم',
            'protocol' => 'HL7',
            'status' => 'active',
        ]);
        $message = LabDeviceMessage::query()->create([
            'lab_device_id' => $device->id,
            'raw_message' => implode("\r", [
                'MSH|^~\\&|ANALYZER|CBC-VALUES-01|LIS|HMS|20261126140000||ORU^R01|MSG-VALUES|P|2.5',
                'PID|1||'.$patient->mrn.'||Test^Patient',
                'OBR|1|LAB-900011||CBC-VALUES^Complete Blood Count',
                'OBX|1|NM|HB^Hemoglobin|1|14.1|g/dL|12.0 - 16.0|N|||F',
            ]),
            'status' => 'unmatched',
            'received_at' => now(),
        ]);

        $this->actingAsRole('laboratory_technician');
        $response = $this->getJson("/api/v1/lab-device-messages/{$message->id}/values?lab_order_item_id={$item->id}");

        $response->assertOk()
            ->assertJsonPath('data.0.parameter_id', $parameter->id)
            ->assertJsonPath('data.0.code', 'HB')
            ->assertJsonPath('data.0.value', '14.1');

        $this->assertSame(LabOrderItem::STATUS_PENDING, $item->fresh()->status);
        $this->assertDatabaseCount('lab_result_values', 0);
    }

    public function test_connection_failure_explains_which_device_endpoint_was_unreachable(): void
    {
        $result = app(DeviceConnectionTester::class)->test('127.0.0.1', 1);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('127.0.0.1:1', $result['message']);
        $this->assertStringContainsString('تعذر الاتصال بالجهاز', $result['message']);
    }

    public function test_ready_values_come_from_the_device_mapped_to_the_test_by_sample_number(): void
    {
        $test = LabTest::query()->create([
            'code' => 'CBC-AUTO',
            'name_ar' => 'صورة دم كاملة',
            'name_en' => 'Complete Blood Count',
            'price' => 100,
            'is_active' => true,
        ]);
        LabTestParameter::query()->create([
            'lab_test_id' => $test->id,
            'code' => 'HB',
            'name_ar' => 'الهيموجلوبين',
            'name_en' => 'Hemoglobin',
            'unit' => 'g/dL',
            'sort_order' => 1,
        ]);

        $device = LabDevice::query()->create([
            'code' => 'CBC-AUTO-DEVICE',
            'name' => 'محلل صورة الدم المخصص',
            'protocol' => 'HL7',
            'status' => 'active',
        ]);
        $device->tests()->attach($test->id);

        $unmappedDevice = LabDevice::query()->create([
            'code' => 'OTHER-AUTO-DEVICE',
            'name' => 'جهاز غير مرتبط بالفحص',
            'protocol' => 'HL7',
            'status' => 'active',
        ]);

        $patient = Patient::factory()->create();
        $order = LabOrder::query()->create([
            'order_number' => '200',
            'patient_id' => $patient->id,
            'branch_id' => $this->branch->id,
            'status' => LabOrder::STATUS_ORDERED,
            'ordered_at' => now(),
        ]);
        $item = $order->items()->create([
            'lab_test_id' => $test->id,
            'status' => LabOrderItem::STATUS_PENDING,
            'sample_collected_at' => now(),
        ]);

        $raw = implode("\r", [
            'MSH|^~\\&|CBC-AUTO-DEVICE|SCARLET|LIS|HMS|20261126130000||ORU^R01|MSG-AUTO-200|P|2.5',
            'PID|1||'.$patient->mrn.'||Test^Patient',
            'OBR|1|200||CBC-AUTO^Complete Blood Count|||20261126125500',
            'OBX|1|NM|HB^Hemoglobin|1|14.2|g/dL|12.0 - 16.0|N|||F',
        ]);

        LabDeviceMessage::query()->create([
            'lab_device_id' => $unmappedDevice->id,
            'raw_message' => $raw,
            'status' => 'received',
            'parsed_summary' => 'ORDER=200 | CBC-AUTO(1)',
            'received_at' => now()->subMinute(),
        ]);

        LabDeviceMessage::query()->create([
            'lab_device_id' => $device->id,
            'raw_message' => $raw,
            'status' => 'received',
            'parsed_summary' => 'ORDER=200 | CBC-AUTO(1)',
            'received_at' => now(),
        ]);

        $this->actingAsRole('super_admin');

        $this->getJson('/api/v1/lab-order-items/'.$item->id.'/device-values')
            ->assertOk()
            ->assertJsonPath('data.device.code', 'CBC-AUTO-DEVICE')
            ->assertJsonPath('data.values.0.parameter_id', $test->parameters()->first()->id)
            ->assertJsonPath('data.values.0.value', '14.2');

        $this->getJson('/api/v1/lab-orders/'.$order->id.'/device-values')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.lab_order_item_id', $item->id)
            ->assertJsonPath('data.0.device.code', 'CBC-AUTO-DEVICE');

        $this->assertSame(LabOrderItem::STATUS_PENDING, $item->fresh()->status);
        $this->assertDatabaseCount('lab_result_values', 0);
    }
}
