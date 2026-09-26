<?php

declare(strict_types=1);

namespace Horizon\Middleware;

use Horizon\Http\Request;
use Horizon\Http\Response;
use Horizon\Middleware\Pipeline;
use Horizon\Middleware\MiddlewareInterface;
use Closure;
use InvalidArgumentException;

/**
 * MiddlewareManager
 * 
 * Manages middleware registration, resolution, and execution.
 * Supports global middleware, route middleware, middleware groups, and aliases.
 */
class MiddlewareManager
{
    /**
     * Global middleware that runs on every request.
     */
    protected array $globalMiddleware = [];

    /**
     * Route-specific middleware.
     */
    protected array $routeMiddleware = [];

    /**
     * Middleware groups.
     */
    protected array $middlewareGroups = [];

    /**
     * Middleware aliases.
     */
    protected array $middlewareAliases = [];

    /**
     * Middleware priority order.
     */
    protected array $middlewarePriority = [];

    /**
     * Middleware resolver function.
     */
    protected ?Closure $resolver = null;

    /**
     * Create a new MiddlewareManager instance.
     */
    public function __construct(?Closure $resolver = null)
    {
        $this->resolver = $resolver;
        $this->setDefaultPriority();
    }

    // ====================================================================
    // Global Middleware
    // ====================================================================

    /**
     * Add global middleware.
     */
    public function global(string|array $middleware): void
    {
        $middleware = is_array($middleware) ? $middleware : [$middleware];
        $this->globalMiddleware = array_merge($this->globalMiddleware, $middleware);
    }

    /**
     * Prepend global middleware.
     */
    public function prependGlobal(string|array $middleware): void
    {
        $middleware = is_array($middleware) ? $middleware : [$middleware];
        $this->globalMiddleware = array_merge($middleware, $this->globalMiddleware);
    }

    /**
     * Get global middleware.
     */
    public function getGlobal(): array
    {
        return $this->globalMiddleware;
    }

    /**
     * Clear global middleware.
     */
    public function clearGlobal(): void
    {
        $this->globalMiddleware = [];
    }

    // ====================================================================
    // Route Middleware
    // ====================================================================

    /**
     * Register route middleware.
     */
    public function route(string $name, string $class): void
    {
        $this->routeMiddleware[$name] = $class;
    }

    /**
     * Register multiple route middleware.
     */
    public function routes(array $middleware): void
    {
        foreach ($middleware as $name => $class) {
            $this->route($name, $class);
        }
    }

    /**
     * Get route middleware.
     */
    public function getRoute(string $name): ?string
    {
        return $this->routeMiddleware[$name] ?? null;
    }

    /**
     * Get all route middleware.
     */
    public function getRoutes(): array
    {
        return $this->routeMiddleware;
    }

    /**
     * Check if route middleware exists.
     */
    public function hasRoute(string $name): bool
    {
        return isset($this->routeMiddleware[$name]);
    }

    // ====================================================================
    // Middleware Groups
    // ====================================================================

    /**
     * Register middleware group.
     */
    public function group(string $name, array $middleware): void
    {
        $this->middlewareGroups[$name] = $middleware;
    }

    /**
     * Add middleware to existing group.
     */
    public function addToGroup(string $group, string|array $middleware): void
    {
        if (!isset($this->middlewareGroups[$group])) {
            $this->middlewareGroups[$group] = [];
        }

        $middleware = is_array($middleware) ? $middleware : [$middleware];
        $this->middlewareGroups[$group] = array_merge($this->middlewareGroups[$group], $middleware);
    }

    /**
     * Get middleware group.
     */
    public function getGroup(string $name): array
    {
        return $this->middlewareGroups[$name] ?? [];
    }

    /**
     * Get all middleware groups.
     */
    public function getGroups(): array
    {
        return $this->middlewareGroups;
    }

    /**
     * Check if middleware group exists.
     */
    public function hasGroup(string $name): bool
    {
        return isset($this->middlewareGroups[$name]);
    }

    // ====================================================================
    // Middleware Aliases
    // ====================================================================

    /**
     * Register middleware alias.
     */
    public function alias(string $alias, string $class): void
    {
        $this->middlewareAliases[$alias] = $class;
    }

    /**
     * Register multiple middleware aliases.
     */
    public function aliases(array $aliases): void
    {
        $this->middlewareAliases = array_merge($this->middlewareAliases, $aliases);
    }

    /**
     * Get middleware alias.
     */
    public function getAlias(string $alias): ?string
    {
        return $this->middlewareAliases[$alias] ?? null;
    }

    /**
     * Get all aliases.
     */
    public function getAliases(): array
    {
        return $this->middlewareAliases;
    }

    // ====================================================================
    // Middleware Priority
    // ====================================================================

