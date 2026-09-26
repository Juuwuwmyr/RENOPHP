<?php

declare(strict_types=1);

namespace Horizon\Routing;

use Horizon\Routing\Route;
use Countable;
use IteratorAggregate;
use ArrayIterator;

/**
 * RouteCollection
 * 
 * Manages a collection of routes with efficient lookup and organization.
 * Optimized for fast route matching by organizing routes by HTTP method.
 */
class RouteCollection implements Countable, IteratorAggregate
{
    /**
     * All routes in the collection.
     */
    protected array $routes = [];

    /**
     * Routes organized by HTTP method for faster lookup.
     */
    protected array $routesByMethod = [];

    /**
     * Named routes for quick access.
     */
    protected array $namedRoutes = [];

    /**
     * Route lookup table by URI for exact matches.
     */
    protected array $routesByUri = [];

    /**
     * Add a route to the collection.
     */
    public function add(Route $route): void
    {
        $this->routes[] = $route;
        
        // Index by methods
        foreach ($route->getMethods() as $method) {
            $this->routesByMethod[$method][] = $route;
        }
        
        // Index by name if named
        if ($route->isNamed()) {
            $this->namedRoutes[$route->getName()] = $route;
        }
        
        // Index by URI for exact matches (no parameters)
        if (!$route->hasParameters()) {
            foreach ($route->getMethods() as $method) {
                $this->routesByUri[$method][$route->getUri()] = $route;
            }
        }
    }

    /**
     * Get all routes.
     */
    public function all(): array
    {
        return $this->routes;
    }

    /**
     * Get routes by HTTP method.
     */
    public function getByMethod(string $method): array
    {
        return $this->routesByMethod[strtoupper($method)] ?? [];
    }

    /**
     * Get route by name.
     */
    public function getByName(string $name): ?Route
    {
        return $this->namedRoutes[$name] ?? null;
    }

    /**
     * Get route by URI and method (exact match only).
     */
    public function getByUri(string $method, string $uri): ?Route
    {
        return $this->routesByUri[strtoupper($method)][$uri] ?? null;
    }

    /**
     * Check if collection has routes for method.
     */
    public function hasMethod(string $method): bool
    {
        return isset($this->routesByMethod[strtoupper($method)]);
    }

    /**
     * Check if collection has named route.
     */
    public function hasName(string $name): bool
    {
        return isset($this->namedRoutes[$name]);
    }

    /**
     * Get all HTTP methods used in collection.
     */
    public function getMethods(): array
    {
        return array_keys($this->routesByMethod);
    }

    /**
     * Get all route names.
     */
    public function getNames(): array
    {
        return array_keys($this->namedRoutes);
    }

    /**
     * Remove route from collection.
     */
    public function remove(Route $route): bool
    {
        $key = array_search($route, $this->routes, true);
        
        if ($key === false) {
            return false;
        }

        // Remove from main array
        unset($this->routes[$key]);
        $this->routes = array_values($this->routes);

        // Remove from method indexes
        foreach ($route->getMethods() as $method) {
            $methodRoutes = $this->routesByMethod[$method] ?? [];
            $methodKey = array_search($route, $methodRoutes, true);
            
            if ($methodKey !== false) {
                unset($this->routesByMethod[$method][$methodKey]);
                $this->routesByMethod[$method] = array_values($this->routesByMethod[$method]);
                
                // Clean up empty method arrays
                if (empty($this->routesByMethod[$method])) {
                    unset($this->routesByMethod[$method]);
                }
            }
        }

        // Remove from named routes
        if ($route->isNamed()) {
            unset($this->namedRoutes[$route->getName()]);
        }

        // Remove from URI index
        if (!$route->hasParameters()) {
            foreach ($route->getMethods() as $method) {
                unset($this->routesByUri[$method][$route->getUri()]);
            }
        }

        return true;
    }

