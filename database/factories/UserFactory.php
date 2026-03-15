<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password123'),
            'role' => 'applicant',
            'agency' => null,
            'locale' => 'en',
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Create an applicant user
     */
    public function applicant(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'applicant',
            'agency' => null,
            'mfa_mission_id' => null,
            'can_review' => false,
            'can_approve' => false,
        ]);
    }

    /**
     * Create a GIS officer user
     */
    public function gisOfficer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'gis_officer',
            'agency' => 'gis',
            'mfa_mission_id' => null,
            'can_review' => true,
            'can_approve' => false,
        ]);
    }

    /**
     * Create a GIS reviewer user
     */
    public function gisReviewer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'gis_reviewer',
            'agency' => 'gis',
            'mfa_mission_id' => null,
            'can_review' => true,
            'can_approve' => false,
        ]);
    }

    /**
     * Create a GIS approver user
     */
    public function gisApprover(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'gis_approver',
            'agency' => 'gis',
            'mfa_mission_id' => null,
            'can_review' => false,
            'can_approve' => true,
        ]);
    }

    /**
     * Create a GIS admin user
     */
    public function gisAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'gis_admin',
            'agency' => 'gis',
            'mfa_mission_id' => null,
            'can_review' => true,
            'can_approve' => true,
        ]);
    }

    /**
     * Create an MFA officer user
     */
    public function mfaOfficer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'mfa_reviewer',
            'agency' => 'mfa',
            'mfa_mission_id' => 1, // Default mission
            'can_review' => true,
            'can_approve' => false,
        ]);
    }

    /**
     * Create an MFA reviewer user
     */
    public function mfaReviewer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'mfa_reviewer',
            'agency' => 'mfa',
            'mfa_mission_id' => 1, // Default mission
            'can_review' => true,
            'can_approve' => false,
        ]);
    }

    /**
     * Create an MFA approver user
     */
    public function mfaApprover(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'mfa_approver',
            'agency' => 'mfa',
            'mfa_mission_id' => 1, // Default mission
            'can_review' => false,
            'can_approve' => true,
        ]);
    }

    /**
     * Create an MFA admin user
     */
    public function mfaAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'mfa_admin',
            'agency' => 'mfa',
            'mfa_mission_id' => null, // Admins can access all missions
            'can_review' => true,
            'can_approve' => true,
        ]);
    }

    /**
     * Create a finance officer user
     */
    public function financeOfficer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'finance_officer',
            'agency' => 'finance',
            'mfa_mission_id' => null,
            'can_review' => false,
            'can_approve' => false,
        ]);
    }

    /**
     * Create a border officer user
     */
    public function borderOfficer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'border_officer',
            'agency' => 'border',
            'mfa_mission_id' => null,
            'can_review' => false,
            'can_approve' => false,
        ]);
    }

    /**
     * Create an admin user
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
            'agency' => null,
            'mfa_mission_id' => null,
            'can_review' => true,
            'can_approve' => true,
        ]);
    }

    /**
     * Assign user to a specific agency
     */
    public function forAgency(string $agency): static
    {
        return $this->state(fn (array $attributes) => [
            'agency' => $agency,
        ]);
    }

    /**
     * Assign user to a specific MFA mission
     */
    public function forMission(int $missionId): static
    {
        return $this->state(fn (array $attributes) => [
            'mfa_mission_id' => $missionId,
        ]);
    }

    /**
     * Applicant with KYC fields set (e.g. after Sumsub flow)
     */
    public function applicantWithKyc(string $status = 'under_review'): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'applicant',
            'kyc_status' => $status,
            'kyc_completed_at' => in_array($status, ['approved', 'rejected'], true) ? now() : null,
        ]);
    }

    /**
     * User with Sumsub applicant ID stored (after createApplicant)
     */
    public function withSumsubApplicant(?string $sumsubId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'sumsub_applicant_id' => $sumsubId ?? 'sumsub-test-id-' . ($attributes['id'] ?? $this->faker->uuid),
            'kyc_level' => 'basic-kyc-level',
        ]);
    }
}
