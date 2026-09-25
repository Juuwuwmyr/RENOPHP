<?php

declare(strict_types=1);

namespace Horizon\Routing;

use Horizon\Contracts\Http\RequestInterface;
use Horizon\Contracts\Routing\RouteCollectionInterface;
use Horizon\Contracts\Routing\RouteInterface;
use Horizon\Http\Exceptions\MethodNotAllowedException;
use Horizon\Http\Exceptions\NotFoundHttpException;

class RouteCollection implements RouteCollectionInterface
{
    /**
     * An array of the routes keyed by method.
     */
    protected array $routes = [];

    /**
     * An flattened array of all the routes.
     */
    protected array $allRoutes = [];

    /**
     * A look-up table of routes by their names.
     */
    protected array $nameList = [];

    /**
     * A look-up table of routes by controller action.
     */
    protected array $actionList = [];

    /**
     * Add a Route instance to the collection.
     */
    public function add(RouteInterface $route): RouteInterface
    {
        $this->addToCollections($route);

        $this->addLookups($route);

        return $route;
    }

    /**
     * Add the given route to the arrays of routes.
     */
    protected function addToCollections(RouteInterface $route): void
    {
        $domainAndUri = ($route->getDomain() ?? '') . $route->uri();

        foreach ($route->methods() as $method) {
            $this->routes[$method][$domainAndUri] = $route;
        }

        $this->allRoutes[$method.$domainAndUri] = $route;
    }

    /**
     * Add the route to any look-up tables if necessary.
     */
    protected function addLookups(RouteInterface $route): void
    {
        // If the route has a name, we will add it to the name look-up table so that we
        // will quickly be able to find any route associate with a name and not have
        // to iterate through every route every time we need to perform a look-up.
        if ($name = $route->getName()) {
            $this->nameList[$name] = $route;
        }

        // When the route is routing to a controller we will also store the action that
        // is used by the route. This will let us reverse route to controllers while
        // processing a request and easily generate URLs to the given controllers.
        $action = $route->getAction();

        if (isset($action['controller'])) {
            $this->addToActionList($action, $route);
        }
    }

    /**
     * Add a route to the controller action dictionary.
     */
    protected function addToActionList(array $action, RouteInterface $route): void
    {
        $this->actionList[trim($action['controller'], '\\')] = $route;
    }

    /**
     * Refresh the name look-up table.
     */
    public function refreshNameLookups(): void
    {
        $this->nameList = [];

        foreach ($this->allRoutes as $route) {
            if ($route->getName()) {
                $this->nameList[$route->getName()] = $route;
            }
        }
    }

    /**
     * Refresh the action look-up table.
     */
    public function refreshActionLookups(): void
    {
        $this->actionList = [];

        foreach ($this->allRoutes as $route) {
            $action = $route->getAction();

            if (isset($action['controller'])) {
                $this->addToActionList($action, $route);
            }
        }
    }

    /**
     * Find the first route matching a given request.
     */
    public function match(RequestInterface $request): RouteInterface
    {
        $routes = $this->get($request->method());

        // First, we will see if we can find a matching route for this current request
        // method. If we can, great, we can just return it so that it can be called
        // by the consumer. Otherwise we will check for routes with another method.
        $route = $this->matchAgainstRoutes($routes, $request);

        return $this->handleMatchedRoute($request, $route);
    }

    /**
     * Determine if a route in the array matches the request.
     */
    protected function matchAgainstRoutes(array $routes, RequestInterface $request, bool $includingMethod = true): ?RouteInterface
    {
        // Separate fallback routes from regular routes
        $fallbacks = [];
        $regularRoutes = [];
        
        foreach ($routes as $route) {
            if (isset($route->isFallback) && $route->isFallback) {
                $fallbacks[] = $route;
            } else {
                $regularRoutes[] = $route;
            }
        }
        
        // Check regular routes first, then fallbacks
        $allRoutes = array_merge($regularRoutes, $fallbacks);
        
        foreach ($allRoutes as $route) {
            if ($route->matches($request, $includingMethod)) {
                return $route;
            }
        }
        
        return null;
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
     * Handle the matched route.
     */
    protected function handleMatchedRoute(RequestInterface $request, ?RouteInterface $route): RouteInterface
    {
        if (!is_null($route)) {
            return $route;
        }

        // If no route was found, we will check if a matching route is specified for
        // another HTTP verb. If it is we will need to throw a MethodNotAllowed and
        // inform the user agent of which HTTP verb it should use for this route.
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
     * Get routes from the collection by method.
     */
    public function get(string $method = null): array
    {
        return is_null($method) ? $this->getRoutes() : $this->routes[$method] ?? [];
    }

    /**
     * Determine if the route collection contains a given named route.
     */
    public function hasNamedRoute(string $name): bool
    {
        return !is_null($this->getByName($name));
    }

    /**
     * Get a route instance by its name.
     */
    public function getByName(string $name): ?RouteInterface
    {
        return $this->nameList[$name] ?? null;
    }

    /**
     * Get a route instance by its controller action.
     */
    public function getByAction(string $action): ?RouteInterface
    {
        return $this->actionList[$action] ?? null;
    }

    /**
     * Get all of the routes in the collection.
     */
    public function getRoutes(): array
    {
        return array_values($this->allRoutes);
    }

    /**
     * Get all of the routes keyed by their HTTP verb / method.
     */
    public function getRoutesByMethod(): array
    {
        return $this->routes;
    }

    /**
     * Get all of the routes keyed by their name.
     */
    public function getRoutesByName(): array
    {
        return $this->nameList;
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