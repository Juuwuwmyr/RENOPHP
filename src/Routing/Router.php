<?php

declare(strict_types=1);

namespace Horizon\Routing;

use Horizon\Http\Request;
use Horizon\Http\Response;
use Horizon\Routing\Route;
use Horizon\Routing\RouteCollection;
use Horizon\Routing\RouteCompiler;
use Horizon\Routing\Exceptions\RouteNotFoundException;
use Horizon\Routing\Exceptions\MethodNotAllowedException;
use Closure;
use InvalidArgumentException;

/**
 * Router
 * 
 * Fast, flexible HTTP router with pattern matching, parameter extraction,
 * and comprehensive routing features.
 * 
 * Philosophy: "Fast by default, flexible when needed"
 */
class Router
{
    /**
     * Route collection.
     */
    protected RouteCollection $routes;

    /**
     * Route compiler for pattern matching.
     */
    protected RouteCompiler $compiler;

    /**
     * Current route group attributes.
     */
    protected array $groupStack = [];

    /**
     * Route model bindings.
     */
    protected array $bindings = [];

    /**
     * Route parameter patterns.
     */
    protected array $patterns = [];

    /**
     * Named routes cache.
     */
    protected array $namedRoutes = [];

    /**
     * Compiled routes cache.
     */
    protected array $compiledRoutes = [];

    /**
     * Route caching enabled.
     */
    protected bool $cacheEnabled = false;

    /**
     * Route cache file path.
     */
    protected ?string $cacheFile = null;

    /**
     * Supported HTTP methods.
     */
    protected array $httpMethods = [
        'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'
    ];

    /**
     * Create a new Router instance.
     */
    public function __construct()
    {
        $this->routes = new RouteCollection();
        $this->compiler = new RouteCompiler();
        $this->setDefaultPatterns();
    }

    // ====================================================================
    // Route Registration Methods
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
     * Register a route that responds to any HTTP method.
     */
    public function any(string $uri, mixed $action): Route
    {
        return $this->addRoute($this->httpMethods, $uri, $action);
    }

    /**
     * Register a route that responds to multiple HTTP methods.
     */
    public function match(array $methods, string $uri, mixed $action): Route
    {
        return $this->addRoute(array_map('strtoupper', $methods), $uri, $action);
    }

    /**
     * Add a route to the collection.
     */
    protected function addRoute(array $methods, string $uri, mixed $action): Route
    {
        $uri = $this->normalizeUri($uri);
        
        // Apply group attributes
        $attributes = $this->mergeGroupAttributes([]);
        
        if (isset($attributes['prefix'])) {
            $uri = $this->applyPrefix($uri, $attributes['prefix']);
        }

        $route = new Route($methods, $uri, $action);
        
        // Apply group attributes to route
        $this->applyGroupAttributesToRoute($route, $attributes);
        
        // Add route to collection
        $this->routes->add($route);
        
        return $route;
    }

    // ====================================================================
    // Route Matching
    // ====================================================================

    /**
     * Find the route matching a given request.
     */
    public function matchRequest(Request $request): Route
    {
        $method = $request->method();
        $pathInfo = $request->path();

        // Try to find exact match first
        if ($route = $this->findExactMatch($method, $pathInfo)) {
            return $this->bindParameters($request, $route);
        }

        // Try compiled routes with parameters
        if ($route = $this->findCompiledMatch($method, $pathInfo)) {
            return $this->bindParameters($request, $route);
        }

        // Check if path exists for other methods
        $allowedMethods = $this->getAllowedMethods($pathInfo);
        if (!empty($allowedMethods)) {
            throw new MethodNotAllowedException($allowedMethods);
        }

        throw new RouteNotFoundException("Route not found for: {$method} {$pathInfo}");
    }

    /**
     * Find exact route match.
     */
    protected function findExactMatch(string $method, string $pathInfo): ?Route
    {
        foreach ($this->routes->getByMethod($method) as $route) {
            if ($route->getUri() === $pathInfo && !$route->hasParameters()) {
                return $route;
            }
        }

        return null;
    }

