<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\SlaService;
use Carbon\Carbon;

class SlaServiceTest extends TestCase
{
    protected SlaService $slaService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->slaService = new SlaService();
    }

    /** @test */
    public function it_calculates_deadline_correctly()
    {
        $deadline = $this->slaService->calculateDeadline(72); // 72 hours

        $expected = Carbon::now()->addHours(72);
        $this->assertEquals($expected->toDateTimeString(), $deadline->toDateTimeString());
    }

    /** @test */
    public function it_calculates_remaining_hours()
    {
        $application = \App\Models\Application::factory()->create([
            'sla_deadline' => Carbon::now()->addHours(48),
            'status' => 'under_review'
        ]);

        $remaining = $this->slaService->remainingHours($application);

        $this->assertGreaterThan(47, $remaining);
        $this->assertLessThan(49, $remaining);
    }

    /** @test */
    public function it_detects_sla_breach()
    {
        $application = \App\Models\Application::factory()->create([
            'sla_deadline' => Carbon::now()->subHours(1),
            'status' => 'under_review'
        ]);

        $isBreached = $this->slaService->isBreached($application);

        $this->assertTrue($isBreached);
    }

    /** @test */
    public function it_detects_no_sla_breach()
    {
        $application = \App\Models\Application::factory()->create([
            'sla_deadline' => Carbon::now()->addHours(1),
            'status' => 'under_review'
        ]);

        $isBreached = $this->slaService->isBreached($application);

        $this->assertFalse($isBreached);
    }

    /** @test */
    public function it_extends_deadline()
    {
        $originalDeadline = Carbon::now()->addHours(24);
        $extendedDeadline = $this->slaService->extendDeadline($originalDeadline, 48);

        $expected = Carbon::now()->addHours(72); // 24 + 48
        $this->assertEquals($expected->toDateTimeString(), $extendedDeadline->toDateTimeString());
    }

    /** @test */
    public function it_handles_zero_hours()
    {
        $deadline = $this->slaService->calculateDeadline(0);

        $this->assertEquals(Carbon::now()->toDateTimeString(), $deadline->toDateTimeString());
    }

    /** @test */
    public function it_gets_approaching_breach_applications()
    {
        // Create applications with different SLA deadlines
        \App\Models\Application::factory()->create([
            'sla_deadline' => Carbon::now()->addHours(2), // Approaching
            'status' => 'under_review'
        ]);
        
        \App\Models\Application::factory()->create([
            'sla_deadline' => Carbon::now()->addHours(10), // Not approaching
            'status' => 'under_review'
        ]);

        $approaching = $this->slaService->getApproachingBreach(6); // 6 hour threshold

        $this->assertCount(1, $approaching);
    }

    /** @test */
    public function it_gets_breached_applications()
    {
        // Create breached application
        \App\Models\Application::factory()->create([
            'sla_deadline' => Carbon::now()->subHours(1),
            'status' => 'under_review'
        ]);

        // Create non-breached application
        \App\Models\Application::factory()->create([
            'sla_deadline' => Carbon::now()->addHours(1),
            'status' => 'under_review'
        ]);

        $breached = $this->slaService->getBreached();

        $this->assertCount(1, $breached);
    }

    /** @test */
    public function it_gets_sla_stats()
    {
        // Create test applications
        \App\Models\Application::factory()->create([
            'sla_deadline' => Carbon::now()->addHours(10),
            'status' => 'under_review'
        ]);

        $stats = $this->slaService->getStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_tracked', $stats);
        $this->assertArrayHasKey('currently_breached', $stats);
        $this->assertArrayHasKey('approaching_breach', $stats);
        $this->assertArrayHasKey('compliance_rate', $stats);
        $this->assertArrayHasKey('avg_processing_hours', $stats);
    }
}
