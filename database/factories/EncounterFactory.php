<?php

namespace Database\Factories;

use App\Domains\Encounters\Models\Encounter;
use App\Domains\Identity\Models\Branch;
use App\Domains\Identity\Models\Department;
use App\Domains\Patients\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Encounter>
 */
class EncounterFactory extends Factory
{
    protected $model = Encounter::class;

    public function definition(): array
    {
        return [
            'encounter_number' => 'ENC-T'.fake()->unique()->numerify('######'),
            'patient_id' => Patient::factory(),
            'branch_id' => Branch::factory(),
            'department_id' => Department::factory(),
            'type' => Encounter::TYPE_OUTPATIENT,
            'status' => Encounter::STATUS_OPEN,
            'started_at' => now(),
        ];
    }
}