    /**
     * Clear all routes.
     */
    public function clear(): void
    {
        $this->routes = [];
        $this->routesByMethod = [];
        $this->namedRoutes = [];
        $this->routesByUri = [];
    }

    /**
     * Filter routes by callback.
     */
    public function filter(callable $callback): static
    {
        $filtered = new static();
        
        foreach ($this->routes as $route) {
            if ($callback($route)) {
                $filtered->add($route);
            }
        }
        
        return $filtered;
    }

    /**
     * Find routes matching URI pattern.
     */
    public function match(string $uri, string $method = null): array
    {
        $matches = [];
        $routes = $method ? $this->getByMethod($method) : $this->routes;

        foreach ($routes as $route) {
            if ($this->routeMatches($route, $uri)) {
                $matches[] = $route;
            }
        }

        return $matches;
    }

    /**
     * Check if route matches URI.
     */
    protected function routeMatches(Route $route, string $uri): bool
    {
        if ($route->getUri() === $uri) {
            return true;
        }

        if ($route->hasParameters()) {
            // Simple pattern matching - in real implementation would use RouteCompiler
            $pattern = preg_replace('/\{[^}]+\}/', '([^/]+)', $route->getUri());
            return preg_match('#^' . $pattern . '$#', $uri) === 1;
        }

        return false;
    }

    /**
     * Get collection statistics.
     */
    public function getStats(): array
    {
        $stats = [
            'total_routes' => count($this->routes),
            'named_routes' => count($this->namedRoutes),
            'methods' => array_keys($this->routesByMethod),
            'routes_by_method' => [],
        ];

        foreach ($this->routesByMethod as $method => $routes) {
            $stats['routes_by_method'][$method] = count($routes);
        }

        return $stats;
    }

    /**
     * Convert collection to array.
     */
    public function toArray(): array
    {
        return array_map(fn($route) => $route->toArray(), $this->routes);
    }

    /**
     * Merge another collection into this one.
     */
    public function merge(RouteCollection $collection): void
    {
        foreach ($collection->all() as $route) {
            $this->add($route);
        }
    }

    /**
     * Create a copy of the collection.
     */
    public function copy(): static
    {
        $copy = new static();
        $copy->merge($this);
        return $copy;
    }

    // ====================================================================
    // Countable Interface
    // ====================================================================

    /**
     * Count routes in collection.
     */
    public function count(): int
    {
        return count($this->routes);
    }

    // ====================================================================
    // IteratorAggregate Interface
    // ====================================================================

    /**
     * Get iterator for routes.
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->routes);
    }

    // ====================================================================
    // Magic Methods
    // ====================================================================

    /**
     * Check if collection is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->routes);
    }

    /**
     * Get first route in collection.
     */
    public function first(): ?Route
    {
        return $this->routes[0] ?? null;
    }

    /**
     * Get last route in collection.
     */
    public function last(): ?Route
    {
        return $this->routes[array_key_last($this->routes)] ?? null;
    }

    /**
     * Convert collection to string for debugging.
     */
    public function __toString(): string
    {
        $output = "RouteCollection (" . count($this->routes) . " routes)\n";
        
        foreach ($this->routes as $route) {
            $methods = implode('|', $route->getMethods());
            $name = $route->getName() ? " [{$route->getName()}]" : '';
            $output .= "  {$methods} {$route->getUri()}{$name}\n";
        }

        return $output;
    }

    /**
     * Serialize collection.
     */
    public function __serialize(): array
    {
        return [
            'routes' => $this->routes,
            'routesByMethod' => $this->routesByMethod,
            'namedRoutes' => $this->namedRoutes,
            'routesByUri' => $this->routesByUri,
        ];
    }

    /**
     * Unserialize collection.
     */
    public function __unserialize(array $data): void
    {
        $this->routes = $data['routes'] ?? [];
        $this->routesByMethod = $data['routesByMethod'] ?? [];
        $this->namedRoutes = $data['namedRoutes'] ?? [];
        $this->routesByUri = $data['routesByUri'] ?? [];
    }
}