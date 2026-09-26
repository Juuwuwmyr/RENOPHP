<?php

declare(strict_types=1);

namespace Reno\Routing;

use Reno\Contracts\Http\RequestInterface;
use Reno\Contracts\Routing\RouteCollectionInterface;
use Reno\Contracts\Routing\RouteInterface;
use Reno\Http\Exceptions\MethodNotAllowedException;
use Reno\Http\Exceptions\NotFoundHttpException;

class CachedRouteCollection implements RouteCollectionInterface
{
    /**
     * The cached route data.
     */
    protected array $cachedData;

    /**
     * The instantiated routes.
     */
    protected array $routes = [];

    /**
     * Create a new cached route collection.
     */
    public function __construct(array $cachedData)
    {
        $this->cachedData = $cachedData;
    }

    /**
     * Add a route to the collection.
     */
    public function add(RouteInterface $route): RouteInterface
    {
        throw new \BadMethodCallException('Cannot add routes to a cached collection.');
    }

    /**
     * Find the first route matching a given request.
     */
    public function match(RequestInterface $request): RouteInterface
    {
        $routes = $this->get($request->method());

        $route = $this->matchAgainstRoutes($routes, $request);

        return $this->handleMatchedRoute($request, $route);
    }

    /**
     * Get routes from the collection by method.
     */
    public function get(?string $method = null): array
    {
        if (is_null($method)) {
            return $this->getRoutes();
        }

        $routes = [];
        $cachedRoutes = $this->cachedData['routes'][$method] ?? [];

        foreach ($cachedRoutes as $uri => $routeData) {
            $routes[] = $this->createRouteFromCache($uri, $routeData);
        }

        return $routes;
    }

    /**
     * Determine if a route in the array matches the request.
     */
    protected function matchAgainstRoutes(array $routes, RequestInterface $request, bool $includingMethod = true): ?RouteInterface
    {
        [$fallbacks, $regularRoutes] = $this->separateFallbackRoutes($routes);

        foreach (array_merge($regularRoutes, $fallbacks) as $route) {
            if ($route->matches($request, $includingMethod)) {
                return $route;
            }
        }

        return null;
    }

    /**
     * Separate fallback routes from regular routes.
     */
    protected function separateFallbackRoutes(array $routes): array
    {
        $fallbacks = [];
        $regularRoutes = [];

        foreach ($routes as $route) {
            if ($route->isFallback) {
                $fallbacks[] = $route;
            } else {
                $regularRoutes[] = $route;
            }
        }

        return [$fallbacks, $regularRoutes];
    }

    /**
     * Handle the matched route.
     */
    protected function handleMatchedRoute(RequestInterface $request, ?RouteInterface $route): RouteInterface
    {
        if (!is_null($route)) {
            return $route;
        }

        $others = $this->checkForAlternateVerbs($request);

        if (count($others) > 0) {
            $this->methodNotAllowed($others);
        }

        $this->notFound();
    }

    /**
     * Determine if any routes match on another HTTP verb.
     */
    protected function checkForAlternateVerbs(RequestInterface $request): array
    {
        $methods = array_diff(Router::$verbs, [$request->method()]);

        return array_values(array_filter($methods, function ($method) use ($request) {
            return !is_null($this->matchAgainstRoutes($this->get($method), $request, false));
        }));
    }

    /**
     * Throw a method not allowed HTTP exception.
     */
    protected function methodNotAllowed(array $others): void
    {
        throw new MethodNotAllowedException(
            sprintf(
                'The %s method is not supported for this route. Supported methods: %s.',
                request()->method(),
                implode(', ', $others)
            )
        );
    }

    /**
     * Throw a not found HTTP exception.
     */
    protected function notFound(): void
    {
        throw new NotFoundHttpException('Route not found.');
    }

    /**
     * Determine if the route collection contains a given named route.
     */
    public function hasNamedRoute(string $name): bool
    {
        return isset($this->cachedData['names'][$name]);
    }

    /**
     * Get a route instance by its name.
     */
    public function getByName(string $name): ?RouteInterface
    {
        if (!$this->hasNamedRoute($name)) {
            return null;
        }

        $uri = $this->cachedData['names'][$name];
        return $this->findRouteByUri($uri);
    }

    /**
     * Get a route instance by its controller action.
     */
    public function getByAction(string $action): ?RouteInterface
    {
        $action = trim($action, '\\');
        
        if (!isset($this->cachedData['actions'][$action])) {
            return null;
        }

        $uri = $this->cachedData['actions'][$action];
        return $this->findRouteByUri($uri);
    }

    /**
     * Find a route by its URI.
     */
    protected function findRouteByUri(string $uri): ?RouteInterface
    {
        foreach ($this->cachedData['routes'] as $method => $routes) {
            if (isset($routes[$uri])) {
                return $this->createRouteFromCache($uri, $routes[$uri]);
            }
        }

        return null;
    }

    /**
     * Get all of the routes in the collection.
     */
    public function getRoutes(): array
    {
        $routes = [];

        foreach ($this->cachedData['routes'] as $method => $methodRoutes) {
            foreach ($methodRoutes as $uri => $routeData) {
                $routes[] = $this->createRouteFromCache($uri, $routeData);
            }
        }

        return array_unique($routes, SORT_REGULAR);
    }

    /**
     * Get all of the routes keyed by their HTTP verb / method.
     */
    public function getRoutesByMethod(): array
    {
        $routes = [];

        foreach ($this->cachedData['routes'] as $method => $methodRoutes) {
            $routes[$method] = [];
            foreach ($methodRoutes as $uri => $routeData) {
                $routes[$method][$uri] = $this->createRouteFromCache($uri, $routeData);
            }
        }

        return $routes;
    }

    /**
     * Get all of the routes keyed by their name.
     */
    public function getRoutesByName(): array
    {
        $routes = [];

        foreach ($this->cachedData['names'] as $name => $uri) {
            $routes[$name] = $this->findRouteByUri($uri);
        }

        return array_filter($routes);
    }

    /**
     * Create a route instance from cached data.
     */
    protected function createRouteFromCache(string $uri, array $routeData): RouteInterface
    {
        $cacheKey = $uri . ':' . md5(serialize($routeData));

        if (isset($this->routes[$cacheKey])) {
            return $this->routes[$cacheKey];
        }

        $route = new Route($routeData['methods'], $routeData['uri'], $routeData['action']);
        $route->defaults = $routeData['defaults'];
        $route->wheres = $routeData['wheres'];
        $route->isFallback = $routeData['is_fallback'];

        // Set compiled route data if available
        if (isset($this->cachedData['compiled'][$uri])) {
            $compiledData = $this->cachedData['compiled'][$uri];
            $route->setCompiledRoute(new CompiledRoute(
                $compiledData['regex'],
                $compiledData['tokens'],
                $compiledData['path_variables'],
                $compiledData['host_regex'],
                $compiledData['host_tokens'],
                $compiledData['host_variables'],
                $compiledData['variables']
            ));
        }

        return $this->routes[$cacheKey] = $route;
    }

    /**
     * Get an iterator for the items.
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->getRoutes());
    }

    /**
     * Count the number of items in the collection.
     */
    public function count(): int
    {
        return count($this->getRoutes());
    }

    /**
     * Get all routes in the collection.
     */
    public function all(): array
    {
        return $this->getRoutes();
    }
}