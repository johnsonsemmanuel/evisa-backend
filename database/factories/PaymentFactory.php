<?php

namespace Database\Factories;

use App\Enums\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\Application;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'user_id' => fn (array $attr) => Application::find($attr['application_id'])->user_id,
            'gateway' => PaymentGateway::GCB,
            'transaction_reference' => 'GCB-' . now()->format('YmdHis') . '-' . strtoupper($this->faker->bothify('??????')),
            'payment_provider' => 'gcb',
            'gateway_reference' => null,
            'currency' => 'GHS',
            'amount' => 25000, // 250.00 GHS in pesewas
            'status' => PaymentStatus::Initiated,
            'provider_response' => [],
            'raw_response' => null,
            'metadata' => null,
            'paid_at' => null,
            'failure_reason' => null,
        ];
    }

    public function gcb(): static
    {
        return $this->state(fn (array $attributes) => [
            'gateway' => PaymentGateway::GCB,
            'payment_provider' => 'gcb',
            'transaction_reference' => 'GCB-' . now()->format('YmdHis') . '-' . strtoupper($this->faker->bothify('??????')),
        ]);
    }

    public function paystack(): static
    {
        return $this->state(fn (array $attributes) => [
            'gateway' => PaymentGateway::Paystack,
            'payment_provider' => 'paystack',
            'transaction_reference' => 'PS-' . now()->format('YmdHis') . '-' . strtoupper($this->faker->bothify('??????')),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
    }
}
