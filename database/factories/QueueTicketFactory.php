<?php

namespace Database\Factories;

use App\Domains\Identity\Models\Branch;
use App\Domains\Identity\Models\Department;
use App\Domains\Patients\Models\Patient;
use App\Domains\Scheduling\Models\QueueTicket;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QueueTicket>
 */
class QueueTicketFactory extends Factory
{
    protected $model = QueueTicket::class;

    public function definition(): array
    {
        return [
            'queue_date' => today(),
            'branch_id' => Branch::factory(),
            'department_id' => Department::factory(),
            'patient_id' => Patient::factory(),
            'number' => fake()->unique()->numberBetween(1, 999),
            'prefix' => 'Q',
            'status' => QueueTicket::STATUS_WAITING,
            'priority' => QueueTicket::PRIORITY_NORMAL,
        ];
    }
}
