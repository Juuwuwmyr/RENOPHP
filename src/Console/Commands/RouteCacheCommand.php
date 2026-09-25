<?php

declare(strict_types=1);

namespace Horizon\Console\Commands;

use Horizon\Console\Command;
use Horizon\Routing\RouteCache;

class RouteCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected string $signature = 'route:cache';

    /**
     * The console command description.
     */
    protected string $description = 'Create a route cache file for faster route registration';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Caching routes...');

        try {
            $this->cacheRoutes();
            $this->info('Routes cached successfully!');
            return 0;
        } catch (\Exception $e) {
            $this->error('Failed to cache routes: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Cache the application routes.
     */
    protected function cacheRoutes(): void
    {
        // Clear existing route cache
        $this->call('route:clear');

        // Get route cache instance
        $cache = $this->getRouteCache();

        // Load all routes
        $router = app('router');
        $this->loadRoutes($router);

        // Cache the routes
        $cache->store($router->getRoutes());

        $stats = $cache->getStats();
        $this->line("Route cache created: {$stats['size_human']} ({$stats['route_count']} routes)");
    }

    /**
     * Load all application routes.
     */
    protected function loadRoutes($router): void
    {
        $routeFiles = $this->getRouteFiles();

        foreach ($routeFiles as $file) {
            if (file_exists($file)) {
                require $file;
            }
        }
    }

    /**
     * Get the route files to load.
     */
    protected function getRouteFiles(): array
    {
        $basePath = app()->basePath();

        return [
            $basePath . '/routes/web.php',
            $basePath . '/routes/api.php',
            $basePath . '/routes/console.php',
            $basePath . '/routes/channels.php',
        ];
    }

    /**
     * Get the route cache instance.
     */
    protected function getRouteCache(): RouteCache
    {
        return new RouteCache(
            app()->getCachedRoutesPath()
        );
    }
}