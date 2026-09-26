<?php

declare(strict_types=1);

namespace Horizon\Routing;

use Horizon\Routing\Router;
use Horizon\Routing\Route;
use Closure;
use InvalidArgumentException;

/**
 * RouteGroup
 * 
 * Advanced route grouping with nested groups, conditional registration,
 * and comprehensive attribute management.
 */
class RouteGroup
{
    /**
     * Group attributes.
     */
    protected array $attributes = [];

    /**
     * Parent router instance.
     */
    protected Router $router;

    /**
     * Parent group (for nested groups).
     */
    protected ?RouteGroup $parent = null;

    /**
     * Child groups.
     */
    protected array $children = [];

    /**
     * Registered routes in this group.
     */
    protected array $routes = [];

    /**
     * Group conditions.
     */
    protected array $conditions = [];

    /**
     * Create a new RouteGroup instance.
     */
    public function __construct(Router $router, array $attributes = [], ?RouteGroup $parent = null)
    {
        $this->router = $router;
        $this->attributes = $attributes;
        $this->parent = $parent;

        if ($parent) {
            $parent->addChild($this);
        }
    }

    // ====================================================================
    // Route Registration
    // ====================================================================

    /**
     * Register a GET route.
     */
    public function get(string $uri, mixed $action): Route
    {
        return $this->addRoute(['GET', 'HEAD'], $uri, $action);
    }

    /**
     * Register a POST route.
     */
    public function post(string $uri, mixed $action): Route
    {
        return $this->addRoute(['POST'], $uri, $action);
    }

    /**
     * Register a PUT route.
     */
    public function put(string $uri, mixed $action): Route
    {
        return $this->addRoute(['PUT'], $uri, $action);
    }

    /**
     * Register a PATCH route.
     */
    public function patch(string $uri, mixed $action): Route
    {
        return $this->addRoute(['PATCH'], $uri, $action);
    }

    /**
     * Register a DELETE route.
     */
    public function delete(string $uri, mixed $action): Route
    {
        return $this->addRoute(['DELETE'], $uri, $action);
    }

    /**
     * Register an OPTIONS route.
     */
    public function options(string $uri, mixed $action): Route
    {
        return $this->addRoute(['OPTIONS'], $uri, $action);
    }

