<?php

declare(strict_types=1);

namespace Horizon\Routing;

use Horizon\Contracts\Routing\RouteCollectionInterface;
use Horizon\Contracts\Routing\RouteInterface;

class RouteCache
{
    /**
     * The cache file path.
     */
    protected string $cachePath;

    /**
     * Create a new route cache instance.
     */
    public function __construct(string $cachePath)
    {
        $this->cachePath = $cachePath;
    }

    /**
     * Determine if the route cache exists and is fresh.
     */
    public function isFresh(array $routeFiles = []): bool
    {
        if (!$this->exists()) {
            return false;
        }

        $cacheTime = filemtime($this->cachePath);

        foreach ($routeFiles as $file) {
            if (file_exists($file) && filemtime($file) > $cacheTime) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if the route cache exists.
     */
    public function exists(): bool
    {
        return file_exists($this->cachePath);
    }

    /**
     * Cache the given routes.
     */
    public function store(RouteCollectionInterface $routes): void
    {
        $this->ensureCacheDirectoryExists();

        $cachedRoutes = $this->buildCacheData($routes);

        file_put_contents(
            $this->cachePath,
            '<?php return ' . var_export($cachedRoutes, true) . ';'
        );
    }

    /**
     * Load the cached routes.
     */
    public function load(): array
    {
        if (!$this->exists()) {
            throw new \RuntimeException('Route cache file does not exist.');
        }

        return require $this->cachePath;
    }

    /**
     * Clear the route cache.
     */
    public function clear(): bool
    {
        if ($this->exists()) {
            return unlink($this->cachePath);
        }

        return true;
    }

    /**
     * Build the cache data structure.
     */
    protected function buildCacheData(RouteCollectionInterface $routes): array
    {
        $cachedRoutes = [
            'routes' => [],
            'compiled' => [],
            'names' => [],
            'actions' => [],
        ];

        foreach ($routes->getRoutes() as $route) {
            $routeData = $this->serializeRoute($route);
            
            // Store route by method and URI
            $domainAndUri = ($route->getDomain() ?? '') . $route->uri();
            
            foreach ($route->methods() as $method) {
                $cachedRoutes['routes'][$method][$domainAndUri] = $routeData;
            }

            // Store compiled route data
            $cachedRoutes['compiled'][$domainAndUri] = $this->serializeCompiledRoute($route);

            // Store named routes
            if ($name = $route->getName()) {
                $cachedRoutes['names'][$name] = $domainAndUri;
            }

            // Store action routes
            $action = $route->getAction();
            if (isset($action['controller'])) {
                $cachedRoutes['actions'][trim($action['controller'], '\\')] = $domainAndUri;
            }
        }

        return $cachedRoutes;
    }

    /**
     * Serialize a route for caching.
     */
    protected function serializeRoute(RouteInterface $route): array
    {
        return [
            'methods' => $route->methods(),
            'uri' => $route->uri(),
            'action' => $route->getAction(),
            'defaults' => $route->defaults,
            'wheres' => $route->wheres(),
            'is_fallback' => $route->isFallback,
        ];
    }

    /**
     * Serialize compiled route data for caching.
     */
    protected function serializeCompiledRoute(RouteInterface $route): array
    {
        $compiled = $route->getCompiled();
        
        return [
            'regex' => $compiled->getRegex(),
            'tokens' => $compiled->getTokens(),
            'path_variables' => $compiled->getPathVariables(),
            'host_regex' => $compiled->getHostRegex(),
            'host_tokens' => $compiled->getHostTokens(),
            'host_variables' => $compiled->getHostVariables(),
            'variables' => $compiled->getVariables(),
        ];
    }

    /**
     * Ensure the cache directory exists.
     */
    protected function ensureCacheDirectoryExists(): void
    {
        $directory = dirname($this->cachePath);

        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    /**
     * Get the cache file path.
     */
    public function getCachePath(): string
    {
        return $this->cachePath;
    }

    /**
     * Get the cache file size in bytes.
     */
    public function getSize(): int
    {
        return $this->exists() ? filesize($this->cachePath) : 0;
    }

    /**
     * Get cache statistics.
     */
    public function getStats(): array
    {
        if (!$this->exists()) {
            return [
                'exists' => false,
                'size' => 0,
                'created_at' => null,
                'modified_at' => null,
            ];
        }

        $stat = stat($this->cachePath);

        return [
            'exists' => true,
            'size' => $stat['size'],
            'size_human' => $this->formatBytes($stat['size']),
            'created_at' => date('Y-m-d H:i:s', $stat['ctime']),
            'modified_at' => date('Y-m-d H:i:s', $stat['mtime']),
            'route_count' => $this->countCachedRoutes(),
        ];
    }

    /**
     * Count the number of cached routes.
     */
    protected function countCachedRoutes(): int
    {
        if (!$this->exists()) {
            return 0;
        }

        try {
            $data = $this->load();
            return count($data['routes'] ?? []);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Format bytes to human readable format.
     */
    protected function formatBytes(int $size, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }
}