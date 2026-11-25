<?php

namespace Database\Factories;

use App\Domains\Patients\Models\Patient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition(): array
    {
        $male = fake()->boolean();

        return [
            'mrn' => 'SC-T'.fake()->unique()->numerify('######'),
            'first_name_ar' => $male ? 'أحمد' : 'فاطمة',
            'last_name_ar' => 'اختبار',
            'first_name_en' => $male ? 'Ahmed' : 'Fatima',
            'last_name_en' => 'Test',
            'national_id' => fake()->unique()->numerify('##########'),
            'gender' => $male ? 'male' : 'female',
            'date_of_birth' => fake()->dateTimeBetween('-80 years', '-1 year')->format('Y-m-d'),
            'phone' => '09'.fake()->numerify('########'),
            'city' => 'Khartoum',
            'status' => Patient::STATUS_ACTIVE,
        ];
    }

    public function female(): static
    {
        return $this->state(fn () => [
            'first_name_ar' => 'فاطمة',
            'first_name_en' => 'Fatima',
            'gender' => 'female',
        ]);
    }
}
