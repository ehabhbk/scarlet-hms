<?php

namespace Tests\Feature\Api;

use App\Domains\Integration\Services\DeviceMessageIngestService;
use App\Domains\Patients\Models\Patient;
use App\Domains\Radiology\Models\RadiologyDevice;
use App\Domains\Radiology\Models\RadiologyDeviceMessage;
use App\Domains\Radiology\Models\RadiologyImage;
use App\Domains\Radiology\Models\RadiologyOrder;
use App\Domains\Radiology\Models\RadiologyStudy;

class RadiologyResultsWorkspaceTest extends ApiTestCase
{
    private function study(): RadiologyStudy
    {
        return RadiologyStudy::query()->create([
            'code' => 'XR-CHEST-WS',
            'name_ar' => 'أشعة سينية للصدر',
            'name_en' => 'Chest X-Ray',
            'modality' => 'X-Ray',
            'price' => 100,
            'is_active' => true,
        ]);
    }

    private function order(Patient $patient, RadiologyStudy $study): RadiologyOrder
    {
        return RadiologyOrder::query()->create([
            'order_number' => 'RAD-WS-'.random_int(1000, 9999),
            'patient_id' => $patient->id,
            'branch_id' => $this->branch->id,
            'modality' => $study->modality,
            'study_description' => $study->name_ar,
            'radiology_study_id' => $study->id,
            'status' => RadiologyOrder::STATUS_ORDERED,
            'ordered_at' => now(),
        ]);
    }

    public function test_worklist_rings_follow_real_order_states(): void
    {
        $study = $this->study();
        $patient = Patient::factory()->create();
        $order = $this->order($patient, $study);

        RadiologyDevice::query()->create([
            'code' => 'XR-WS',
            'name' => 'جهاز الأشعة',
            'protocol' => 'HL7',
            'modality' => 'X-Ray',
            'status' => 'active',
        ]);

        $user = $this->actingAsRole('super_admin');

        $row = fn (): array => collect($this->getJson('/api/v1/radiology-worklist')->assertOk()->json('data'))
            ->firstWhere('order_number', $order->order_number);

        $this->assertSame('empty', $row()['result_status']);
        $this->assertSame($patient->mrn, $row()['patient']['mrn']);

        $raw = implode("\r", [
            'MSH|^~\\&|RIS|XR-WS|PACS|HMS|20261127120000||ORU^R01|MSG-WS-1|P|2.5',
            'PID|1||'.$patient->mrn.'||Test^Patient',
            'OBR|1|'.$order->order_number.'||XR-CHEST-WS^Chest X-Ray|||20261127115500',
            'OBX|1|ST|REPORT^Report|1|لا يوجد ارتشاح حاد|||N|||F',
        ]);

        app(DeviceMessageIngestService::class)->ingestRadiology($raw, ['device_code' => 'XR-WS']);

        $received = $row();
        $this->assertSame('received', $received['result_status']);
        $this->assertSame('device', $received['source']);
        $this->assertNull($received['reporter']);

        $this->postJson("/api/v1/radiology-orders/{$order->id}/confirm-result", [
            'report' => $order->fresh()->report,
        ])->assertOk();

        $signed = $row();
        $this->assertSame('approved', $signed['result_status']);
        $this->assertSame($user->id, $signed['reporter']['id']);

        $this->postJson("/api/v1/radiology-orders/{$order->id}/print")->assertOk();

        $this->assertSame('printed', $row()['result_status']);
    }

