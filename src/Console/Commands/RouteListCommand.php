<?php

declare(strict_types=1);

namespace Horizon\Console\Commands;

use Horizon\Console\Command;

class RouteListCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected string $signature = 'route:list 
                                  {--method= : Filter routes by method}
                                  {--name= : Filter routes by name}
                                  {--path= : Filter routes by path}
                                  {--middleware= : Filter routes by middleware}
                                  {--domain= : Filter routes by domain}';

    /**
     * The console command description.
     */
    protected string $description = 'List all registered routes';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $router = app('router');
        $routes = $router->getRoutes()->getRoutes();

        if (empty($routes)) {
            $this->info('No routes found.');
            return 0;
        }

        // Apply filters
        $routes = $this->filterRoutes($routes);

        if (empty($routes)) {
            $this->info('No routes match the specified filters.');
            return 0;
        }

        // Display routes table
        $this->displayRoutesTable($routes);

        return 0;
    }

    /**
     * Filter routes based on command options.
     */
    protected function filterRoutes(array $routes): array
    {
        $filtered = [];

        foreach ($routes as $route) {
            if ($this->routeMatchesFilters($route)) {
                $filtered[] = $route;
            }
        }

        return $filtered;
    }

    /**
     * Check if route matches all filters.
     */
    protected function routeMatchesFilters($route): bool
    {
        // Filter by method
        if ($method = $this->option('method')) {
            if (!in_array(strtoupper($method), $route->methods())) {
                return false;
            }
        }

        // Filter by name
        if ($name = $this->option('name')) {
            if (!$route->getName() || !str_contains($route->getName(), $name)) {
                return false;
            }
        }

        // Filter by path
        if ($path = $this->option('path')) {
            if (!str_contains($route->uri(), $path)) {
                return false;
            }
        }

        // Filter by middleware
        if ($middleware = $this->option('middleware')) {
            $routeMiddleware = $route->gatherMiddleware();
            if (!in_array($middleware, $routeMiddleware)) {
                return false;
            }
        }

        // Filter by domain
        if ($domain = $this->option('domain')) {
            if (!$route->domain() || !str_contains($route->domain(), $domain)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Display the routes in a table format.
     */
    protected function displayRoutesTable(array $routes): void
    {
        $headers = ['Method', 'URI', 'Name', 'Action', 'Middleware', 'Domain'];
        $rows = [];

        foreach ($routes as $route) {
            $rows[] = [
                implode('|', $route->methods()),
                $route->uri(),
                $route->getName() ?: '-',
                $this->getRouteAction($route),
                implode(', ', array_slice($route->gatherMiddleware(), 0, 3)) . 
                    (count($route->gatherMiddleware()) > 3 ? '...' : ''),
                $route->domain() ?: '-',
            ];
        }

        $this->table($headers, $rows);

        $this->line('');
        $this->info('Total routes: ' . count($routes));
    }

    /**
     * Get the route action description.
     */
    protected function getRouteAction($route): string
    {
        $action = $route->getAction();

        if (isset($action['controller'])) {
            $controller = $action['controller'];
            $method = $action['method'] ?? 'invoke';
            return $controller . '@' . $method;
        }

        if (isset($action['uses'])) {
            if (is_string($action['uses'])) {
                return 'Closure';
            }
        }

        return 'Closure';
    }
}