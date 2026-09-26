<?php

declare(strict_types=1);

namespace Horizon\Routing;

use Horizon\Http\Request;
use Horizon\Routing\Route;
use Horizon\Routing\RouteCollection;
use Horizon\Routing\RouteCache;
use Horizon\Routing\RouteCompiler;
use Horizon\Routing\Exceptions\RouteNotFoundException;
use Horizon\Routing\Exceptions\MethodNotAllowedException;

/**
 * FastRouter
 * 
 * High-performance router with caching, optimized lookups, and
 * performance monitoring for production applications.
 */
class FastRouter
{
    /**
     * Route collection.
     */
    protected RouteCollection $routes;

    /**
     * Route cache instance.
     */
    protected ?RouteCache $cache = null;

    /**
     * Route compiler.
     */
    protected RouteCompiler $compiler;

    /**
     * Performance metrics.
     */
    protected array $metrics = [
        'cache_hits' => 0,
        'cache_misses' => 0,
        'static_matches' => 0,
        'dynamic_matches' => 0,
        'total_lookups' => 0,
        'avg_lookup_time' => 0.0,
    ];

    /**
     * Router configuration.
     */
    protected array $config = [
        'cache_enabled' => true,
        'performance_monitoring' => true,
        'precompile_routes' => true,
    ];

    /**
     * Compiled route lookup tables.
     */
    protected array $staticRoutes = [];
    protected array $dynamicRoutes = [];
    protected array $namedRoutes = [];

    /**
     * Create a new FastRouter instance.
     */
    public function __construct(RouteCollection $routes = null, RouteCache $cache = null, array $config = [])
    {
        $this->routes = $routes ?? new RouteCollection();
        $this->cache = $cache;
        $this->compiler = new RouteCompiler();
        $this->config = array_merge($this->config, $config);

        if ($this->config['precompile_routes']) {
            $this->compileRoutes();
        }
    }

    // ====================================================================
    // Route Registration
    // ====================================================================

    /**
     * Add route to collection and update lookup tables.
     */
    public function addRoute(Route $route): void
    {
        $this->routes->add($route);
        
        if ($this->config['precompile_routes']) {
            $this->compileRoute($route);
        }
    }

    /**
     * Set the route collection.
     */
    public function setRoutes(RouteCollection $routes): void
    {
        $this->routes = $routes;
        
        if ($this->config['precompile_routes']) {
            $this->compileRoutes();
        }
    }

    /**
     * Get the route collection.
     */
    public function getRoutes(): RouteCollection
    {
        return $this->routes;
    }

    // ====================================================================
    // High-Performance Route Matching
    // ====================================================================

    /**
     * Find matching route with performance optimization.
     */
    public function match(Request $request): Route
    {
        $startTime = microtime(true);
        $method = $request->method();
        $uri = '/' . ltrim($request->path(), '/');

        try {
            $route = $this->findRoute($method, $uri);
            
            if (!$route) {
                $this->recordMiss($startTime);
                throw new RouteNotFoundException("No route found for {$method} {$uri}", $method, $uri);
            }

            $this->recordHit($startTime, $route['type'] ?? 'unknown');
            return $this->createRouteFromCache($route, $request);

        } catch (RouteNotFoundException $e) {
            // Check if route exists with different method
            $allowedMethods = $this->getAllowedMethods($uri);
            
            if (!empty($allowedMethods)) {
                throw new MethodNotAllowedException($allowedMethods, $method, $uri);
            }
            
            throw $e;
        }
    }