    public function test_device_data_lists_mapped_device_messages_and_received_images(): void
    {
        $study = $this->study();
        $patient = Patient::factory()->create();
        $order = $this->order($patient, $study);

        $device = RadiologyDevice::query()->create([
            'code' => 'DR-WS',
            'name' => 'جهاز الأشعة الرقمي',
            'protocol' => 'DICOM',
            'ae_title' => 'DR-WS',
            'modality' => 'X-Ray',
            'status' => 'active',
        ]);
        $device->studies()->attach($study->id);

        $message = RadiologyDeviceMessage::query()->create([
            'radiology_device_id' => $device->id,
            'raw_message' => 'MSH|^~\\&|DR-WS|PACS|SCARLET|HMS|20261127120000||ORU^R01|MSG-WS-2|P|2.5',
            'status' => 'imported',
            'message_id' => 'MSG-WS-2',
            'parsed_summary' => 'ORDER='.$order->order_number.' | MRN='.$patient->mrn,
            'received_at' => now(),
        ]);

        $image = RadiologyImage::query()->create([
            'radiology_order_id' => $order->id,
            'radiology_device_id' => $device->id,
            'radiology_device_message_id' => $message->id,
            'path' => 'dicom/2026/11/27/test.dcm',
            'sop_uid' => '1.2.826.0.1.3680043.10.9009',
            'modality' => 'XR',
            'received_at' => now(),
        ]);

        $this->actingAsRole('super_admin');

        $this->getJson("/api/v1/radiology-orders/{$order->id}/device-data")
            ->assertOk()
            ->assertJsonPath('data.devices.0.id', $device->id)
            ->assertJsonPath('data.devices.0.mapped_reason', 'study')
            ->assertJsonPath('data.messages.0.id', $message->id)
            ->assertJsonPath('data.images.0.id', $image->id);
    }

    public function test_report_is_saved_as_draft_then_signed_by_a_human(): void
    {
        $study = $this->study();
        $order = $this->order(Patient::factory()->create(), $study);
        $user = $this->actingAsRole('super_admin');

        $this->postJson("/api/v1/radiology-orders/{$order->id}/report", [
            'report' => 'مسودة أولية',
        ])->assertOk()->assertJsonPath('data.status', RadiologyOrder::STATUS_ORDERED);

        $this->assertDatabaseHas('radiology_orders', [
            'id' => $order->id,
            'status' => RadiologyOrder::STATUS_ORDERED,
            'report' => 'مسودة أولية',
            'reported_by' => null,
        ]);

        $this->postJson("/api/v1/radiology-orders/{$order->id}/confirm-result", [
            'report' => 'التقرير النهائي',
        ])->assertOk()
            ->assertJsonPath('data.status', RadiologyOrder::STATUS_COMPLETED)
            ->assertJsonPath('data.reporter.id', $user->id);

        $this->postJson("/api/v1/radiology-orders/{$order->id}/report", [
            'report' => 'تعديل بعد التوقيع',
        ])->assertStatus(422);

        $this->postJson("/api/v1/radiology-orders/{$order->id}/reopen")
            ->assertOk()
            ->assertJsonPath('data.status', RadiologyOrder::STATUS_ORDERED)
            ->assertJsonPath('data.reporter', null);

        $this->postJson("/api/v1/radiology-orders/{$order->id}/report", [
            'report' => 'تقرير معدّل',
        ])->assertOk();
    }

    public function test_manual_fetch_reports_device_errors_without_signing_the_order(): void
    {
        $study = $this->study();
        $order = $this->order(Patient::factory()->create(), $study);

        $device = RadiologyDevice::query()->create([
            'code' => 'FETCH-WS',
            'name' => 'جهاز غير متصل',
            'protocol' => 'HL7',
            'modality' => 'X-Ray',
            'status' => 'active',
            'host' => null,
            'port' => null,
        ]);
        $device->studies()->attach($study->id);

        $this->actingAsRole('super_admin');

        $this->postJson("/api/v1/radiology-orders/{$order->id}/fetch-devices")
            ->assertOk()
            ->assertJsonPath('data.polls.0.device.id', $device->id)
            ->assertJsonPath('data.polls.0.result.frames', 0);

        $this->assertSame(RadiologyOrder::STATUS_ORDERED, $order->fresh()->status);
        $this->assertNull($order->fresh()->reported_by);
    }
}
