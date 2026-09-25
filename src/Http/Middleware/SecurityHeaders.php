<?php

declare(strict_types=1);

namespace Horizon\Http\Middleware;

use Horizon\Contracts\Http\MiddlewareInterface;
use Horizon\Contracts\Http\RequestInterface;
use Horizon\Contracts\Http\ResponseInterface;

class SecurityHeaders implements MiddlewareInterface
{
    /**
     * The security headers configuration.
     */
    protected array $config;

    /**
     * Create a new security headers middleware instance.
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'x_content_type_options' => 'nosniff',
            'x_frame_options' => 'DENY',
            'x_xss_protection' => '1; mode=block',
            'referrer_policy' => 'strict-origin-when-cross-origin',
            'content_security_policy' => "default-src 'self'",
            'strict_transport_security' => 'max-age=31536000; includeSubDomains',
            'permissions_policy' => 'camera=(), microphone=(), geolocation=()',
        ], $config);
    }

    /**
     * Handle an incoming request.
     */
    public function handle(RequestInterface $request, \Closure $next): ResponseInterface
    {
        $response = $next($request);

        return $this->addSecurityHeaders($response, $request);
    }

    /**
     * Add security headers to the response.
     */
    protected function addSecurityHeaders(ResponseInterface $response, RequestInterface $request): ResponseInterface
    {
        // X-Content-Type-Options
        if ($this->config['x_content_type_options']) {
            $response->header('X-Content-Type-Options', $this->config['x_content_type_options']);
        }

        // X-Frame-Options
        if ($this->config['x_frame_options']) {
            $response->header('X-Frame-Options', $this->config['x_frame_options']);
        }

        // X-XSS-Protection
        if ($this->config['x_xss_protection']) {
            $response->header('X-XSS-Protection', $this->config['x_xss_protection']);
        }

        // Referrer-Policy
        if ($this->config['referrer_policy']) {
            $response->header('Referrer-Policy', $this->config['referrer_policy']);
        }

        // Content-Security-Policy
        if ($this->config['content_security_policy']) {
            $response->header('Content-Security-Policy', $this->config['content_security_policy']);
        }

        // Strict-Transport-Security (only for HTTPS)
        if ($request->secure() && $this->config['strict_transport_security']) {
            $response->header('Strict-Transport-Security', $this->config['strict_transport_security']);
        }

        // Permissions-Policy
        if ($this->config['permissions_policy']) {
            $response->header('Permissions-Policy', $this->config['permissions_policy']);
        }

        return $response;
    }
}