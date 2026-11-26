<?php

namespace Tests\Feature\Api;

use App\Domains\Laboratory\Models\LabOrder;
use App\Domains\Laboratory\Models\LabOrderItem;
use App\Domains\Laboratory\Models\LabTest;
use App\Domains\Laboratory\Models\LabTestParameter;
use App\Domains\Patients\Models\Patient;
use App\Domains\Settings\Models\Setting;

class LabResultApprovalTest extends ApiTestCase
{
    private function makeOrder(): array
    {
        $test = LabTest::query()->create([
            'code' => 'CBC-PRINT',
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

        $simple = LabTest::query()->create([
            'code' => 'URINE-PRINT',
            'name_ar' => 'تحليل بول',
            'price' => 50,
            'is_active' => true,
        ]);

        $patient = Patient::factory()->create();
        $order = LabOrder::query()->create([
            'order_number' => '300',
            'patient_id' => $patient->id,
            'branch_id' => $this->branch->id,
            'status' => LabOrder::STATUS_ORDERED,
            'ordered_at' => now(),
        ]);

        $first = $order->items()->create([
            'lab_test_id' => $test->id,
            'status' => LabOrderItem::STATUS_PENDING,
            'sample_collected_at' => now(),
        ]);
        $second = $order->items()->create([
            'lab_test_id' => $simple->id,
            'status' => LabOrderItem::STATUS_PENDING,
            'sample_collected_at' => now(),
        ]);

        return [$order, $first, $second, $parameter];
    }

    public function test_print_requires_every_result_to_be_approved_and_marks_the_whole_sample(): void
    {
        [$order, $first, $second, $parameter] = $this->makeOrder();
        $this->actingAsRole('super_admin');

        $this->postJson('/api/v1/lab-order-items/'.$first->id.'/values', [
            'values' => [['parameter_id' => $parameter->id, 'value' => '13.5', 'flag' => 'N']],
        ])->assertOk();
        $this->postJson('/api/v1/lab-order-items/'.$second->id.'/result', [
            'result_value' => 'Negative',
        ])->assertOk();

        $this->postJson('/api/v1/lab-orders/'.$order->id.'/print')
            ->assertStatus(422)
            ->assertJsonPath('message', 'لا يمكن طباعة النتيجة إلا بعد إدخال جميع الفحوصات واعتمادها.');

        $this->postJson('/api/v1/lab-order-items/'.$first->id.'/approve')->assertOk();
        $this->postJson('/api/v1/lab-order-items/'.$second->id.'/approve')->assertOk();

        $this->getJson('/api/v1/lab-worklist')
            ->assertOk()
            ->assertJsonPath('data.0.result_status', 'approved')
            ->assertJsonPath('data.0.all_approved', true);

        $this->postJson('/api/v1/lab-orders/'.$order->id.'/print')->assertOk();

        $this->assertNotNull($first->fresh()->printed_at);
        $this->assertNotNull($second->fresh()->printed_at);
        $this->assertNotNull($second->fresh()->printed_by);

        $this->getJson('/api/v1/lab-worklist')
            ->assertOk()
            ->assertJsonPath('data.0.result_status', 'printed')
            ->assertJsonPath('data.0.items.0.printed_at', $first->fresh()->printed_at?->toJSON());
    }

    public function test_supervisor_can_withdraw_approval_and_then_edit_the_result(): void
    {
        [$order, $first, $second, $parameter] = $this->makeOrder();
        $this->actingAsRole('super_admin');

        $this->postJson('/api/v1/lab-order-items/'.$first->id.'/values', [
            'values' => [['parameter_id' => $parameter->id, 'value' => '13.5']],
        ])->assertOk();
        $this->postJson('/api/v1/lab-order-items/'.$second->id.'/result', [
            'result_value' => 'Negative',
        ])->assertOk();
        $this->postJson('/api/v1/lab-order-items/'.$first->id.'/approve')->assertOk();
        $this->postJson('/api/v1/lab-order-items/'.$second->id.'/approve')->assertOk();
        $this->postJson('/api/v1/lab-orders/'.$order->id.'/print')->assertOk();

        $this->postJson('/api/v1/lab-order-items/'.$first->id.'/unapprove')
            ->assertOk()
            ->assertJsonPath('data.status', LabOrderItem::STATUS_ENTERED);

        $fresh = $first->fresh();
        $this->assertNull($fresh->approved_by);
        $this->assertNull($fresh->approved_at);
        $this->assertNull($fresh->printed_at);
        $this->assertSame(LabOrder::STATUS_IN_PROGRESS, $order->fresh()->status);

        $this->postJson('/api/v1/lab-order-items/'.$first->id.'/values', [
            'values' => [['parameter_id' => $parameter->id, 'value' => '14.2']],
        ])->assertOk();

        $this->assertSame('14.2', $fresh->values()->first()->value);
    }

    public function test_empty_results_need_a_setting_to_be_approved(): void
    {
        [$order, $first, , $parameter] = $this->makeOrder();
        $this->actingAsRole('super_admin');

        $this->postJson('/api/v1/lab-order-items/'.$first->id.'/values', [
            'values' => [['parameter_id' => $parameter->id, 'value' => '']],
        ])->assertOk();

        $this->postJson('/api/v1/lab-order-items/'.$first->id.'/approve')
            ->assertStatus(422)
            ->assertJsonPath('message', 'لا يمكن اعتماد نتيجة تحتوي حقولاً فارغة.');

        Setting::put('lab_allow_empty_results', true, 'laboratory', false);

        $this->postJson('/api/v1/lab-order-items/'.$first->id.'/approve')
            ->assertOk()
            ->assertJsonPath('data.status', LabOrderItem::STATUS_APPROVED);

        $this->assertNotNull($first->fresh()->approved_at);
    }
}
