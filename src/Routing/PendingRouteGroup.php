<?php

declare(strict_types=1);

namespace Reno\Routing;

use Closure;

class PendingRouteGroup
{
    protected Router $router;
    protected array $attributes;

    public function __construct(Router $router, array $attributes = [])
    {
        $this->router = $router;
        $this->attributes = $attributes;
    }

    /**
     * Set the route group prefix.
     */
    public function prefix(string $prefix): static
    {
        $this->attributes['prefix'] = $prefix;

        return $this;
    }

    /**
     * Set the route group middleware.
     */
    public function middleware(string|array $middleware): static
    {
        $this->attributes['middleware'] = $middleware;

        return $this;
    }

    /**
     * Set the route group namespace.
     */
    public function namespace(string $namespace): static
    {
        $this->attributes['namespace'] = $namespace;

        return $this;
    }

    /**
     * Set the route group domain.
     */
    public function domain(string $domain): static
    {
        $this->attributes['domain'] = $domain;

        return $this;
    }

    /**
     * Set the route group name prefix.
     */
    public function name(string $name): static
    {
        $this->attributes['as'] = $name;

        return $this;
    }

    /**
     * Add where constraints to the route group.
     */
    public function where(array|string $name, ?string $expression = null): static
    {
        if (!isset($this->attributes['where'])) {
            $this->attributes['where'] = [];
        }

        if (is_array($name)) {
            $this->attributes['where'] = array_merge($this->attributes['where'], $name);
        } else {
            $this->attributes['where'][$name] = $expression;
        }

        return $this;
    }

    /**
     * Register the route group.
     */
    public function group(Closure $callback): void
    {
        $this->router->group($this->attributes, $callback);
    }

    /**
     * Handle dynamic method calls for common group methods.
     */
    public function __call(string $method, array $parameters): mixed
    {
        if (method_exists($this->router, $method)) {
            // If it's a route registration method, register the group first
            if (in_array($method, ['get', 'post', 'put', 'patch', 'delete', 'options', 'any', 'match'])) {
                return $this->router->group($this->attributes, function (Router $router) use ($method, $parameters) {
                    return $router->{$method}(...$parameters);
                });
            }

            return $this->router->{$method}(...$parameters);
        }

        throw new \BadMethodCallException("Method {$method} does not exist.");
    }
}