    /**
     * Find compiled route match with parameters.
     */
    protected function findCompiledMatch(string $method, string $pathInfo): ?Route
    {
        foreach ($this->routes->getByMethod($method) as $route) {
            if ($route->hasParameters()) {
                $compiled = $this->compileRoute($route);
                
                if (preg_match($compiled['regex'], $pathInfo, $matches)) {
                    // Extract parameter values
                    $parameters = [];
                    foreach ($compiled['parameters'] as $i => $name) {
                        if (isset($matches[$i + 1])) {
                            $parameters[$name] = $matches[$i + 1];
                        }
                    }
                    
                    $route->setParameters($parameters);
                    return $route;
                }
            }
        }

        return null;
    }

    /**
     * Get allowed methods for a path.
     */
    protected function getAllowedMethods(string $pathInfo): array
    {
        $allowedMethods = [];

        foreach ($this->routes->all() as $route) {
            if ($this->routeMatchesPath($route, $pathInfo)) {
                $allowedMethods = array_merge($allowedMethods, $route->getMethods());
            }
        }

        return array_unique($allowedMethods);
    }

    /**
     * Check if route matches path (ignoring method).
     */
    protected function routeMatchesPath(Route $route, string $pathInfo): bool
    {
        if ($route->getUri() === $pathInfo) {
            return true;
        }

        if ($route->hasParameters()) {
            $compiled = $this->compileRoute($route);
            return preg_match($compiled['regex'], $pathInfo) === 1;
        }

        return false;
    }

    // ====================================================================
    // Route Compilation
    // ====================================================================

    /**
     * Compile a route pattern.
     */
    protected function compileRoute(Route $route): array
    {
        $uri = $route->getUri();
        
        if (isset($this->compiledRoutes[$uri])) {
            return $this->compiledRoutes[$uri];
        }

        $compiled = $this->compiler->compile($route, $this->patterns);
        $this->compiledRoutes[$uri] = $compiled;
        
        return $compiled;
    }

    // ====================================================================
    // Parameter Binding
    // ====================================================================

    /**
     * Bind parameters to request.
     */
    protected function bindParameters(Request $request, Route $route): Route
    {
        $parameters = $route->getParameters();
        
        // Apply model bindings
        foreach ($parameters as $name => $value) {
            if (isset($this->bindings[$name])) {
                $parameters[$name] = $this->performBinding($name, $value);
            }
        }

        $route->setParameters($parameters);
        $request->setRouteParameters($parameters);
        
        return $route;
    }

    /**
     * Perform model binding for a parameter.
     */
    protected function performBinding(string $key, mixed $value): mixed
    {
        $binding = $this->bindings[$key];
        
        if ($binding instanceof Closure) {
            return $binding($value);
        }
        
        if (is_string($binding)) {
            // Assume it's a model class name
            return $binding::find($value);
        }
        
        return $value;
    }

    /**
     * Register a model binding.
     */
    public function model(string $key, string $class): void
    {
        $this->bindings[$key] = $class;
    }

    /**
     * Register a custom binding.
     */
    public function bind(string $key, Closure $resolver): void
    {
        $this->bindings[$key] = $resolver;
    }

    // ====================================================================
    // Route Groups
    // ====================================================================

    /**
     * Create a route group.
     */
    public function group(array $attributes, Closure $callback): void
    {
        $this->groupStack[] = $attributes;
        
        $callback($this);
        
        array_pop($this->groupStack);
    }

    /**
     * Apply group attributes to route.
     */
    protected function applyGroupAttributesToRoute(Route $route, array $attributes): void
    {
        if (isset($attributes['middleware'])) {
            $route->middleware($attributes['middleware']);
        }
        
        if (isset($attributes['name'])) {
            $route->name($attributes['name']);
        }
        
        if (isset($attributes['namespace'])) {
            $route->namespace($attributes['namespace']);
        }
        
        if (isset($attributes['where'])) {
            $route->where($attributes['where']);
        }
    }

    /**
     * Merge group attributes.
     */
    protected function mergeGroupAttributes(array $new): array
    {
        $attributes = [];
        
        foreach ($this->groupStack as $group) {
            $attributes = $this->mergeGroup($attributes, $group);
        }
        
        return $this->mergeGroup($attributes, $new);
    }

