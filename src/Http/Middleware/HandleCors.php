<?php

declare(strict_types=1);

namespace Reno\Http\Middleware;

use Reno\Contracts\Http\MiddlewareInterface;
use Reno\Contracts\Http\RequestInterface;
use Reno\Contracts\Http\ResponseInterface;

class HandleCors implements MiddlewareInterface
{
    /**
     * The CORS configuration.
     */
    protected array $config;

    /**
     * Create a new CORS middleware instance.
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'allowed_origins' => ['*'],
            'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
            'allowed_headers' => ['*'],
            'exposed_headers' => [],
            'max_age' => 0,
            'supports_credentials' => false,
        ], $config);
    }

    /**
     * Handle an incoming request.
     */
    public function handle(RequestInterface $request, \Closure $next): ResponseInterface
    {
        // Handle preflight OPTIONS requests
        if ($request->method() === 'OPTIONS') {
            return $this->handlePreflightRequest($request);
        }

        $response = $next($request);

        return $this->addCorsHeaders($request, $response);
    }

    /**
     * Handle preflight OPTIONS requests.
     */
    protected function handlePreflightRequest(RequestInterface $request): ResponseInterface
    {
        $response = response('', 200);

        $this->addCorsHeaders($request, $response);

        if ($maxAge = $this->config['max_age']) {
            $response->header('Access-Control-Max-Age', (string) $maxAge);
        }

        return $response;
    }

    /**
     * Add CORS headers to the response.
     */
    protected function addCorsHeaders(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $origin = $request->header('Origin');

        if ($this->isOriginAllowed($origin)) {
            $response->header('Access-Control-Allow-Origin', $origin ?: '*');
        }

        if ($this->config['supports_credentials']) {
            $response->header('Access-Control-Allow-Credentials', 'true');
        }

        $response->header('Access-Control-Allow-Methods', implode(', ', $this->config['allowed_methods']));

        if ($allowedHeaders = $this->getAllowedHeaders($request)) {
            $response->header('Access-Control-Allow-Headers', $allowedHeaders);
        }

        if (!empty($this->config['exposed_headers'])) {
            $response->header('Access-Control-Expose-Headers', implode(', ', $this->config['exposed_headers']));
        }

        return $response;
    }

    /**
     * Check if the origin is allowed.
     */
    protected function isOriginAllowed(?string $origin): bool
    {
        if (in_array('*', $this->config['allowed_origins'])) {
            return true;
        }

        return $origin && in_array($origin, $this->config['allowed_origins']);
    }

    /**
     * Get the allowed headers for the request.
     */
    protected function getAllowedHeaders(RequestInterface $request): string
    {
        if (in_array('*', $this->config['allowed_headers'])) {
            $requestHeaders = $request->header('Access-Control-Request-Headers');
            return $requestHeaders ?: implode(', ', $this->config['allowed_headers']);
        }

        return implode(', ', $this->config['allowed_headers']);
    }
}