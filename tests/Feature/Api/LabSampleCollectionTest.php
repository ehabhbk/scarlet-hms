<?php

namespace Tests\Feature\Api;

use App\Domains\Laboratory\Models\LabOrder;
use App\Domains\Laboratory\Models\LabOrderItem;
use App\Domains\Laboratory\Models\LabTest;
use App\Domains\Patients\Models\Patient;

class LabSampleCollectionTest extends ApiTestCase
{
    private function makePendingOrder(string $number): array
    {
        $test = LabTest::query()->create([
            'code' => 'CBC-'.$number,
            'name_ar' => 'صورة دم كاملة '.$number,
            'price' => 100,
            'is_active' => true,
        ]);

        $patient = Patient::factory()->create();
        $order = LabOrder::query()->create([
            'order_number' => $number,
            'patient_id' => $patient->id,
            'branch_id' => $this->branch->id,
            'status' => LabOrder::STATUS_ORDERED,
            'ordered_at' => now(),
        ]);

        $items = collect(range(1, 2))->map(fn () => $order->items()->create([
            'lab_test_id' => $test->id,
            'status' => LabOrderItem::STATUS_PENDING,
        ]));

        return [$order, $items, $patient];
    }

    public function test_collection_worklist_groups_real_orders_with_plain_integer_numbers(): void
    {
        [$firstOrder, , $firstPatient] = $this->makePendingOrder('501');
        [$secondOrder, $secondItems, $secondPatient] = $this->makePendingOrder('502');
        $secondItems->first()->update(['sample_collected_at' => now()]);

        $this->actingAsRole('sample_collector');

        $response = $this->getJson('/api/v1/lab-collection-worklist')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $rows = collect($response->json('data'))->keyBy('order_number');

        $this->assertSame('501', (string) $rows->get('501')['order_number']);
        $this->assertSame('pending', $rows->get('501')['collection_status']);
        $this->assertSame(0, $rows->get('501')['collected_count']);
        $this->assertSame('partial', $rows->get('502')['collection_status']);
        $this->assertSame(1, $rows->get('502')['collected_count']);
        $this->assertSame($firstPatient->id, $rows->get('501')['patient']['id']);
        $this->assertSame($secondPatient->id, $rows->get('502')['patient']['id']);
        $this->assertSame($firstOrder->id, $rows->get('501')['id']);
        $this->assertSame($secondOrder->id, $rows->get('502')['id']);
    }

    public function test_sample_collector_can_collect_every_item_of_an_order_at_once(): void
    {
        [$order, $items] = $this->makePendingOrder('503');
        $collector = $this->actingAsRole('sample_collector');

        $this->postJson('/api/v1/lab-orders/'.$order->id.'/collect')
            ->assertOk()
            ->assertJsonPath('data.collected_count', 2);

        $this->assertNotNull($items->first()->fresh()->sample_collected_at);
        $this->assertNotNull($items->last()->fresh()->sample_collected_at);
        $this->assertSame($collector->id, $items->first()->fresh()->sample_collected_by);
        $this->assertSame(LabOrder::STATUS_IN_PROGRESS, $order->fresh()->status);

        $this->getJson('/api/v1/lab-collection-worklist')
            ->assertOk()
            ->assertJsonPath('data.0.collection_status', 'collected')
            ->assertJsonPath('data.0.pending_count', 0);
    }

    public function test_laboratory_technician_cannot_collect_samples(): void
    {
        [$order] = $this->makePendingOrder('504');
        $this->actingAsRole('laboratory_technician');

        $this->postJson('/api/v1/lab-orders/'.$order->id.'/collect')->assertForbidden();
        $this->assertNull($order->items()->first()->sample_collected_at);
    }
}