    /**
     * Set middleware priority order.
     */
    public function priority(array $priority): void
    {
        $this->middlewarePriority = $priority;
    }

    /**
     * Get middleware priority.
     */
    public function getPriority(): array
    {
        return $this->middlewarePriority;
    }

    /**
     * Sort middleware by priority.
     */
    public function sortByPriority(array $middleware): array
    {
        if (empty($this->middlewarePriority)) {
            return $middleware;
        }

        $priorityMap = array_flip($this->middlewarePriority);
        
        usort($middleware, function ($a, $b) use ($priorityMap) {
            $aName = $this->extractMiddlewareName($a);
            $bName = $this->extractMiddlewareName($b);
            
            $aPriority = $priorityMap[$aName] ?? 999;
            $bPriority = $priorityMap[$bName] ?? 999;
            
            return $aPriority <=> $bPriority;
        });

        return $middleware;
    }

    /**
     * Set default middleware priority.
     */
    protected function setDefaultPriority(): void
    {
        $this->middlewarePriority = [
            'cors',
            'security',
            'throttle',
            'auth',
            'verified',
            'admin',
            'cache',
            'compress',
        ];
    }

    // ====================================================================
    // Middleware Resolution
    // ====================================================================

    /**
     * Resolve middleware array to concrete instances.
     */
    public function resolve(array $middleware): array
    {
        $resolved = [];

        foreach ($middleware as $middlewareItem) {
            $resolved = array_merge($resolved, $this->resolveMiddleware($middlewareItem));
        }

        return $this->sortByPriority($resolved);
    }

    /**
     * Resolve single middleware item.
     */
    protected function resolveMiddleware(string $middleware): array
    {
        // Check if it's a group
        if ($this->hasGroup($middleware)) {
            return $this->resolve($this->getGroup($middleware));
        }

        // Check if it's an alias
        if ($this->hasAlias($middleware)) {
            return [$this->getAlias($middleware)];
        }

        // Check if it's a route middleware
        if ($this->hasRoute($middleware)) {
            return [$this->getRoute($middleware)];
        }

        // Return as-is (assume it's a class name)
        return [$middleware];
    }

    /**
     * Extract middleware name from string.
     */
    protected function extractMiddlewareName(string $middleware): string
    {
        // Handle middleware with parameters (e.g., "throttle:60,1")
        if (str_contains($middleware, ':')) {
            return explode(':', $middleware, 2)[0];
        }

        return $middleware;
    }

    /**
     * Check if alias exists.
     */
    protected function hasAlias(string $alias): bool
    {
        return isset($this->middlewareAliases[$alias]);
    }

    // ====================================================================
    // Pipeline Execution
    // ====================================================================

    /**
     * Create middleware pipeline.
     */
    public function pipeline(): Pipeline
    {
        return new Pipeline($this->resolver);
    }

    /**
     * Execute middleware pipeline.
     */
    public function execute(Request $request, array $middleware, Closure $destination): Response
    {
        $resolvedMiddleware = $this->resolve($middleware);

        return $this->pipeline()
            ->send($request)
            ->through($resolvedMiddleware)
            ->then($destination);
    }

    /**
     * Execute global middleware pipeline.
     */
    public function executeGlobal(Request $request, Closure $destination): Response
    {
        return $this->execute($request, $this->globalMiddleware, $destination);
    }

    /**
     * Execute middleware for route.
     */
    public function executeForRoute(Request $request, array $routeMiddleware, Closure $destination): Response
    {
        $middleware = array_merge($this->globalMiddleware, $routeMiddleware);
        return $this->execute($request, $middleware, $destination);
    }

    // ====================================================================
    // Utility Methods
    // ====================================================================

    /**
     * Get all registered middleware.
     */
    public function getAllMiddleware(): array
    {
        return [
            'global' => $this->globalMiddleware,
            'route' => $this->routeMiddleware,
            'groups' => $this->middlewareGroups,
            'aliases' => $this->middlewareAliases,
        ];
    }

    /**
     * Clear all middleware.
     */
    public function clear(): void
    {
        $this->globalMiddleware = [];
        $this->routeMiddleware = [];
        $this->middlewareGroups = [];
        $this->middlewareAliases = [];
    }

    /**
     * Get middleware statistics.
     */
    public function getStats(): array
    {
        return [
            'global_count' => count($this->globalMiddleware),
            'route_count' => count($this->routeMiddleware),
            'group_count' => count($this->middlewareGroups),
            'alias_count' => count($this->middlewareAliases),
            'priority_count' => count($this->middlewarePriority),
        ];
    }

    /**
     * Set middleware resolver.
     */
    public function setResolver(Closure $resolver): void
    {
        $this->resolver = $resolver;
    }

    /**
     * Get middleware resolver.
     */
    public function getResolver(): ?Closure
    {
        return $this->resolver;
    }
}