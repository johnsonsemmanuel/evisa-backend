<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\User;
use App\Models\VisaType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApplicationFactory extends Factory
{
    protected $model = Application::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'visa_type_id' => VisaType::factory(),
            'reference_number' => 'TEST-' . date('Y') . '-' . str_pad($this->faker->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'status' => 'draft',
            'first_name_encrypted' => $this->faker->firstName,
            'last_name_encrypted' => $this->faker->lastName,
            'email_encrypted' => $this->faker->email,
            'phone_encrypted' => $this->faker->phoneNumber,
            'passport_number_encrypted' => $this->faker->bothify('??#######'),
            'nationality_encrypted' => $this->faker->country,
            'date_of_birth_encrypted' => $this->faker->date('Y-m-d', '-30 years'),
            'intended_arrival' => $this->faker->dateTimeBetween('+1 week', '+3 months'),
            'duration_days' => $this->faker->numberBetween(7, 90),
            'purpose_of_visit' => $this->faker->randomElement(['Tourism', 'Business', 'Study', 'Medical']),
            'visa_channel' => 'e-visa',
            'tier' => 'tier_1',
            'processing_tier' => 'fast_track',
            'assigned_agency' => null,
            'current_queue' => null,
            'sla_deadline' => null,
            'submitted_at' => null,
            'decided_at' => null,
            'decision_notes' => null,
            'risk_screening_status' => 'pending',
            'assigned_officer_id' => null,
        ];
    }

    public function underReview(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'under_review',
            'assigned_agency' => 'gis',
            'current_queue' => 'review_queue',
            'sla_deadline' => now()->addHours(72),
            'submitted_at' => now()->subHours(2),
        ]);
    }

    public function pendingApproval(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending_approval',
            'assigned_agency' => 'gis',
            'current_queue' => 'approval_queue',
            'sla_deadline' => now()->addHours(24),
            'submitted_at' => now()->subHours(12),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'assigned_agency' => 'gis',
            'current_queue' => null,
            'sla_deadline' => now()->addHours(24),
            'submitted_at' => now()->subHours(48),
            'decided_at' => now()->subHours(1),
        ]);
    }

    public function issued(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'issued',
            'assigned_agency' => 'gis',
            'current_queue' => null,
            'sla_deadline' => now()->addHours(24),
            'submitted_at' => now()->subHours(72),
            'decided_at' => now()->subHours(24),
        ]);
    }
}
