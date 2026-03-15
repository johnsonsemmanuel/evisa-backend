<?php

namespace Database\Factories;

use App\Models\VisaType;
use Illuminate\Database\Eloquent\Factories\Factory;

class VisaTypeFactory extends Factory
{
    protected $model = VisaType::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['Tourist e-Visa', 'Business e-Visa', 'Student e-Visa', 'Medical e-Visa']),
            'slug' => $this->faker->unique()->slug(2),
            'description' => $this->faker->sentence(10),
            'base_fee' => 260.00,
            'max_duration_days' => $this->faker->numberBetween(30, 90),
            'is_active' => true,
            // required_documents dropped in 2026_03_15_999999; use visa_type_required_documents pivot if needed
        ];
    }
}
