<?php

declare(strict_types=1);

namespace Horizon\Routing\Exceptions;

use Exception;
use Throwable;

/**
 * MethodNotAllowedException
 * 
 * Thrown when a route exists for the URI but not for the HTTP method.
 * Corresponds to HTTP 405 Method Not Allowed.
 */
class MethodNotAllowedException extends Exception
{
    /**
     * Allowed HTTP methods.
     */
    protected array $allowedMethods;

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
     * Create a new MethodNotAllowedException.
     */
    public function __construct(
        array $allowedMethods = [],
        string $method = '',
        string $uri = '',
        string $message = 'Method not allowed',
        array $context = [],
        int $code = 405,
        ?Throwable $previous = null
    ) {
        $this->allowedMethods = array_map('strtoupper', $allowedMethods);
        $this->method = strtoupper($method);
        $this->uri = $uri;
        $this->context = $context;

        // Build descriptive message if not provided
        if ($message === 'Method not allowed' && $method && $uri) {
            $allowed = implode(', ', $this->allowedMethods);
            $message = "Method {$method} not allowed for {$uri}. Allowed methods: {$allowed}";
        }

        parent::__construct($message, $code, $previous);
    }

    /**
     * Create exception for specific request.
     */
    public static function forRequest(string $method, string $uri, array $allowedMethods, array $context = []): static
    {
        return new static($allowedMethods, $method, $uri, context: $context);
    }

    /**
     * Get allowed methods.
     */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
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
        return 405;
    }

    /**
     * Get Allow header value.
     */
    public function getAllowHeader(): string
    {
        return implode(', ', $this->allowedMethods);
    }

    /**
     * Check if method is allowed.
     */
    public function isMethodAllowed(string $method): bool
    {
        return in_array(strtoupper($method), $this->allowedMethods, true);
    }

    /**
     * Get suggested method (closest match).
     */
    public function getSuggestedMethod(): ?string
    {
        if (empty($this->allowedMethods)) {
            return null;
        }

        // If GET is requested and POST is allowed (or vice versa), suggest the alternative
        if ($this->method === 'GET' && in_array('POST', $this->allowedMethods)) {
            return 'POST';
        }

        if ($this->method === 'POST' && in_array('GET', $this->allowedMethods)) {
            return 'GET';
        }

        // Return the first allowed method as fallback
        return $this->allowedMethods[0];
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
            'allowed_methods' => $this->allowedMethods,
            'allow_header' => $this->getAllowHeader(),
            'suggested_method' => $this->getSuggestedMethod(),
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
        $message = "Method not allowed: {$this->method} {$this->uri}\n\n";
        
        $message .= "Allowed methods for this route:\n";
        foreach ($this->allowedMethods as $method) {
            $message .= "  - {$method}\n";
        }
        $message .= "\n";

        if ($suggested = $this->getSuggestedMethod()) {
            $message .= "Did you mean: {$suggested} {$this->uri}?\n\n";
        }

        $message .= "Possible solutions:\n";
        $message .= "1. Change the HTTP method to one of the allowed methods\n";
        $message .= "2. Register the route with the {$this->method} method\n";
        $message .= "3. Use Route::any() or Route::match() for multiple methods\n";
        $message .= "4. Check if you're using the correct HTTP verb for the action\n";

        return $message;
    }

    /**
     * Get HTTP headers for response.
     */
    public function getHeaders(): array
    {
        return [
            'Allow' => $this->getAllowHeader(),
        ];
    }

    /**
     * Convert exception to string.
     */
    public function __toString(): string
    {
        return $this->getDeveloperMessage();
    }
}