<?php

declare(strict_types=1);

namespace Horizon\Routing;

use Horizon\Routing\RouteCollection;
use Horizon\Routing\Route;
use Horizon\Routing\RouteCompiler;
use InvalidArgumentException;

/**
 * RouteCache
 * 
 * High-performance route caching system that compiles routes into 
 * optimized data structures for fast lookup and matching.
 */
class RouteCache
{
    /**
     * Cache file path.
     */
    protected string $cacheFile;

    /**
     * Route compiler instance.
     */
    protected RouteCompiler $compiler;

    /**
     * Cached route data.
     */
    protected ?array $cachedRoutes = null;

    /**
     * Cache statistics.
     */
    protected array $stats = [
        'hits' => 0,
        'misses' => 0,
        'compilations' => 0,
        'cache_size' => 0,
    ];

    /**
     * Cache configuration.
     */
    protected array $config = [
        'enabled' => true,
        'ttl' => 86400, // 24 hours
        'auto_refresh' => true,
        'compression' => true,
    ];

    /**
     * Create a new RouteCache instance.
     */
    public function __construct(string $cacheFile, ?RouteCompiler $compiler = null, array $config = [])
    {
        $this->cacheFile = $cacheFile;
        $this->compiler = $compiler ?? new RouteCompiler();
        $this->config = array_merge($this->config, $config);

        $this->ensureCacheDirectory();
    }

    // ====================================================================
    // Cache Management
    // ====================================================================