    /**
     * Find route using optimized lookup strategy.
     */
    protected function findRoute(string $method, string $uri): ?array
    {
        $this->metrics['total_lookups']++;

        // Strategy 1: Try cache first
        if ($this->cache && $this->cache->isEnabled()) {
            $cached = $this->cache->findRoute($method, $uri);
            if ($cached) {
                $this->metrics['cache_hits']++;
                return array_merge($cached, ['type' => 'cached']);
            }
            $this->metrics['cache_misses']++;
        }

        // Strategy 2: Try static routes (O(1) lookup)
        if (isset($this->staticRoutes[$method][$uri])) {
            $this->metrics['static_matches']++;
            return array_merge($this->staticRoutes[$method][$uri], ['type' => 'static']);
        }

        // Strategy 3: Try dynamic routes (ordered by complexity)
        foreach ($this->dynamicRoutes[$method] ?? [] as $routeData) {
            if ($this->matchesDynamicRoute($routeData, $uri)) {
                $this->metrics['dynamic_matches']++;
                return array_merge($routeData, ['type' => 'dynamic']);
            }
        }

        return null;
    }

    /**
     * Check if URI matches dynamic route pattern.
     */
    protected function matchesDynamicRoute(array $routeData, string $uri): bool
    {
        if (!isset($routeData['compiled']['regex'])) {
            return false;
        }

        if (preg_match($routeData['compiled']['regex'], $uri, $matches)) {
            // Extract and store parameters
            $parameters = [];
            if (!empty($routeData['compiled']['parameters'])) {
                foreach ($routeData['compiled']['parameters'] as $index => $paramName) {
                    $parameters[$paramName] = $matches[$index + 1] ?? null;
                }
            }
            $routeData['matched_parameters'] = $parameters;
            return true;
        }

        return false;
    }

    /**
     * Create Route object from cached data.
     */
    protected function createRouteFromCache(array $routeData, Request $request): Route
    {
        $route = new Route(
            $routeData['methods'],
            $routeData['uri'],
            $routeData['action']
        );

        if (isset($routeData['name'])) {
            $route->name($routeData['name']);
        }

        if (isset($routeData['middleware'])) {
            $route->middleware($routeData['middleware']);
        }

        if (isset($routeData['wheres'])) {
            $route->where($routeData['wheres']);
        }

        if (isset($routeData['matched_parameters'])) {
            $route->setParameters($routeData['matched_parameters']);
        }

        return $route;
    }

    /**
     * Get allowed methods for URI.
     */
    protected function getAllowedMethods(string $uri): array
    {
        $allowedMethods = [];

        // Check static routes
        foreach ($this->staticRoutes as $method => $routes) {
            if (isset($routes[$uri])) {
                $allowedMethods[] = $method;
            }
        }

        // Check dynamic routes
        foreach ($this->dynamicRoutes as $method => $routes) {
            foreach ($routes as $routeData) {
                if ($this->matchesDynamicRoute($routeData, $uri)) {
                    $allowedMethods[] = $method;
                    break;
                }
            }
        }

        return array_unique($allowedMethods);
    }

    // ====================================================================
    // Route Compilation
    // ====================================================================

    /**
     * Compile all routes into lookup tables.
     */
    protected function compileRoutes(): void
    {
        $this->staticRoutes = [];
        $this->dynamicRoutes = [];
        $this->namedRoutes = [];

        // Load from cache if available
        if ($this->cache && $this->cache->isEnabled()) {
            $cached = $this->cache->load();
            if ($cached) {
                $this->loadFromCache($cached);
                return;
            }
        }

        // Compile from route collection
        foreach ($this->routes as $route) {
            $this->compileRoute($route);
        }

        // Sort dynamic routes by complexity for optimal matching order
        $this->optimizeDynamicRoutes();

        // Cache compiled routes
        if ($this->cache && $this->cache->isEnabled()) {
            $this->cache->cache($this->routes);
        }
    }

