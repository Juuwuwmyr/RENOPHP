<?php

declare(strict_types=1);

namespace Horizon\Http\Controllers;

class MiddlewareDefinition
{
    /**
     * The middleware name(s).
     */
    protected string|array $middleware;

    /**
     * The middleware options.
     */
    protected array $options;

    /**
     * Create a new middleware definition.
     */
    public function __construct(string|array $middleware, array $options = [])
    {
        $this->middleware = $middleware;
        $this->options = $options;
    }

    /**
     * Set the methods the middleware should apply to.
     */
    public function only(string|array $methods): static
    {
        $this->options['only'] = is_array($methods) ? $methods : func_get_args();

        return $this;
    }

    /**
     * Set the methods the middleware should exclude.
     */
    public function except(string|array $methods): static
    {
        $this->options['except'] = is_array($methods) ? $methods : func_get_args();

        return $this;
    }

    /**
     * Get the middleware name(s).
     */
    public function getMiddleware(): string|array
    {
        return $this->middleware;
    }

    /**
     * Get the middleware options.
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Determine if the middleware applies to the given method.
     */
    public function appliesTo(string $method): bool
    {
        if (isset($this->options['only'])) {
            return in_array($method, (array) $this->options['only'], true);
        }

        if (isset($this->options['except'])) {
            return !in_array($method, (array) $this->options['except'], true);
        }

        return true;
    }

    /**
     * Get the middleware that applies to the given method.
     */
    public function getMiddlewareForMethod(string $method): array
    {
        if (!$this->appliesTo($method)) {
            return [];
        }

        return is_array($this->middleware) ? $this->middleware : [$this->middleware];
    }
}