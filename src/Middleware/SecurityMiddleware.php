<?php

declare(strict_types=1);

namespace Horizon\Middleware;

use Horizon\Http\Request;
use Horizon\Http\Response;
use Horizon\Middleware\MiddlewareInterface;
use Closure;

/**
 * SecurityMiddleware
 * 
 * Adds security headers to responses to protect against common attacks.
 * Configurable security policies with secure defaults.
 */
class SecurityMiddleware implements MiddlewareInterface
{
    /**
     * Security configuration.
     */
    protected array $config;

    /**
     * Default security configuration.
     */
    protected static array $defaults = [
        'content_type_options' => 'nosniff',
        'frame_options' => 'DENY',
        'xss_protection' => '1; mode=block',
        'referrer_policy' => 'strict-origin-when-cross-origin',
        'content_security_policy' => null,
        'strict_transport_security' => null,
        'feature_policy' => null,
        'permissions_policy' => null,
        'cross_origin_embedder_policy' => null,
        'cross_origin_opener_policy' => null,
        'cross_origin_resource_policy' => null,
    ];

    /**
     * Create a new SecurityMiddleware instance.
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge(self::$defaults, $config);
    }

    /**
     * Handle the request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        
        return $this->addSecurityHeaders($response);
    }

    /**
     * Add security headers to the response.
     */
    protected function addSecurityHeaders(Response $response): Response
    {
        // X-Content-Type-Options
        if ($this->config['content_type_options']) {
            $response->header('X-Content-Type-Options', $this->config['content_type_options']);
        }

        // X-Frame-Options
        if ($this->config['frame_options']) {
            $response->header('X-Frame-Options', $this->config['frame_options']);
        }

        // X-XSS-Protection
        if ($this->config['xss_protection']) {
            $response->header('X-XSS-Protection', $this->config['xss_protection']);
        }

        // Referrer-Policy
        if ($this->config['referrer_policy']) {
            $response->header('Referrer-Policy', $this->config['referrer_policy']);
        }

        // Content-Security-Policy
        if ($this->config['content_security_policy']) {
            $response->header('Content-Security-Policy', $this->config['content_security_policy']);
        }

        // Strict-Transport-Security (HTTPS only)
        if ($this->config['strict_transport_security'] && $this->isSecureRequest()) {
            $response->header('Strict-Transport-Security', $this->config['strict_transport_security']);
        }

        // Feature-Policy (deprecated but still used)
        if ($this->config['feature_policy']) {
            $response->header('Feature-Policy', $this->config['feature_policy']);
        }

        // Permissions-Policy (modern replacement for Feature-Policy)
        if ($this->config['permissions_policy']) {
            $response->header('Permissions-Policy', $this->config['permissions_policy']);
        }

        // Cross-Origin-Embedder-Policy
        if ($this->config['cross_origin_embedder_policy']) {
            $response->header('Cross-Origin-Embedder-Policy', $this->config['cross_origin_embedder_policy']);
        }

        // Cross-Origin-Opener-Policy
        if ($this->config['cross_origin_opener_policy']) {
            $response->header('Cross-Origin-Opener-Policy', $this->config['cross_origin_opener_policy']);
        }

        // Cross-Origin-Resource-Policy
        if ($this->config['cross_origin_resource_policy']) {
            $response->header('Cross-Origin-Resource-Policy', $this->config['cross_origin_resource_policy']);
        }

        return $response;
    }

    /**
     * Check if the current request is secure (HTTPS).
     */
    protected function isSecureRequest(): bool
    {
        // In a real implementation, this would check the actual request
        return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    }

    /**
     * Create security middleware with basic configuration.
     */
    public static function basic(): static
    {
        return new static([
            'content_type_options' => 'nosniff',
            'frame_options' => 'SAMEORIGIN',
            'xss_protection' => '1; mode=block',
            'referrer_policy' => 'strict-origin-when-cross-origin',
        ]);
    }

    /**
     * Create security middleware with strict configuration.
     */
    public static function strict(): static
    {
        return new static([
            'content_type_options' => 'nosniff',
            'frame_options' => 'DENY',
            'xss_protection' => '1; mode=block',
            'referrer_policy' => 'strict-origin-when-cross-origin',
            'content_security_policy' => "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'",
            'strict_transport_security' => 'max-age=31536000; includeSubDomains',
            'cross_origin_embedder_policy' => 'require-corp',
            'cross_origin_opener_policy' => 'same-origin',
            'cross_origin_resource_policy' => 'same-origin',
        ]);
    }

    /**
     * Create security middleware for API endpoints.
     */
    public static function api(): static
    {
        return new static([
            'content_type_options' => 'nosniff',
            'frame_options' => 'DENY',
            'referrer_policy' => 'strict-origin-when-cross-origin',
            'cross_origin_resource_policy' => 'cross-origin',
        ]);
    }

    /**
     * Create security middleware with custom CSP.
     */
    public static function withCsp(string $csp): static
    {
        return new static([
            'content_security_policy' => $csp,
        ]);
    }

    /**
     * Create security middleware with HSTS.
     */
    public static function withHsts(string $hsts = 'max-age=31536000; includeSubDomains'): static
    {
        return new static([
            'strict_transport_security' => $hsts,
        ]);
    }
}