<?php

declare(strict_types=1);

namespace Reno\Middleware;

use Reno\Http\Request;
use Reno\Http\Response;
use Reno\Middleware\MiddlewareInterface;
use Closure;

/**
 * CorsMiddleware
 * 
 * Handles Cross-Origin Resource Sharing (CORS) headers.
 * Configurable origins, methods, headers, and credentials support.
 */
class CorsMiddleware implements MiddlewareInterface
{
    /**
     * Allowed origins.
     */
    protected array $allowedOrigins;

    /**
     * Allowed methods.
     */
    protected array $allowedMethods;

    /**
     * Allowed headers.
     */
    protected array $allowedHeaders;

    /**
     * Exposed headers.
     */
    protected array $exposedHeaders;

    /**
     * Max age for preflight cache.
     */
    protected int $maxAge;

    /**
     * Allow credentials.
     */
    protected bool $allowCredentials;

    /**
     * Create a new CorsMiddleware instance.
     */
    public function __construct(array $config = [])
    {
        $this->allowedOrigins = $config['origins'] ?? ['*'];
        $this->allowedMethods = $config['methods'] ?? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
        $this->allowedHeaders = $config['headers'] ?? ['*'];
        $this->exposedHeaders = $config['exposed_headers'] ?? [];
        $this->maxAge = $config['max_age'] ?? 86400; // 24 hours
        $this->allowCredentials = $config['credentials'] ?? false;
    }

    /**
     * Handle the request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Handle preflight OPTIONS request
        if ($request->isOptions() && $this->isPreflightRequest($request)) {
            return $this->handlePreflightRequest($request);
        }

        // Process the request
        $response = $next($request);

        // Add CORS headers to the response
        return $this->addCorsHeaders($request, $response);
    }

    /**
     * Check if request is a preflight request.
     */
    protected function isPreflightRequest(Request $request): bool
    {
        return $request->hasHeader('Access-Control-Request-Method');
    }

    /**
     * Handle preflight OPTIONS request.
     */
    protected function handlePreflightRequest(Request $request): Response
    {
        $response = new Response('', 204);

        $origin = $request->header('Origin');
        if (!$this->isOriginAllowed($origin)) {
            return $response;
        }

        $method = $request->header('Access-Control-Request-Method');
        if (!$this->isMethodAllowed($method)) {
            return $response;
        }

        $headers = $request->header('Access-Control-Request-Headers');
        if ($headers && !$this->areHeadersAllowed($headers)) {
            return $response;
        }

        return $this->addPreflightHeaders($request, $response);
    }

    /**
     * Add CORS headers to response.
     */
    protected function addCorsHeaders(Request $request, Response $response): Response
    {
        $origin = $request->header('Origin');

        if ($this->isOriginAllowed($origin)) {
            $response->header('Access-Control-Allow-Origin', $this->getAllowedOrigin($origin));

            if ($this->allowCredentials) {
                $response->header('Access-Control-Allow-Credentials', 'true');
            }

            if (!empty($this->exposedHeaders)) {
                $response->header('Access-Control-Expose-Headers', implode(', ', $this->exposedHeaders));
            }

            // Add Vary header to indicate that the response varies based on Origin
            $response->header('Vary', 'Origin');
        }

        return $response;
    }

    /**
     * Add preflight headers to response.
     */
    protected function addPreflightHeaders(Request $request, Response $response): Response
    {
        $origin = $request->header('Origin');

        $response->header('Access-Control-Allow-Origin', $this->getAllowedOrigin($origin));
        $response->header('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods));
        $response->header('Access-Control-Max-Age', (string) $this->maxAge);

        if (!empty($this->allowedHeaders)) {
            $headers = in_array('*', $this->allowedHeaders) 
                ? $request->header('Access-Control-Request-Headers') 
                : implode(', ', $this->allowedHeaders);
            
            if ($headers) {
                $response->header('Access-Control-Allow-Headers', $headers);
            }
        }

        if ($this->allowCredentials) {
            $response->header('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    /**
     * Check if origin is allowed.
     */
    protected function isOriginAllowed(?string $origin): bool
    {
        if (empty($origin)) {
            return false;
        }

        if (in_array('*', $this->allowedOrigins)) {
            return true;
        }

        return in_array($origin, $this->allowedOrigins);
    }

    /**
     * Check if method is allowed.
     */
    protected function isMethodAllowed(?string $method): bool
    {
        if (empty($method)) {
            return false;
        }

        return in_array(strtoupper($method), $this->allowedMethods);
    }

    /**
     * Check if headers are allowed.
     */
    protected function areHeadersAllowed(?string $headers): bool
    {
        if (empty($headers)) {
            return true;
        }

        if (in_array('*', $this->allowedHeaders)) {
            return true;
        }

        $requestedHeaders = array_map('trim', explode(',', strtolower($headers)));
        $allowedHeaders = array_map('strtolower', $this->allowedHeaders);

        return empty(array_diff($requestedHeaders, $allowedHeaders));
    }

    /**
     * Get allowed origin value for header.
     */
    protected function getAllowedOrigin(?string $origin): string
    {
        if (in_array('*', $this->allowedOrigins) && !$this->allowCredentials) {
            return '*';
        }

        return $origin ?? '';
    }

    /**
     * Create CORS middleware with specific configuration.
     */
    public static function make(array $config = []): static
    {
        return new static($config);
    }

    /**
     * Create permissive CORS middleware (for development).
     */
    public static function permissive(): static
    {
        return new static([
            'origins' => ['*'],
            'methods' => ['*'],
            'headers' => ['*'],
            'credentials' => false,
        ]);
    }

    /**
     * Create restrictive CORS middleware (for production).
     */
    public static function restrictive(array $allowedOrigins): static
    {
        return new static([
            'origins' => $allowedOrigins,
            'methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
            'headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
            'credentials' => true,
        ]);
    }
}