<?php

namespace Tests\Feature\Api;

use App\Domains\Laboratory\Models\LabTest;
use App\Domains\Patients\Models\Patient;

class LabOrderNumberTest extends ApiTestCase
{
    public function test_lab_order_numbers_are_plain_unique_integers_even_for_the_same_patient(): void
    {
        $test = LabTest::query()->create([
            'code' => 'CBC-NUMBER',
            'name_ar' => 'صورة دم كاملة',
            'price' => 100,
            'is_active' => true,
        ]);
        $patient = Patient::factory()->create();

        $this->actingAsRole('super_admin');

        $numbers = [];

        foreach ([1, 2, 3] as $ignored) {
            $response = $this->postJson('/api/v1/lab-orders', [
                'patient_id' => $patient->id,
                'branch_id' => $this->branch->id,
                'test_ids' => [$test->id],
            ]);

            $response->assertCreated();
            $numbers[] = $response->json('data.order_number');
        }

        $this->assertSame(['1', '2', '3'], $numbers);
        $this->assertCount(3, array_unique($numbers));
        $this->assertDatabaseCount('lab_orders', 3);
    }
}