    /**
     * Merge two sets of group attributes.
     */
    protected function mergeGroup(array $old, array $new): array
    {
        $merged = $old;
        
        // Merge middleware
        if (isset($old['middleware']) || isset($new['middleware'])) {
            $merged['middleware'] = array_unique(array_merge(
                $old['middleware'] ?? [],
                $new['middleware'] ?? []
            ));
        }
        
        // Merge prefixes
        if (isset($old['prefix']) || isset($new['prefix'])) {
            $merged['prefix'] = trim(
                ($old['prefix'] ?? '') . '/' . ($new['prefix'] ?? ''),
                '/'
            );
        }
        
        // Merge names
        if (isset($old['name']) || isset($new['name'])) {
            $merged['name'] = ($old['name'] ?? '') . ($new['name'] ?? '');
        }
        
        // Other attributes override
        foreach (['namespace', 'where', 'domain'] as $key) {
            if (isset($new[$key])) {
                $merged[$key] = $new[$key];
            } elseif (isset($old[$key])) {
                $merged[$key] = $old[$key];
            }
        }
        
        return $merged;
    }

    // ====================================================================
    // Resource Routes
    // ====================================================================

    /**
     * Register resource routes.
     */
    public function resource(string $name, string $controller, array $options = []): void
    {
        $actions = $options['only'] ?? ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'];
        
        if (isset($options['except'])) {
            $actions = array_diff($actions, $options['except']);
        }

        $resourceRoutes = [
            'index' => ['GET', $name, 'index'],
            'create' => ['GET', $name . '/create', 'create'],
            'store' => ['POST', $name, 'store'],
            'show' => ['GET', $name . '/{id}', 'show'],
            'edit' => ['GET', $name . '/{id}/edit', 'edit'],
            'update' => ['PUT', $name . '/{id}', 'update'],
            'destroy' => ['DELETE', $name . '/{id}', 'destroy'],
        ];

        foreach ($actions as $action) {
            if (isset($resourceRoutes[$action])) {
                [$method, $uri, $controllerAction] = $resourceRoutes[$action];
                
                $route = $this->addRoute([$method], $uri, $controller . '@' . $controllerAction);
                $route->name($name . '.' . $action);
                
                if (in_array($action, ['show', 'edit', 'update', 'destroy'])) {
                    $route->where('id', '[0-9]+');
                }
            }
        }
    }

    // ====================================================================
    // Named Routes
    // ====================================================================

    /**
     * Get route by name.
     */
    public function getByName(string $name): ?Route
    {
        if (isset($this->namedRoutes[$name])) {
            return $this->namedRoutes[$name];
        }

        foreach ($this->routes->all() as $route) {
            if ($route->getName() === $name) {
                $this->namedRoutes[$name] = $route;
                return $route;
            }
        }

        return null;
    }

    /**
     * Generate URL for named route.
     */
    public function route(string $name, array $parameters = [], bool $absolute = true): string
    {
        $route = $this->getByName($name);
        
        if (!$route) {
            throw new InvalidArgumentException("Route [{$name}] not defined.");
        }

        return $this->generateUrl($route, $parameters, $absolute);
    }

    /**
     * Generate URL from route.
     */
    protected function generateUrl(Route $route, array $parameters = [], bool $absolute = true): string
    {
        $uri = $route->getUri();
        
        // Replace parameters in URI
        foreach ($parameters as $key => $value) {
            $uri = str_replace('{' . $key . '}', $value, $uri);
            $uri = str_replace('{' . $key . '?}', $value, $uri);
        }

        // Remove optional parameters not provided
        $uri = preg_replace('/\{[^}]+\?\}/', '', $uri);
        
        // Clean up multiple slashes
        $uri = preg_replace('#/+#', '/', '/' . trim($uri, '/'));

        if ($absolute) {
            // In a real implementation, this would get the base URL from config
            $baseUrl = 'http://localhost';
            return $baseUrl . $uri;
        }

        return $uri;
    }

    // ====================================================================
    // Route Constraints
    // ====================================================================

    /**
     * Set global parameter pattern.
     */
    public function pattern(string $key, string $pattern): void
    {
        $this->patterns[$key] = $pattern;
    }

    /**
     * Set multiple parameter patterns.
     */
    public function patterns(array $patterns): void
    {
        $this->patterns = array_merge($this->patterns, $patterns);
    }

