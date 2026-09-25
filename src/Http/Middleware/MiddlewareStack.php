<?php

declare(strict_types=1);

namespace Horizon\Http\Middleware;

use Horizon\Contracts\Foundation\ApplicationInterface;
use Horizon\Contracts\Http\MiddlewareInterface;

class MiddlewareStack
{
    /**
     * The application instance.
     */
    protected ApplicationInterface $app;

    /**
     * The middleware stack.
     */
    protected array $middleware = [];

    /**
     * The middleware groups.
     */
    protected array $middlewareGroups = [];

    /**
     * The middleware aliases.
     */
    protected array $middlewareAliases = [];

    /**
     * The middleware priority.
     */
    protected array $middlewarePriority = [
        \Horizon\Http\Middleware\HandleCors::class,
        \Horizon\Http\Middleware\TrimStrings::class,
        \Horizon\Http\Middleware\ConvertEmptyStringsToNull::class,
        \Horizon\Http\Middleware\ThrottleRequests::class,
        \Horizon\Http\Middleware\VerifyCsrfToken::class,
    ];

    /**
     * Create a new middleware stack instance.
     */
    public function __construct(ApplicationInterface $app)
    {
        $this->app = $app;
    }

    /**
     * Add middleware to the stack.
     */
    public function push(string $middleware): static
    {
        if (!in_array($middleware, $this->middleware)) {
            $this->middleware[] = $middleware;
        }

        return $this;
    }

    /**
     * Prepend middleware to the stack.
     */
    public function prepend(string $middleware): static
    {
        if (!in_array($middleware, $this->middleware)) {
            array_unshift($this->middleware, $middleware);
        }

        return $this;
    }

    /**
     * Remove middleware from the stack.
     */
    public function remove(string $middleware): static
    {
        $this->middleware = array_values(
            array_filter($this->middleware, function ($m) use ($middleware) {
                return $m !== $middleware;
            })
        );

        return $this;
    }

    /**
     * Define a middleware group.
     */
    public function group(string $name, array $middleware): static
    {
        $this->middlewareGroups[$name] = $middleware;

        return $this;
    }

    /**
     * Define a middleware alias.
     */
    public function alias(string $alias, string $middleware): static
    {
        $this->middlewareAliases[$alias] = $middleware;

        return $this;
    }

    /**
     * Get the middleware stack.
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Get the middleware groups.
     */
    public function getMiddlewareGroups(): array
    {
        return $this->middlewareGroups;
    }

    /**
     * Get the middleware aliases.
     */
    public function getMiddlewareAliases(): array
    {
        return $this->middlewareAliases;
    }

    /**
     * Resolve middleware stack for execution.
     */
    public function resolve(array $middleware): array
    {
        $resolved = [];

        foreach ($middleware as $name) {
            if (isset($this->middlewareAliases[$name])) {
                $name = $this->middlewareAliases[$name];
            }

            if (isset($this->middlewareGroups[$name])) {
                $resolved = array_merge($resolved, $this->resolve($this->middlewareGroups[$name]));
            } else {
                $resolved[] = $name;
            }
        }

        return $this->sortMiddleware($resolved);
    }

    /**
     * Sort middleware by priority.
     */
    protected function sortMiddleware(array $middleware): array
    {
        return array_values(array_unique($middleware));
    }

    /**
     * Get middleware instance.
     */
    public function make(string $middleware): MiddlewareInterface
    {
        return $this->app->make($middleware);
    }
}