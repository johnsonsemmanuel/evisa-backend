<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\User;
use App\Services\GcbPaymentService;
use App\Services\MultiPaymentService;
use App\Services\PaystackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Application $application;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->application = Application::factory()->create([
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function it_can_get_available_payment_methods()
    {
        $service = app(MultiPaymentService::class);
        $methods = $service->getAvailablePaymentMethods('GH');

        $this->assertIsArray($methods);
        $this->assertNotEmpty($methods);
        
        // Check for GCB methods
        $gcbMethods = array_filter($methods, fn($m) => $m['provider'] === 'gcb');
        $this->assertCount(6, $gcbMethods);

        // Check for Paystack methods
        $paystackMethods = array_filter($methods, fn($m) => $m['provider'] === 'paystack');
        $this->assertCount(2, $paystackMethods);
    }

    /** @test */
    public function it_can_initialize_gcb_payment()
    {
        $service = app(MultiPaymentService::class);
        
        $result = $service->initializePayment(
            $this->application,
            'gcb_card',
            'GHS',
            'http://localhost/callback'
        );

        // In test environment without API key, it should return error
        $this->assertArrayHasKey('success', $result);
        
        if (!$result['success']) {
            $this->assertStringContainsString('not configured', $result['message']);
        }
    }

    /** @test */
    public function it_can_initialize_paystack_payment()
    {
        $service = app(MultiPaymentService::class);
        
        $result = $service->initializePayment(
            $this->application,
            'paystack_card',
            'GHS',
            'http://localhost/callback'
        );

        // In test environment without API key, it should return error
        $this->assertArrayHasKey('success', $result);
        
        if (!$result['success']) {
            $this->assertStringContainsString('not configured', $result['message']);
        }
    }

    /** @test */
    public function gcb_service_generates_correct_merchant_ref()
    {
        $service = app(GcbPaymentService::class);
        
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('generateMerchantRef');
        $method->setAccessible(true);
        
        $ref = $method->invoke($service, $this->application);
        
        $this->assertStringStartsWith('GCB', $ref);
        $this->assertLessThanOrEqual(20, strlen($ref));
    }

    /** @test */
    public function paystack_service_generates_correct_reference()
    {
        $service = app(PaystackService::class);
        
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('generateReference');
        $method->setAccessible(true);
        
        $ref = $method->invoke($service, $this->application);
        
        $this->assertStringStartsWith('PS-', $ref);
        $this->assertStringContainsString($this->application->reference_number, $ref);
    }

    /** @test */
    public function it_handles_gcb_status_codes_correctly()
    {
        $service = app(GcbPaymentService::class);
        
        // Use reflection to test protected method
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('getStatusMessage');
        $method->setAccessible(true);
        
        $this->assertEquals('Payment Successful', $method->invoke($service, '00'));
        $this->assertEquals('Payment Pending', $method->invoke($service, '01'));
        $this->assertEquals('Payment Failed', $method->invoke($service, '02'));
        $this->assertEquals('Checkout URL Expired', $method->invoke($service, '03'));
        $this->assertEquals('Unknown Status', $method->invoke($service, '99'));
    }

    /** @test */
    public function it_can_handle_gcb_callback()
    {
        $this->actingAs($this->user);

        $payload = [
            'merchantRef' => 'GCB-TEST-123',
            'statusCode' => '00',
            'bankRef' => '999EPAY0000001',
            'timeCompleted' => '2025-03-13 10:00:00',
            'paymentOption' => 'card',
        ];

        $response = $this->postJson('/api/webhooks/gcb', $payload);

        // Should return 200 even if payment not found (GCB requirement)
        $response->assertStatus(200);
    }

    /** @test */
    public function it_can_handle_paystack_webhook()
    {
        $this->actingAs($this->user);

        $payload = [
            'event' => 'charge.success',
            'data' => [
                'reference' => 'PS-TEST-123',
                'status' => 'success',
                'amount' => 10000,
                'currency' => 'GHS',
            ],
        ];

        $response = $this->postJson('/api/webhooks/paystack', $payload);

        // Should return 400 when payment not found (expected behavior)
        $response->assertStatus(400);
        $response->assertJson(['message' => 'Payment not found']);
    }

    /** @test */
    public function payment_methods_are_filtered_by_country()
    {
        $service = app(MultiPaymentService::class);
        
        // Ghana should have GCB methods
        $ghMethods = $service->getAvailablePaymentMethods('GH');
        $gcbMethods = array_filter($ghMethods, fn($m) => $m['provider'] === 'gcb');
        $this->assertNotEmpty($gcbMethods);

        // Nigeria should not have GCB methods
        $ngMethods = $service->getAvailablePaymentMethods('NG');
        $gcbMethods = array_filter($ngMethods, fn($m) => $m['provider'] === 'gcb');
        $this->assertEmpty($gcbMethods);

        // But should have Paystack
        $paystackMethods = array_filter($ngMethods, fn($m) => $m['provider'] === 'paystack');
        $this->assertNotEmpty($paystackMethods);
    }
}
