<?php

namespace App\Services;

use App\Exceptions\UnauthorizedExternalRequestException;
use Illuminate\Support\Facades\Log;

class ExternalUrlValidator
{
    /**
     * Validate external URL against allowlist and security rules.
     * 
     * SECURITY: Prevents SSRF attacks by:
     * 1. Allowlisting trusted external hosts
     * 2. Blocking private IP ranges (RFC 1918, RFC 4193, loopback)
     * 3. Blocking localhost and internal domains
     * 4. Validating URL format and scheme
     * 
     * @param string $url
     * @throws UnauthorizedExternalRequestException
     */
    public function validateExternalUrl(string $url): void
    {
        // Parse URL
        $parsed = parse_url($url);
        
        if (!$parsed || !isset($parsed['host'])) {
            throw new UnauthorizedExternalRequestException("Invalid URL format: {$url}");
        }
        
        $host = strtolower($parsed['host']);
        $scheme = $parsed['scheme'] ?? '';
        
        // Only allow HTTPS for external requests (security requirement)
        if (!in_array($scheme, ['https'])) {
            throw new UnauthorizedExternalRequestException("Only HTTPS URLs are allowed: {$url}");
        }
        
        // Check against allowlist
        $allowedHosts = $this->getAllowedHosts();
        
        if (!in_array($host, $allowedHosts)) {
            Log::warning('SSRF attempt blocked: Host not in allowlist', [
                'url' => $url,
                'host' => $host,
                'allowed_hosts' => $allowedHosts,
                'user_agent' => request()->userAgent(),
                'ip' => request()->ip(),
            ]);
            
            throw new UnauthorizedExternalRequestException("Host not in allowlist: {$host}");
        }
        
        // Additional security: Block private IP ranges (SSRF via DNS rebinding)
        if ($this->isPrivateIp($host)) {
            Log::warning('SSRF attempt blocked: Private IP range', [
                'url' => $url,
                'host' => $host,
                'user_agent' => request()->userAgent(),
                'ip' => request()->ip(),
            ]);
            
            throw new UnauthorizedExternalRequestException("Private IP ranges not allowed: {$host}");
        }
        
        Log::info('External URL validated successfully', [
            'url' => $url,
            'host' => $host,
        ]);
    }
    
    /**
     * Get allowed external hosts from configuration.
     * 
     * @return array
     */
    protected function getAllowedHosts(): array
    {
        return config('services.allowed_external_hosts', [
            // Payment gateways
            'api.paystack.co',
            'api.stripe.com',
            
            // Identity verification
            'api.sumsub.com',
            
            // Passport verification
            'aeropass.icao.int',
            
            // Government APIs (examples - configure actual endpoints)
            'api.state.gov',           // US State Department
            'api.gov.uk',              // UK Government
            'api.immigration.gov.ng',  // Nigeria Immigration
            
            // GCB Bank (configure actual domain)
            'api.gcb.com.gh',
            'uat.gcb.com.gh',
            
            // Add other trusted external services as needed
        ]);
    }
    
    /**
     * Check if host resolves to private IP range.
     * 
     * Prevents SSRF via DNS rebinding attacks where attacker controls
     * DNS to resolve public domain to private IP.
     * 
     * @param string $host
     * @return bool
     */
    protected function isPrivateIp(string $host): bool
    {
        // Skip IP check for known safe domains (performance optimization)
        $trustedDomains = [
            'api.paystack.co',
            'api.stripe.com',
            'api.sumsub.com',
            'aeropass.icao.int',
        ];
        
        if (in_array($host, $trustedDomains)) {
            return false;
        }
        
        // Resolve hostname to IP
        $ip = gethostbyname($host);
        
        // If resolution failed, gethostbyname returns the hostname
        if ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
            // DNS resolution failed - block for security
            return true;
        }
        
        // Check if IP is in private ranges
        return $this->isPrivateIpAddress($ip);
    }
    
    /**
     * Check if IP address is in private ranges.
     * 
     * Private ranges (RFC 1918, RFC 4193, loopback):
     * - 10.0.0.0/8
     * - 172.16.0.0/12
     * - 192.168.0.0/16
     * - 127.0.0.0/8 (loopback)
     * - ::1 (IPv6 loopback)
     * - fc00::/7 (IPv6 private)
     * 
     * @param string $ip
     * @return bool
     */
    protected function isPrivateIpAddress(string $ip): bool
    {
        // Use PHP's built-in filter for private/reserved ranges
        return !filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );
    }
    
    /**
     * Validate webhook URL (stricter validation for user-supplied URLs).
     * 
     * @param string $url
     * @throws UnauthorizedExternalRequestException
     */
    public function validateWebhookUrl(string $url): void
    {
        // First run standard validation
        $this->validateExternalUrl($url);
        
        // Additional webhook-specific checks
        $parsed = parse_url($url);
        
        // Webhook URLs should not have query parameters (security best practice)
        if (isset($parsed['query']) && !empty($parsed['query'])) {
            throw new UnauthorizedExternalRequestException("Webhook URLs should not contain query parameters");
        }
        
        // Webhook URLs should not have fragments
        if (isset($parsed['fragment']) && !empty($parsed['fragment'])) {
            throw new UnauthorizedExternalRequestException("Webhook URLs should not contain fragments");
        }
        
        // Path should be reasonable (not too long, no suspicious patterns)
        $path = $parsed['path'] ?? '/';
        if (strlen($path) > 200) {
            throw new UnauthorizedExternalRequestException("Webhook URL path too long");
        }
        
        // Block suspicious patterns in path
        $suspiciousPatterns = [
            '/admin',
            '/internal',
            '/private',
            '/.env',
            '/config',
            '/debug',
        ];
        
        foreach ($suspiciousPatterns as $pattern) {
            if (str_contains(strtolower($path), $pattern)) {
                throw new UnauthorizedExternalRequestException("Suspicious pattern in webhook URL path");
            }
        }
    }
    
    /**
     * Get validation requirements for setup documentation.
     * 
     * @return array
     */
    public function getValidationRequirements(): array
    {
        return [
            'purpose' => 'Prevent SSRF (Server-Side Request Forgery) attacks',
            'security_measures' => [
                'allowlist_validation' => 'Only pre-approved external hosts are allowed',
                'https_only' => 'All external requests must use HTTPS',
                'private_ip_blocking' => 'Blocks private IP ranges (RFC 1918, RFC 4193)',
                'dns_rebinding_protection' => 'Resolves hostnames to check for private IPs',
                'webhook_validation' => 'Stricter validation for user-supplied webhook URLs',
            ],
            'configuration' => [
                'file' => 'config/services.php',
                'key' => 'allowed_external_hosts',
                'description' => 'Array of allowed external hostnames',
            ],
            'allowed_hosts' => $this->getAllowedHosts(),
            'blocked_ranges' => [
                '10.0.0.0/8' => 'Private network (RFC 1918)',
                '172.16.0.0/12' => 'Private network (RFC 1918)',
                '192.168.0.0/16' => 'Private network (RFC 1918)',
                '127.0.0.0/8' => 'Loopback addresses',
                '::1' => 'IPv6 loopback',
                'fc00::/7' => 'IPv6 private addresses',
            ],
            'usage_examples' => [
                'service_validation' => 'app(ExternalUrlValidator::class)->validateExternalUrl($url)',
                'webhook_validation' => 'app(ExternalUrlValidator::class)->validateWebhookUrl($webhookUrl)',
            ],
        ];
    }
}