    /**
     * Register a route for any HTTP method.
     */
    public function any(string $uri, mixed $action): Route
    {
        return $this->addRoute(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'], $uri, $action);
    }

    /**
     * Register a route for multiple HTTP methods.
     */
    public function match(array $methods, string $uri, mixed $action): Route
    {
        return $this->addRoute($methods, $uri, $action);
    }

    /**
     * Add a route to this group.
     */
    protected function addRoute(array $methods, string $uri, mixed $action): Route
    {
        $uri = $this->getFullUri($uri);
        $route = new Route($methods, $uri, $action);
        
        $this->applyGroupAttributes($route);
        $this->routes[] = $route;
        
        // Add to router's collection
        $this->router->getRoutes()->add($route);
        
        return $route;
    }

    // ====================================================================
    // Nested Groups
    // ====================================================================

    /**
     * Create a nested group.
     */
    public function group(array $attributes, Closure $callback): RouteGroup
    {
        $mergedAttributes = $this->mergeAttributes($this->attributes, $attributes);
        $group = new RouteGroup($this->router, $mergedAttributes, $this);
        
        $callback($group);
        
        return $group;
    }

    /**
     * Create a prefixed group.
     */
    public function prefix(string $prefix): static
    {
        $this->attributes['prefix'] = $this->attributes['prefix'] ?? '';
        $this->attributes['prefix'] = trim($this->attributes['prefix'] . '/' . $prefix, '/');
        
        return $this;
    }

    /**
     * Add middleware to group.
     */
    public function middleware(string|array $middleware): static
    {
        if (!isset($this->attributes['middleware'])) {
            $this->attributes['middleware'] = [];
        }

        $middleware = is_array($middleware) ? $middleware : [$middleware];
        $this->attributes['middleware'] = array_merge($this->attributes['middleware'], $middleware);
        
        return $this;
    }

    /**
     * Set namespace for group.
     */
    public function namespace(string $namespace): static
    {
        $this->attributes['namespace'] = $namespace;
        return $this;
    }

    /**
     * Set name prefix for group.
     */
    public function name(string $name): static
    {
        $this->attributes['name'] = ($this->attributes['name'] ?? '') . $name;
        return $this;
    }

    /**
     * Set domain constraint for group.
     */
    public function domain(string $domain): static
    {
        $this->attributes['domain'] = $domain;
        return $this;
    }

    /**
     * Add where constraints for group.
     */
    public function where(array $wheres): static
    {
        if (!isset($this->attributes['where'])) {
            $this->attributes['where'] = [];
        }

        $this->attributes['where'] = array_merge($this->attributes['where'], $wheres);
        return $this;
    }

    // ====================================================================
    // Resource Routes
    // ====================================================================

    /**
     * Register resource routes.
     */
    public function resource(string $name, string $controller, array $options = []): ResourceRouteRegistrar
    {
        $registrar = new ResourceRouteRegistrar($this, $name, $controller, $options);
        $registrar->register();
        
        return $registrar;
    }

    /**
     * Register API resource routes (without create/edit forms).
     */
    public function apiResource(string $name, string $controller, array $options = []): ResourceRouteRegistrar
    {
        $options['only'] = $options['only'] ?? ['index', 'store', 'show', 'update', 'destroy'];
        
        return $this->resource($name, $controller, $options);
    }

    /**
     * Register multiple resources.
     */
    public function resources(array $resources): void
    {
        foreach ($resources as $name => $controller) {
            if (is_array($controller)) {
                $this->resource($name, $controller[0], $controller[1] ?? []);
            } else {
                $this->resource($name, $controller);
            }
        }
    }

    /**
     * Register multiple API resources.
     */
    public function apiResources(array $resources): void
    {
        foreach ($resources as $name => $controller) {
            if (is_array($controller)) {
                $this->apiResource($name, $controller[0], $controller[1] ?? []);
            } else {
                $this->apiResource($name, $controller);
            }
        }
    }

    // ====================================================================
    // Conditional Registration
    // ====================================================================

    /**
     * Register routes conditionally.
     */
    public function when(Closure|bool $condition, Closure $callback): static
    {
        if (is_callable($condition)) {
            $shouldRegister = $condition($this);
        } else {
            $shouldRegister = $condition;
        }

        if ($shouldRegister) {
            $callback($this);
        }

        return $this;
    }

    /**
     * Register routes unless condition is true.
     */
    public function unless(Closure|bool $condition, Closure $callback): static
    {
        if (is_callable($condition)) {
            $shouldSkip = $condition($this);
        } else {
            $shouldSkip = $condition;
        }

        if (!$shouldSkip) {
            $callback($this);
        }

        return $this;
    }

    /**
     * Register routes for specific environments.
     */
    public function env(string|array $environments, Closure $callback): static
    {
        $environments = is_array($environments) ? $environments : [$environments];
        
        // In a real implementation, this would check the actual environment
        $currentEnv = $_ENV['APP_ENV'] ?? 'production';
        
        if (in_array($currentEnv, $environments)) {
            $callback($this);
        }

        return $this;
    }

    // ====================================================================
    // Specialized Groups
    // ====================================================================

    /**
     * Create an API group with common API settings.
     */
    public function api(Closure $callback, array $options = []): RouteGroup
    {
        $defaultOptions = [
            'prefix' => 'api',
            'middleware' => ['cors', 'throttle'],
        ];

        $mergedOptions = array_merge($defaultOptions, $options);
        
        return $this->group($mergedOptions, $callback);
    }

    /**
     * Create a web group with common web settings.
     */
    public function web(Closure $callback, array $options = []): RouteGroup
    {
        $defaultOptions = [
            'middleware' => ['web', 'csrf'],
        ];

        $mergedOptions = array_merge($defaultOptions, $options);
        
        return $this->group($mergedOptions, $callback);
    }

    /**
     * Create an admin group with admin-specific settings.
     */
    public function admin(Closure $callback, array $options = []): RouteGroup
    {
        $defaultOptions = [
            'prefix' => 'admin',
            'middleware' => ['auth', 'admin'],
            'name' => 'admin.',
        ];

        $mergedOptions = array_merge($defaultOptions, $options);
        
        return $this->group($mergedOptions, $callback);
    }

    /**
     * Create a versioned API group.
     */
    public function version(string $version, Closure $callback, array $options = []): RouteGroup
    {
        $defaultOptions = [
            'prefix' => "v{$version}",
            'name' => "v{$version}.",
        ];

        $mergedOptions = array_merge($defaultOptions, $options);
        
        return $this->group($mergedOptions, $callback);
    }

    // ====================================================================
    // Attribute Management
    // ====================================================================

    /**
     * Get full URI with group prefixes.
     */
    protected function getFullUri(string $uri): string
    {
        $prefix = $this->getFullPrefix();
        
        if ($prefix) {
            return '/' . trim($prefix . '/' . ltrim($uri, '/'), '/');
        }

        return '/' . ltrim($uri, '/');
    }

    /**
     * Get full prefix including parent groups.
     */
    protected function getFullPrefix(): string
    {
        $prefixes = [];
        $group = $this;

        while ($group) {
            if (isset($group->attributes['prefix']) && $group->attributes['prefix'] !== '') {
                array_unshift($prefixes, $group->attributes['prefix']);
            }
            $group = $group->parent;
        }

        return implode('/', $prefixes);
    }

    /**
     * Apply group attributes to route.
     */
    protected function applyGroupAttributes(Route $route): void
    {
        $mergedAttributes = $this->getMergedAttributes();

        if (isset($mergedAttributes['middleware'])) {
            $route->middleware($mergedAttributes['middleware']);
        }

        if (isset($mergedAttributes['name'])) {
            $route->name($mergedAttributes['name']);
        }

        if (isset($mergedAttributes['namespace'])) {
            $route->namespace($mergedAttributes['namespace']);
        }

        if (isset($mergedAttributes['domain'])) {
            $route->domain($mergedAttributes['domain']);
        }

        if (isset($mergedAttributes['where'])) {
            $route->where($mergedAttributes['where']);
        }
    }

    /**
     * Get merged attributes from all parent groups.
     */
    protected function getMergedAttributes(): array
    {
        $attributes = [];
        $group = $this;

        while ($group) {
            $attributes = $this->mergeAttributes($group->attributes, $attributes);
            $group = $group->parent;
        }

        return $attributes;
    }

    /**
     * Merge two sets of attributes.
     */
    protected function mergeAttributes(array $old, array $new): array
    {
        $merged = $old;

        // Merge middleware arrays
        if (isset($old['middleware']) || isset($new['middleware'])) {
            $merged['middleware'] = array_unique(array_merge(
                $old['middleware'] ?? [],
                $new['middleware'] ?? []
            ));
        }

        // Merge prefixes
        if (isset($old['prefix']) || isset($new['prefix'])) {
            $oldPrefix = $old['prefix'] ?? '';
            $newPrefix = $new['prefix'] ?? '';
            
            if ($oldPrefix && $newPrefix) {
                $merged['prefix'] = trim($oldPrefix . '/' . $newPrefix, '/');
            } elseif ($newPrefix) {
                $merged['prefix'] = $newPrefix;
            } elseif ($oldPrefix) {
                $merged['prefix'] = $oldPrefix;
            }
        }

        // Merge names
        if (isset($old['name']) || isset($new['name'])) {
            $merged['name'] = ($old['name'] ?? '') . ($new['name'] ?? '');
        }

        // Merge where constraints
        if (isset($old['where']) || isset($new['where'])) {
            $merged['where'] = array_merge(
                $old['where'] ?? [],
                $new['where'] ?? []
            );
        }

        // Other attributes override
        foreach (['namespace', 'domain'] as $key) {
            if (isset($new[$key])) {
                $merged[$key] = $new[$key];
            } elseif (isset($old[$key])) {
                $merged[$key] = $old[$key];
            }
        }

        return $merged;
    }

    // ====================================================================
    // Group Management
    // ====================================================================

    /**
     * Add child group.
     */
    protected function addChild(RouteGroup $child): void
    {
        $this->children[] = $child;
    }

    /**
     * Get all child groups.
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * Get parent group.
     */
    public function getParent(): ?RouteGroup
    {
        return $this->parent;
    }

    /**
     * Get group attributes.
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get routes registered in this group.
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Get all routes including child groups.
     */
    public function getAllRoutes(): array
    {
        $routes = $this->routes;

        foreach ($this->children as $child) {
            $routes = array_merge($routes, $child->getAllRoutes());
        }

        return $routes;
    }

    /**
     * Count routes in group.
     */
    public function count(): int
    {
        return count($this->routes);
    }

    /**
     * Count all routes including child groups.
     */
    public function countAll(): int
    {
        return count($this->getAllRoutes());
    }

    /**
     * Get group statistics.
     */
    public function getStats(): array
    {
        return [
            'routes' => count($this->routes),
            'total_routes' => $this->countAll(),
            'children' => count($this->children),
            'depth' => $this->getDepth(),
            'attributes' => $this->attributes,
        ];
    }

    /**
     * Get group depth.
     */
    public function getDepth(): int
    {
        $depth = 0;
        $group = $this->parent;

        while ($group) {
            $depth++;
            $group = $group->parent;
        }

        return $depth;
    }

    /**
     * Convert group to string for debugging.
     */
    public function __toString(): string
    {
        $prefix = $this->getFullPrefix();
        $routes = $this->countAll();
        
        return "RouteGroup[prefix: /{$prefix}, routes: {$routes}]";
    }
}