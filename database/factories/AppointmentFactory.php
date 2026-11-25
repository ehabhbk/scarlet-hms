<?php

namespace Database\Factories;

use App\Domains\Identity\Models\Branch;
use App\Domains\Identity\Models\Department;
use App\Domains\Patients\Models\Patient;
use App\Domains\Scheduling\Models\Appointment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        return [
            'appointment_number' => 'APT-T'.fake()->unique()->numerify('######'),
            'patient_id' => Patient::factory(),
            'branch_id' => Branch::factory(),
            'department_id' => Department::factory(),
            'scheduled_at' => now()->addHour(),
            'duration_minutes' => 20,
            'type' => 'new',
            'status' => Appointment::STATUS_BOOKED,
        ];
    }
}
