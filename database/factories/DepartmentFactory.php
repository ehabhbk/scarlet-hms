<?php

namespace Database\Factories;

use App\Domains\Identity\Models\Branch;
use App\Domains\Identity\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Department>
 */
class DepartmentFactory extends Factory
{
    protected $model = Department::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'code' => 'DEP-'.fake()->unique()->numerify('###'),
            'name_ar' => 'قسم سريري',
            'name_en' => 'Clinical Department',
            'type' => 'clinical',
            'is_active' => true,
        ];
    }
}
