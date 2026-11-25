<?php

namespace Database\Factories;

use App\Domains\Identity\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'code' => 'BR-'.fake()->unique()->numerify('###'),
            'name_ar' => 'فرع سكارلت',
            'name_en' => 'Scarlet Branch',
            'type' => 'hospital',
            'city' => 'Khartoum',
            'timezone' => 'Africa/Khartoum',
            'is_active' => true,
        ];
    }
}