    /**
     * Cache the route collection.
     */
    public function cache(RouteCollection $routes): bool
    {
        if (!$this->config['enabled']) {
            return false;
        }

        $startTime = microtime(true);
        
        try {
            $compiled = $this->compileRoutes($routes);
            $data = [
                'routes' => $compiled,
                'metadata' => [
                    'compiled_at' => time(),
                    'route_count' => count($routes),
                    'compiler_version' => '1.0',
                    'checksum' => $this->generateChecksum($routes),
                ],
                'stats' => [
                    'compilation_time' => microtime(true) - $startTime,
                    'memory_usage' => memory_get_usage(),
                ],
            ];

            $serialized = $this->config['compression'] 
                ? gzcompress(serialize($data), 6)
                : serialize($data);

            $success = file_put_contents($this->cacheFile, $serialized, LOCK_EX) !== false;
            
            if ($success) {
                $this->cachedRoutes = $compiled;
                $this->stats['compilations']++;
                $this->stats['cache_size'] = filesize($this->cacheFile);
            }

            return $success;

        } catch (\Throwable $e) {
            error_log("Route cache compilation failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Load routes from cache.
     */
    public function load(): ?array
    {
        if (!$this->config['enabled'] || !file_exists($this->cacheFile)) {
            $this->stats['misses']++;
            return null;
        }

        if ($this->cachedRoutes !== null) {
            $this->stats['hits']++;
            return $this->cachedRoutes;
        }

        try {
            $content = file_get_contents($this->cacheFile);
            if ($content === false) {
                $this->stats['misses']++;
                return null;
            }

            $data = $this->config['compression']
                ? unserialize(gzuncompress($content))
                : unserialize($content);

            if (!$this->isValidCache($data)) {
                $this->stats['misses']++;
                return null;
            }

            $this->cachedRoutes = $data['routes'];
            $this->stats['hits']++;
            $this->stats['cache_size'] = filesize($this->cacheFile);

            return $this->cachedRoutes;

        } catch (\Throwable $e) {
            error_log("Route cache loading failed: " . $e->getMessage());
            $this->stats['misses']++;
            return null;
        }
    }

    /**
     * Check if cache is valid and fresh.
     */
    public function isValid(RouteCollection $routes = null): bool
    {
        if (!$this->config['enabled'] || !file_exists($this->cacheFile)) {
            return false;
        }

        // Check TTL
        if ($this->config['ttl'] > 0) {
            $age = time() - filemtime($this->cacheFile);
            if ($age > $this->config['ttl']) {
                return false;
            }
        }

        // Check checksum if routes provided
        if ($routes !== null) {
            $cached = $this->load();
            if (!$cached || !isset($cached['metadata'])) {
                return false;
            }

            $currentChecksum = $this->generateChecksum($routes);
            return $cached['metadata']['checksum'] === $currentChecksum;
        }

        return true;
    }

    /**
     * Clear the cache.
     */
    public function clear(): bool
    {
        $this->cachedRoutes = null;
        
        if (file_exists($this->cacheFile)) {
            return unlink($this->cacheFile);
        }

        return true;
    }

    /**
     * Refresh the cache if needed.
     */
    public function refresh(RouteCollection $routes): bool
    {
        if (!$this->config['auto_refresh']) {
            return false;
        }

        if (!$this->isValid($routes)) {
            return $this->cache($routes);
        }

        return true;
    }

    // ====================================================================
    // Route Compilation
    // ====================================================================

    /**
     * Compile routes into optimized structures.
     */
    protected function compileRoutes(RouteCollection $routes): array
    {
        $compiled = [
            'static' => [], // Routes without parameters
            'dynamic' => [], // Routes with parameters
            'methods' => [], // Method -> routes mapping
            'names' => [], // Named route lookup
            'patterns' => [], // Compiled regex patterns
            'metadata' => [
                'total_routes' => count($routes),
                'static_routes' => 0,
                'dynamic_routes' => 0,
                'compiled_at' => time(),
                'checksum' => $this->generateChecksum($routes),
            ],
        ];

        foreach ($routes as $route) {
            $this->compileRoute($route, $compiled);
        }

        // Optimize static route lookup
        $compiled['static'] = $this->optimizeStaticRoutes($compiled['static']);
        
        // Group dynamic routes by complexity
        $compiled['dynamic'] = $this->optimizeDynamicRoutes($compiled['dynamic']);

        return $compiled;
    }

    /**
     * Compile individual route.
     */
    protected function compileRoute(Route $route, array &$compiled): void
    {
        $uri = $route->getUri();
        $methods = $route->getMethods();
        $hasParameters = $route->hasParameters();

        // Route data
        $routeData = [
            'uri' => $uri,
            'methods' => $methods,
            'action' => $this->serializeAction($route->getAction()),
            'name' => $route->getName(),
            'middleware' => $route->getMiddleware(),
            'wheres' => $route->getWheres(),
            'parameters' => $route->getParameterNames(),
        ];

        if ($hasParameters) {
            // Dynamic route with parameters
            $routeCompilation = $this->compiler->compile($route);
            $routeData['compiled'] = $routeCompilation;
            $routeData['complexity'] = $this->calculateComplexity($routeCompilation);
            
            $compiled['dynamic'][] = $routeData;
            $compiled['metadata']['dynamic_routes']++;
            
            // Store pattern for quick access
            $compiled['patterns'][$uri] = $routeCompilation;
        } else {
            // Static route without parameters
            foreach ($methods as $method) {
                $compiled['static'][$method][$uri] = $routeData;
            }
            $compiled['metadata']['static_routes']++;
        }

        // Method mapping
        foreach ($methods as $method) {
            if (!isset($compiled['methods'][$method])) {
                $compiled['methods'][$method] = [];
            }
            $compiled['methods'][$method][] = $uri;
        }

        // Named route mapping
        if ($route->getName()) {
            $compiled['names'][$route->getName()] = $routeData;
        }
    }

    /**
     * Optimize static routes for O(1) lookup.
     */
    protected function optimizeStaticRoutes(array $staticRoutes): array
    {
        // Static routes are already optimized as hash maps
        // Additional optimizations could include:
        // - Prefix trees for common prefixes
        // - Method-specific optimizations
        
        return $staticRoutes;
    }

    /**
     * Optimize dynamic routes by complexity.
     */
    protected function optimizeDynamicRoutes(array $dynamicRoutes): array
    {
        // Sort by complexity (simpler routes first for faster matching)
        usort($dynamicRoutes, function ($a, $b) {
            return ($a['complexity'] ?? 0) <=> ($b['complexity'] ?? 0);
        });

        return $dynamicRoutes;
    }

    /**
     * Calculate route complexity score.
     */
    protected function calculateComplexity(array $compiled): int
    {
        $complexity = 0;
        
        // Parameter count
        $complexity += count($compiled['parameters'] ?? []);
        
        // Optional parameters
        $complexity += count($compiled['optional'] ?? []) * 2;
        
        // Regex complexity
        if (isset($compiled['regex'])) {
            $regex = $compiled['regex'];
            $complexity += substr_count($regex, '(');
            $complexity += substr_count($regex, '[');
            $complexity += substr_count($regex, '*');
            $complexity += substr_count($regex, '+');
            $complexity += substr_count($regex, '?');
            $complexity += substr_count($regex, '|');
        }

        return $complexity;
    }

    // ====================================================================
    // Fast Route Matching
    // ====================================================================

    /**
     * Find matching route from cache.
     */
    public function findRoute(string $method, string $uri): ?array
    {
        $routes = $this->load();
        if (!$routes) {
            return null;
        }

        // Try static routes first (O(1) lookup)
        if (isset($routes['static'][$method][$uri])) {
            return $routes['static'][$method][$uri];
        }

        // Try dynamic routes (ordered by complexity)
        foreach ($routes['dynamic'] as $route) {
            if (!in_array($method, $route['methods'])) {
                continue;
            }

            if (isset($route['compiled']['regex'])) {
                if (preg_match($route['compiled']['regex'], $uri, $matches)) {
                    // Extract parameters
                    $parameters = [];
                    if (!empty($route['compiled']['parameters'])) {
                        foreach ($route['compiled']['parameters'] as $index => $paramName) {
                            $parameters[$paramName] = $matches[$index + 1] ?? null;
                        }
                    }
                    
                    $route['matched_parameters'] = $parameters;
                    return $route;
                }
            }
        }

        return null;
    }

    /**
     * Get route by name from cache.
     */
    public function getNamedRoute(string $name): ?array
    {
        $routes = $this->load();
        if (!$routes) {
            return null;
        }

        return $routes['names'][$name] ?? null;
    }

    /**
     * Get routes by method from cache.
     */
    public function getRoutesByMethod(string $method): array
    {
        $routes = $this->load();
        if (!$routes) {
            return [];
        }

        return $routes['methods'][$method] ?? [];
    }

    // ====================================================================
    // Utility Methods
    // ====================================================================

    /**
     * Generate checksum for route collection.
     */
    protected function generateChecksum(RouteCollection $routes): string
    {
        $data = [];
        
        foreach ($routes as $route) {
            $data[] = [
                'methods' => $route->getMethods(),
                'uri' => $route->getUri(),
                'action' => $this->serializeAction($route->getAction()),
                'middleware' => $route->getMiddleware(),
                'wheres' => $route->getWheres(),
            ];
        }

        return md5(serialize($data));
    }

    /**
     * Serialize route action for caching.
     */
    protected function serializeAction(mixed $action): mixed
    {
        if ($action instanceof \Closure) {
            // Can't serialize closures, store a placeholder
            return ['type' => 'closure', 'hash' => spl_object_hash($action)];
        }

        return $action;
    }

    /**
     * Validate cached data structure.
     */
    protected function isValidCache(array $data): bool
    {
        $requiredKeys = ['routes', 'metadata'];
        
        foreach ($requiredKeys as $key) {
            if (!isset($data[$key])) {
                return false;
            }
        }

        $routes = $data['routes'];
        $requiredRouteKeys = ['static', 'dynamic', 'methods', 'names', 'patterns', 'metadata'];
        
        foreach ($requiredRouteKeys as $key) {
            if (!isset($routes[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Ensure cache directory exists.
     */
    protected function ensureCacheDirectory(): void
    {
        $directory = dirname($this->cacheFile);
        
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new InvalidArgumentException("Cannot create cache directory: {$directory}");
            }
        }

        if (!is_writable($directory)) {
            throw new InvalidArgumentException("Cache directory is not writable: {$directory}");
        }
    }

    // ====================================================================
    // Configuration and Statistics
    // ====================================================================

    /**
     * Get cache statistics.
     */
    public function getStats(): array
    {
        $fileStats = [];
        
        if (file_exists($this->cacheFile)) {
            $fileStats = [
                'file_size' => filesize($this->cacheFile),
                'file_mtime' => filemtime($this->cacheFile),
                'file_age' => time() - filemtime($this->cacheFile),
            ];
        }

        return array_merge($this->stats, $fileStats, [
            'hit_rate' => $this->getHitRate(),
            'enabled' => $this->config['enabled'],
            'cache_file' => $this->cacheFile,
        ]);
    }

    /**
     * Get cache hit rate.
     */
    public function getHitRate(): float
    {
        $total = $this->stats['hits'] + $this->stats['misses'];
        
        if ($total === 0) {
            return 0.0;
        }

        return round(($this->stats['hits'] / $total) * 100, 2);
    }

    /**
     * Get cache configuration.
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Update cache configuration.
     */
    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * Enable caching.
     */
    public function enable(): void
    {
        $this->config['enabled'] = true;
    }

    /**
     * Disable caching.
     */
    public function disable(): void
    {
        $this->config['enabled'] = false;
    }

    /**
     * Check if caching is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->config['enabled'];
    }

    /**
     * Warm up the cache.
     */
    public function warmUp(RouteCollection $routes): bool
    {
        if (!$this->config['enabled']) {
            return false;
        }

        // Force recompilation
        $this->clear();
        return $this->cache($routes);
    }

    /**
     * Get cache file path.
     */
    public function getCacheFile(): string
    {
        return $this->cacheFile;
    }
}