    /**
     * Set default parameter patterns.
     */
    protected function setDefaultPatterns(): void
    {
        $this->patterns = [
            'id' => '[0-9]+',
            'slug' => '[a-z0-9-]+',
            'uuid' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}',
            'alpha' => '[a-zA-Z]+',
            'num' => '[0-9]+',
        ];
    }

    // ====================================================================
    // Route Caching
    // ====================================================================

    /**
     * Enable route caching.
     */
    public function enableCache(string $cacheFile): void
    {
        $this->cacheEnabled = true;
        $this->cacheFile = $cacheFile;
    }

    /**
     * Load routes from cache.
     */
    public function loadCache(): bool
    {
        if (!$this->cacheEnabled || !$this->cacheFile || !file_exists($this->cacheFile)) {
            return false;
        }

        $cached = include $this->cacheFile;
        
        if (is_array($cached) && isset($cached['routes'], $cached['compiled'])) {
            $this->routes = unserialize($cached['routes']);
            $this->compiledRoutes = $cached['compiled'];
            return true;
        }

        return false;
    }

    /**
     * Cache compiled routes.
     */
    public function cacheRoutes(): bool
    {
        if (!$this->cacheEnabled || !$this->cacheFile) {
            return false;
        }

        $cached = [
            'routes' => serialize($this->routes),
            'compiled' => $this->compiledRoutes,
            'timestamp' => time(),
        ];

        $content = "<?php\n\nreturn " . var_export($cached, true) . ";\n";
        
        return file_put_contents($this->cacheFile, $content, LOCK_EX) !== false;
    }

    /**
     * Clear route cache.
     */
    public function clearCache(): bool
    {
        if ($this->cacheFile && file_exists($this->cacheFile)) {
            return unlink($this->cacheFile);
        }

        return true;
    }

    // ====================================================================
    // Utility Methods
    // ====================================================================

    /**
     * Get all routes.
     */
    public function getRoutes(): RouteCollection
    {
        return $this->routes;
    }

    /**
     * Get route count.
     */
    public function count(): int
    {
        return $this->routes->count();
    }

    /**
     * Check if router has routes.
     */
    public function hasRoutes(): bool
    {
        return $this->routes->count() > 0;
    }

    /**
     * Normalize URI.
     */
    protected function normalizeUri(string $uri): string
    {
        // Ensure URI starts with /
        $uri = '/' . ltrim($uri, '/');
        
        // Remove trailing slash unless it's root
        if ($uri !== '/' && str_ends_with($uri, '/')) {
            $uri = rtrim($uri, '/');
        }

        return $uri;
    }

    /**
     * Apply prefix to URI.
     */
    protected function applyPrefix(string $uri, string $prefix): string
    {
        $prefix = trim($prefix, '/');
        
        if (empty($prefix)) {
            return $uri;
        }

        return '/' . $prefix . $uri;
    }

    /**
     * Get router statistics.
     */
    public function getStats(): array
    {
        $stats = [
            'total_routes' => $this->routes->count(),
            'compiled_routes' => count($this->compiledRoutes),
            'named_routes' => count($this->namedRoutes),
            'patterns' => count($this->patterns),
            'cache_enabled' => $this->cacheEnabled,
        ];

        // Count routes by method
        foreach ($this->httpMethods as $method) {
            $stats['routes_by_method'][strtolower($method)] = count($this->routes->getByMethod($method));
        }

        return $stats;
    }

    /**
     * Get route list for debugging.
     */
    public function getRouteList(): array
    {
        $list = [];

        foreach ($this->routes->all() as $route) {
            $list[] = [
                'methods' => implode('|', $route->getMethods()),
                'uri' => $route->getUri(),
                'name' => $route->getName(),
                'action' => $this->formatAction($route->getAction()),
                'middleware' => implode('|', $route->getMiddleware()),
            ];
        }

        return $list;
    }

    /**
     * Format action for display.
     */
    protected function formatAction(mixed $action): string
    {
        if ($action instanceof Closure) {
            return 'Closure';
        }

        if (is_string($action)) {
            return $action;
        }

        if (is_array($action)) {
            return implode('@', $action);
        }

        return 'Unknown';
    }
}