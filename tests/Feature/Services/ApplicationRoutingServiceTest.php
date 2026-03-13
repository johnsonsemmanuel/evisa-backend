<?php

namespace Tests\Feature\Services;

use Tests\TestCase;
use App\Models\Application;
use App\Models\User;
use App\Models\VisaType;
use App\Services\ApplicationRoutingService;
use App\Services\TierClassificationService;
use App\Services\SlaService;

class ApplicationRoutingServiceTest extends TestCase
{
    protected ApplicationRoutingService $routingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->routingService = new ApplicationRoutingService(
            new TierClassificationService(),
            new SlaService()
        );
    }

    /** @test */
    public function it_routes_evisa_fast_track_to_gis()
    {
        $application = Application::factory()->create([
            'visa_channel' => 'e-visa',
            'processing_tier' => 'fast_track',
        ]);

        $routedApp = $this->routingService->route($application);

        $this->assertEquals('gis', $routedApp->assigned_agency);
        $this->assertEquals('under_review', $routedApp->status);
        $this->assertEquals('review_queue', $routedApp->current_queue);
        $this->assertNotNull($routedApp->sla_deadline);
    }

    /** @test */
    public function it_routes_regular_visa_to_mfa()
    {
        $application = Application::factory()->create([
            'visa_channel' => 'regular',
        ]);

        $routedApp = $this->routingService->route($application);

        $this->assertEquals('mfa', $routedApp->assigned_agency);
        $this->assertEquals('under_review', $routedApp->status);
        $this->assertEquals('review_queue', $routedApp->current_queue);
    }

    /** @test */
    public function it_routes_evisa_standard_to_mfa()
    {
        $application = Application::factory()->create([
            'visa_channel' => 'e-visa',
            'processing_tier' => 'standard',
        ]);

        $routedApp = $this->routingService->route($application);

        $this->assertEquals('mfa', $routedApp->assigned_agency);
        $this->assertEquals('under_review', $routedApp->status);
    }

    /** @test */
    public function it_sets_default_tier_when_no_rule_matches()
    {
        $application = Application::factory()->create([
            'visa_channel' => 'e-visa',
        ]);

        $routedApp = $this->routingService->route($application);

        $this->assertEquals('tier_1', $routedApp->tier);
        $this->assertEquals('fast_track', $routedApp->processing_tier);
        $this->assertEquals('gis', $routedApp->assigned_agency);
    }

    /** @test */
    public function it_can_escalate_to_mfa()
    {
        $application = Application::factory()->create([
            'assigned_agency' => 'gis',
            'status' => 'under_review',
        ]);

        $escalatedApp = $this->routingService->escalateToMfa($application);

        $this->assertEquals('mfa', $escalatedApp->assigned_agency);
        $this->assertEquals('escalated', $escalatedApp->status);
        $this->assertNull($escalatedApp->assigned_officer_id);
    }

    /** @test */
    public function it_can_return_to_gis_from_mfa()
    {
        $application = Application::factory()->create([
            'assigned_agency' => 'mfa',
            'status' => 'escalated',
        ]);

        $returnedApp = $this->routingService->returnToGis($application);

        $this->assertEquals('gis', $returnedApp->assigned_agency);
        $this->assertEquals('under_review', $returnedApp->status);
        $this->assertNull($returnedApp->assigned_officer_id);
    }
}