    /**
     * Compile individual route.
     */
    protected function compileRoute(Route $route): void
    {
        $uri = $route->getUri();
        $methods = $route->getMethods();
        $hasParameters = $route->hasParameters();

        $routeData = [
            'uri' => $uri,
            'methods' => $methods,
            'action' => $route->getAction(),
            'name' => $route->getName(),
            'middleware' => $route->getMiddleware(),
            'wheres' => $route->getWheres(),
            'parameters' => $route->getParameterNames(),
        ];

        if ($hasParameters) {
            // Dynamic route
            $compiled = $this->compiler->compile($route);
            $routeData['compiled'] = $compiled;
            $routeData['complexity'] = $this->calculateComplexity($compiled);

            foreach ($methods as $method) {
                if (!isset($this->dynamicRoutes[$method])) {
                    $this->dynamicRoutes[$method] = [];
                }
                $this->dynamicRoutes[$method][] = $routeData;
            }
        } else {
            // Static route
            foreach ($methods as $method) {
                if (!isset($this->staticRoutes[$method])) {
                    $this->staticRoutes[$method] = [];
                }
                $this->staticRoutes[$method][$uri] = $routeData;
            }
        }

        // Named route
        if ($route->getName()) {
            $this->namedRoutes[$route->getName()] = $routeData;
        }
    }

    /**
     * Load compiled routes from cache.
     */
    protected function loadFromCache(array $cached): void
    {
        if (isset($cached['static'])) {
            $this->staticRoutes = $cached['static'];
        }

        if (isset($cached['dynamic'])) {
            // Convert cached dynamic routes to method-indexed format
            foreach ($cached['dynamic'] as $routeData) {
                foreach ($routeData['methods'] as $method) {
                    if (!isset($this->dynamicRoutes[$method])) {
                        $this->dynamicRoutes[$method] = [];
                    }
                    $this->dynamicRoutes[$method][] = $routeData;
                }
            }
        }

        if (isset($cached['names'])) {
            $this->namedRoutes = $cached['names'];
        }
    }

    /**
     * Optimize dynamic route order by complexity.
     */
    protected function optimizeDynamicRoutes(): void
    {
        foreach ($this->dynamicRoutes as $method => $routes) {
            usort($routes, function ($a, $b) {
                return ($a['complexity'] ?? 0) <=> ($b['complexity'] ?? 0);
            });
            $this->dynamicRoutes[$method] = $routes;
        }
    }

    /**
     * Calculate route complexity.
     */
    protected function calculateComplexity(array $compiled): int
    {
        $complexity = 0;
        
        $complexity += count($compiled['parameters'] ?? []);
        $complexity += count($compiled['optional'] ?? []) * 2;
        
        if (isset($compiled['regex'])) {
            $regex = $compiled['regex'];
            $complexity += substr_count($regex, '(');
            $complexity += substr_count($regex, '[');
            $complexity += substr_count($regex, '*');
            $complexity += substr_count($regex, '+');
            $complexity += substr_count($regex, '?');
        }

        return $complexity;
    }

    // ====================================================================
    // Named Routes
    // ====================================================================

    /**
     * Get route by name.
     */
    public function getNamedRoute(string $name): ?array
    {
        // Try cache first
        if ($this->cache && $this->cache->isEnabled()) {
            $cached = $this->cache->getNamedRoute($name);
            if ($cached) {
                return $cached;
            }
        }

        return $this->namedRoutes[$name] ?? null;
    }

    /**
     * Generate URL for named route.
     */
    public function generateUrl(string $name, array $parameters = []): string
    {
        $routeData = $this->getNamedRoute($name);
        
        if (!$routeData) {
            throw new \InvalidArgumentException("Named route '{$name}' not found.");
        }

        return $this->compiler->generate($routeData['uri'], $parameters);
    }

    // ====================================================================
    // Performance Monitoring
    // ====================================================================

    /**
     * Record cache hit.
     */
    protected function recordHit(float $startTime, string $type): void
    {
        if (!$this->config['performance_monitoring']) {
            return;
        }

        $duration = microtime(true) - $startTime;
        $this->updateAverageTime($duration);
    }

    /**
     * Record cache miss.
     */
    protected function recordMiss(float $startTime): void
    {
        if (!$this->config['performance_monitoring']) {
            return;
        }

        $duration = microtime(true) - $startTime;
        $this->updateAverageTime($duration);
    }

    /**
     * Update average lookup time.
     */
    protected function updateAverageTime(float $duration): void
    {
        $totalLookups = $this->metrics['total_lookups'];
        $currentAvg = $this->metrics['avg_lookup_time'];
        
        $this->metrics['avg_lookup_time'] = (($currentAvg * ($totalLookups - 1)) + $duration) / $totalLookups;
    }

