<?php

namespace App\Http\Middleware;

use App\Models\Application;
use App\Services\PricingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to prevent price manipulation attacks.
 * Validates that submitted prices match server-calculated prices.
 */
class ValidateApplicationPrice
{
    public function __construct(
        protected PricingService $pricingService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only validate on payment initiation endpoints
        if (!$this->shouldValidate($request)) {
            return $next($request);
        }

        // Get application from route or request
        $application = $this->getApplication($request);
        
        if (!$application) {
            return $next($request);
        }

        // Calculate server-side price
        $calculatedPricing = $this->pricingService->calculatePrice($application);
        $serverPrice = $calculatedPricing['total'];

        // Check if application has a stored price
        if ($application->total_fee) {
            // Validate stored price matches calculation
            if (!$this->pricingService->validatePrice($application, $application->total_fee)) {
                return response()->json([
                    'message' => 'Price manipulation detected. Application price has been recalculated.',
                    'error' => 'PRICE_MISMATCH',
                    'server_price' => $serverPrice,
                ], 422);
            }
        }

        // Store validated price in request for downstream use
        $request->merge(['validated_price' => $serverPrice]);

        return $next($request);
    }

    /**
     * Determine if this request should be validated.
     */
    protected function shouldValidate(Request $request): bool
    {
        $uri = $request->path();
        
        // Validate payment initiation endpoints
        return str_contains($uri, '/payment/initialize') 
            || str_contains($uri, '/payment/initiate')
            || str_contains($uri, '/applications') && $request->isMethod('POST');
    }

    /**
     * Get application from route parameter or request.
     */
    protected function getApplication(Request $request): ?Application
    {
        // Try to get from route parameter
        $application = $request->route('application');
        
        if ($application instanceof Application) {
            return $application;
        }

        // Try to get from request body (for creation)
        if ($request->has('application_id')) {
            return Application::find($request->input('application_id'));
        }

        return null;
    }
}
