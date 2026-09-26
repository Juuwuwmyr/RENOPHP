<?php

declare(strict_types=1);

namespace Horizon\Routing\Exceptions;

use Exception;
use Throwable;

/**
 * RouteNotFoundException
 * 
 * Thrown when no route matches the current request.
 * Corresponds to HTTP 404 Not Found.
 */
class RouteNotFoundException extends Exception
{
    /**
     * Request method.
     */
    protected string $method;

    /**
     * Request URI.
     */
    protected string $uri;

    /**
     * Additional context data.
     */
    protected array $context = [];

    /**
     * Create a new RouteNotFoundException.
     */
    public function __construct(
        string $message = 'Route not found',
        string $method = '',
        string $uri = '',
        array $context = [],
        int $code = 404,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        
        $this->method = $method;
        $this->uri = $uri;
        $this->context = $context;
    }

    /**
     * Create exception for specific request.
     */
    public static function forRequest(string $method, string $uri, array $context = []): static
    {
        $message = "No route found for {$method} {$uri}";
        
        return new static($message, $method, $uri, $context);
    }

    /**
     * Get request method.
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Get request URI.
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Get context data.
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Set context data.
     */
    public function setContext(array $context): static
    {
        $this->context = $context;
        return $this;
    }

    /**
     * Add context item.
     */
    public function addContext(string $key, mixed $value): static
    {
        $this->context[$key] = $value;
        return $this;
    }

    /**
     * Get HTTP status code.
     */
    public function getStatusCode(): int
    {
        return 404;
    }

    /**
     * Get suggested routes (for debugging).
     */
    public function getSuggestedRoutes(): array
    {
        return $this->context['suggested_routes'] ?? [];
    }

    /**
     * Set suggested routes.
     */
    public function setSuggestedRoutes(array $routes): static
    {
        $this->context['suggested_routes'] = $routes;
        return $this;
    }

    /**
     * Convert to array for logging/debugging.
     */
    public function toArray(): array
    {
        return [
            'exception' => static::class,
            'message' => $this->getMessage(),
            'method' => $this->method,
            'uri' => $this->uri,
            'context' => $this->context,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'trace' => $this->getTraceAsString(),
        ];
    }

    /**
     * Generate developer-friendly error message.
     */
    public function getDeveloperMessage(): string
    {
        $message = "Route not found: {$this->method} {$this->uri}\n\n";
        
        if (!empty($this->context['suggested_routes'])) {
            $message .= "Similar routes:\n";
            foreach ($this->context['suggested_routes'] as $route) {
                $message .= "  - {$route}\n";
            }
            $message .= "\n";
        }

        $message .= "Possible solutions:\n";
        $message .= "1. Check if the route is registered\n";
        $message .= "2. Verify the HTTP method matches\n";
        $message .= "3. Check route parameter constraints\n";
        $message .= "4. Ensure middleware isn't blocking the route\n";

        return $message;
    }

    /**
     * Convert exception to string.
     */
    public function __toString(): string
    {
        return $this->getDeveloperMessage();
    }
}