    // ====================================================================
    // Cache Management
    // ====================================================================

    /**
     * Set route cache.
     */
    public function setCache(RouteCache $cache): void
    {
        $this->cache = $cache;
    }

    /**
     * Get route cache.
     */
    public function getCache(): ?RouteCache
    {
        return $this->cache;
    }

    /**
     * Clear route cache and recompile.
     */
    public function clearCache(): void
    {
        if ($this->cache) {
            $this->cache->clear();
        }
        
        $this->compileRoutes();
    }

    /**
     * Warm up cache.
     */
    public function warmUpCache(): bool
    {
        if (!$this->cache) {
            return false;
        }

        return $this->cache->warmUp($this->routes);
    }

    // ====================================================================
    // Statistics and Diagnostics
    // ====================================================================

    /**
     * Get performance metrics.
     */
    public function getMetrics(): array
    {
        $cacheStats = $this->cache ? $this->cache->getStats() : [];
        
        return array_merge($this->metrics, [
            'cache_stats' => $cacheStats,
            'static_routes_count' => $this->getStaticRoutesCount(),
            'dynamic_routes_count' => $this->getDynamicRoutesCount(),
            'named_routes_count' => count($this->namedRoutes),
            'cache_enabled' => $this->cache && $this->cache->isEnabled(),
        ]);
    }

    /**
     * Get count of static routes.
     */
    protected function getStaticRoutesCount(): int
    {
        $count = 0;
        foreach ($this->staticRoutes as $methodRoutes) {
            $count += count($methodRoutes);
        }
        return $count;
    }

    /**
     * Get count of dynamic routes.
     */
    protected function getDynamicRoutesCount(): int
    {
        $count = 0;
        foreach ($this->dynamicRoutes as $methodRoutes) {
            $count += count($methodRoutes);
        }
        return $count;
    }

    /**
     * Get routing diagnostics.
     */
    public function getDiagnostics(): array
    {
        return [
            'performance' => [
                'avg_lookup_time_ms' => round($this->metrics['avg_lookup_time'] * 1000, 4),
                'total_lookups' => $this->metrics['total_lookups'],
                'cache_hit_rate' => $this->getCacheHitRate(),
                'static_match_rate' => $this->getStaticMatchRate(),
            ],
            'structure' => [
                'static_routes' => $this->getStaticRoutesCount(),
                'dynamic_routes' => $this->getDynamicRoutesCount(),
                'named_routes' => count($this->namedRoutes),
                'methods' => array_keys(array_merge($this->staticRoutes, $this->dynamicRoutes)),
            ],
            'optimization' => [
                'precompiled' => $this->config['precompile_routes'],
                'cache_enabled' => $this->cache && $this->cache->isEnabled(),
                'monitoring_enabled' => $this->config['performance_monitoring'],
            ],
        ];
    }

    /**
     * Get cache hit rate.
     */
    protected function getCacheHitRate(): float
    {
        $total = $this->metrics['cache_hits'] + $this->metrics['cache_misses'];
        
        if ($total === 0) {
            return 0.0;
        }

        return round(($this->metrics['cache_hits'] / $total) * 100, 2);
    }

    /**
     * Get static match rate.
     */
    protected function getStaticMatchRate(): float
    {
        $total = $this->metrics['static_matches'] + $this->metrics['dynamic_matches'];
        
        if ($total === 0) {
            return 0.0;
        }

        return round(($this->metrics['static_matches'] / $total) * 100, 2);
    }

    /**
     * Reset metrics.
     */
    public function resetMetrics(): void
    {
        $this->metrics = [
            'cache_hits' => 0,
            'cache_misses' => 0,
            'static_matches' => 0,
            'dynamic_matches' => 0,
            'total_lookups' => 0,
            'avg_lookup_time' => 0.0,
        ];
    }

    /**
     * Get configuration.
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Set configuration.
     */
    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }
}