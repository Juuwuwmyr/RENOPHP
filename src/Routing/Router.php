<?php

declare(strict_types=1);

namespace Horizon\Routing;

use Horizon\Contracts\Foundation\ApplicationInterface;
use Horizon\Contracts\Http\RequestInterface;
use Horizon\Contracts\Http\ResponseInterface;
use Horizon\Contracts\Routing\RouteCollectionInterface;
use Horizon\Contracts\Routing\RouteInterface;
use Horizon\Contracts\Routing\RouterInterface;
use Horizon\Http\Exceptions\HttpException;
use Horizon\Http\Exceptions\MethodNotAllowedException;
use Horizon\Http\Exceptions\NotFoundHttpException;
use Horizon\Http\Response;

class Router implements RouterInterface
{
    /**
     * The application instance.
     */
    protected ApplicationInterface $container;

    /**
     * The route collection instance.
     */
    protected RouteCollectionInterface $routes;

    /**
     * The currently dispatched route instance.
     */
    protected ?RouteInterface $current = null;

    /**
     * The request currently being dispatched.
     */
    protected ?RequestInterface $currentRequest = null;

    /**
     * All of the short-hand keys for middlewares.
     */
    protected array $middleware = [];

    /**
     * All of the middleware groups.
     */
    protected array $middlewareGroups = [];

    /**
     * The priority-sorted list of middleware.
     */
    protected array $middlewarePriority = [];

    /**
     * The registered route value binders.
     */
    protected array $binders = [];

    /**
     * The globally available parameter patterns.
     */
    protected array $patterns = [];

    /**
     * The route group attribute stack.
     */
    protected array $groupStack = [];

    /**
     * The registered string macros.
     */
    protected array $macros = [];

    /**
     * Create a new Router instance.
     */
    public function __construct(ApplicationInterface $container)
    {
        $this->container = $container;
        
        // Load routes from cache if available, otherwise create new collection
        if ($this->routesAreCached()) {
            $this->loadCachedRoutes();
        } else {
            $this->routes = new RouteCollection();
        }
    }

    /**
     * Determine if routes are cached.
     */
    protected function routesAreCached(): bool
    {
        return $this->container->routesAreCached();
    }

    /**
     * Load cached routes.
     */
    protected function loadCachedRoutes(): void
    {
        $cache = new RouteCache($this->container->getCachedRoutesPath());
        $cachedData = $cache->load();
        $this->routes = new CachedRouteCollection($cachedData);
    }

    /**
     * Register a new GET route with the router.
     */
    public function get(string $uri, mixed $action): RouteInterface
    {
        return $this->addRoute(['GET', 'HEAD'], $uri, $action);
    }

    /**
     * Register a new POST route with the router.
     */
    public function post(string $uri, mixed $action): RouteInterface
    {
        return $this->addRoute(['POST'], $uri, $action);
    }

    /**
     * Register a new PUT route with the router.
     */
    public function put(string $uri, mixed $action): RouteInterface
    {
        return $this->addRoute(['PUT'], $uri, $action);
    }

    /**
     * Register a new PATCH route with the router.
     */
    public function patch(string $uri, mixed $action): RouteInterface
    {
        return $this->addRoute(['PATCH'], $uri, $action);
    }

    /**
     * Register a new DELETE route with the router.
     */
    public function delete(string $uri, mixed $action): RouteInterface
    {
        return $this->addRoute(['DELETE'], $uri, $action);
    }

    /**
     * Register a new OPTIONS route with the router.
     */
    public function options(string $uri, mixed $action): RouteInterface
    {
        return $this->addRoute(['OPTIONS'], $uri, $action);
    }

    /**
     * Register a new route responding to all verbs.
     */
    public function any(string $uri, mixed $action): RouteInterface
    {
        return $this->addRoute(static::$verbs, $uri, $action);
    }

    /**
     * Register a new Fallback route with the router.
     */
    public function fallback(mixed $action): RouteInterface
    {
        $placeholder = 'fallbackPlaceholder';

        return $this->addRoute(
            static::$verbs, "{{$placeholder}}", $action
        )->where($placeholder, '.*')->fallback();
    }

    /**
     * Register a new route with the given verbs.
     */
    public function match(array $methods, string $uri, mixed $action): RouteInterface
    {
        return $this->addRoute(array_map('strtoupper', (array) $methods), $uri, $action);
    }

    /**
     * Register an array of resource controllers.
     */
    public function resources(array $resources, array $options = []): void
    {
        foreach ($resources as $name => $controller) {
            $this->resource($name, $controller, $options);
        }
    }

    /**
     * Route a resource to a controller.
     */
    public function resource(string $name, string $controller, array $options = []): PendingResourceRegistration
    {
        if ($this->container && $this->container->bound(ResourceRegistrar::class)) {
            $registrar = $this->container->make(ResourceRegistrar::class);
        } else {
            $registrar = new ResourceRegistrar($this);
        }

        return new PendingResourceRegistration(
            $registrar, $name, $controller, $options
        );
    }

    /**
     * Register an array of API resource controllers.
     */
    public function apiResources(array $resources, array $options = []): void
    {
        foreach ($resources as $name => $controller) {
            $this->apiResource($name, $controller, $options);
        }
    }

    /**
     * Route an API resource to a controller.
     */
    public function apiResource(string $name, string $controller, array $options = []): PendingResourceRegistration
    {
        $only = ['index', 'show', 'store', 'update', 'destroy'];

        if (isset($options['except'])) {
            $only = array_diff($only, (array) $options['except']);
        }

        return $this->resource($name, $controller, array_merge([
            'only' => $only,
        ], $options));
    }

    /**
     * Create a route group with shared attributes.
     */
    public function group(array $attributes, \Closure $routes): void
    {
        $this->updateGroupStack($attributes);

        $this->loadRoutes($routes);

        array_pop($this->groupStack);
    }

    /**
     * Create a route group with a shared prefix.
     */
    public function prefix(string $prefix): PendingRouteGroup
    {
        return new PendingRouteGroup($this, ['prefix' => $prefix]);
    }

    /**
     * Create a route group with shared middleware.
     */
    public function middleware(string|array $middleware): PendingRouteGroup
    {
        return new PendingRouteGroup($this, ['middleware' => $middleware]);
    }

    /**
     * Create a route group with a shared namespace.
     */
    public function namespace(string $namespace): PendingRouteGroup
    {
        return new PendingRouteGroup($this, ['namespace' => $namespace]);
    }

    /**
     * Create a route group with a shared domain.
     */
    public function domain(string $domain): PendingRouteGroup
    {
        return new PendingRouteGroup($this, ['domain' => $domain]);
    }

    /**
     * Create a route group with a shared name prefix.
     */
    public function name(string $name): PendingRouteGroup
    {
        return new PendingRouteGroup($this, ['as' => $name]);
    }

    /**
     * Update the group stack with the given attributes.
     */
    protected function updateGroupStack(array $attributes): void
    {
        if (!empty($this->groupStack)) {
            $attributes = $this->mergeWithLastGroup($attributes);
        }

        $this->groupStack[] = $attributes;
    }

    /**
     * Merge the given array with the last group stack.
     */
    public function mergeWithLastGroup(array $new, bool $prependExistingPrefix = true): array
    {
        return RouteGroup::mergeAttributes($new, end($this->groupStack));
    }

    /**
     * Load the provided routes.
     */
    protected function loadRoutes(\Closure $routes): void
    {
        $routes($this);
    }

    /**
     * Get the prefix from the last group on the stack.
     */
    public function getLastGroupPrefix(): string
    {
        if (!empty($this->groupStack)) {
            $last = end($this->groupStack);

            return $last['prefix'] ?? '';
        }

        return '';
    }

    /**
     * Add a route to the underlying route collection.
     */
    public function addRoute(array $methods, string $uri, mixed $action): RouteInterface
    {
        return $this->routes->add($this->createRoute($methods, $uri, $action));
    }

    /**
     * Create a new route instance.
     */
    protected function createRoute(array $methods, string $uri, mixed $action): RouteInterface
    {
        // If the route is routing to a controller we will parse the route action into
        // an acceptable array format before registering it and creating this route
        // instance itself. We need to build the Closure that will call this out.
        if ($this->actionReferencesController($action)) {
            $action = $this->convertToControllerAction($action);
        }

        $route = $this->newRoute(
            $methods, $this->prefix($uri), $action
        );

        // If we have groups that need to be merged, we will merge them now after this
        // route has already been created and is ready to go. After we're done with
        // the merge we will be ready to return the route back out to the caller.
        if ($this->hasGroupStack()) {
            $this->mergeGroupAttributesIntoRoute($route);
        }

        $this->addWhereClausesToRoute($route);

        return $route;
    }

    /**
     * Create a new Route object.
     */
    protected function newRoute(array $methods, string $uri, mixed $action): RouteInterface
    {
        return (new Route($methods, $uri, $action))
                    ->setRouter($this)
                    ->setContainer($this->container);
    }

    /**
     * Prefix the given URI with the last prefix.
     */
    protected function prefix(string $uri): string
    {
        return trim(trim($this->getLastGroupPrefix(), '/') . '/' . trim($uri, '/'), '/') ?: '/';
    }

    /**
     * Add the necessary where clauses to the route based on its initial registration.
     */
    protected function addWhereClausesToRoute(RouteInterface $route): void
    {
        $route->where(array_merge(
            $this->patterns, $route->getAction()['where'] ?? []
        ));
    }

    /**
     * Merge the group stack with the controller action.
     */
    protected function mergeGroupAttributesIntoRoute(RouteInterface $route): void
    {
        $route->setAction($this->mergeWithLastGroup(
            $route->getAction(), $prependExistingPrefix = false
        ));
    }

    /**
     * Determine if the action is routing to a controller.
     */
    protected function actionReferencesController(mixed $action): bool
    {
        if (!$action instanceof \Closure) {
            return is_string($action) || (isset($action['uses']) && is_string($action['uses']));
        }

        return false;
    }

    /**
     * Add a controller based route action to the action array.
     */
    protected function convertToControllerAction(mixed $action): array
    {
        if (is_string($action)) {
            $action = ['uses' => $action];
        }

        // Here we'll merge any group "uses" statement if necessary so that the action
        // has the proper clause for this property. Then we can simply set the name
        // of the controller on the action and return the action array for usage.
        if (!empty($this->groupStack)) {
            $action['uses'] = $this->prependGroupNamespace($action['uses']);
        }

        // Here we will set this controller name on the action array just so we always
        // have a copy of it for reference if we need it. This can be used while we
        // search for a controller name or do some other type of fetch operation.
        $action['controller'] = $action['uses'];

        return $action;
    }

    /**
     * Prepend the last group namespace onto the use clause.
     */
    protected function prependGroupNamespace(string $class): string
    {
        $group = end($this->groupStack);

        return isset($group['namespace']) && !str_starts_with($class, '\\')
            ? $group['namespace'] . '\\' . $class : $class;
    }

    /**
     * Dispatch the request to the application.
     */
    public function dispatch(RequestInterface $request): ResponseInterface
    {
        $this->currentRequest = $request;

        return $this->dispatchToRoute($request);
    }

    /**
     * Dispatch the request to a route and return the response.
     */
    public function dispatchToRoute(RequestInterface $request): ResponseInterface
    {
        return $this->runRoute($request, $this->findRoute($request));
    }

    /**
     * Find the route matching a given request.
     */
    protected function findRoute(RequestInterface $request): RouteInterface
    {
        $this->current = $route = $this->routes->match($request);

        $this->container->instance(RouteInterface::class, $route);

        return $route;
    }

    /**
     * Return the response for the given route.
     */
    protected function runRoute(RequestInterface $request, RouteInterface $route): ResponseInterface
    {
        $request->setRouteResolver(function () use ($route) {
            return $route;
        });

        return $route->bind($request)->run();
    }

    /**
     * Determine if the router currently has a group stack.
     */
    public function hasGroupStack(): bool
    {
        return !empty($this->groupStack);
    }

    /**
     * Get the current group stack for the router.
     */
    public function getGroupStack(): array
    {
        return $this->groupStack;
    }

    /**
     * Set the group stack for the router.
     */
    public function setGroupStack(array $groupStack): void
    {
        $this->groupStack = $groupStack;
    }

    /**
     * Push new group attributes onto the group stack.
     */
    public function pushGroup(array $attributes): void
    {
        $this->updateGroupStack($attributes);
    }

    /**
     * Get the underlying route collection.
     */
    public function getRoutes(): RouteCollectionInterface
    {
        return $this->routes;
    }

    /**
     * Get the current route instance.
     */
    public function getCurrentRoute(): ?RouteInterface
    {
        return $this->current;
    }

    /**
     * Get the current request instance.
     */
    public function getCurrentRequest(): ?RequestInterface
    {
        return $this->currentRequest;
    }

    /**
     * Set the global parameter patterns.
     */
    public function patterns(array $patterns): void
    {
        foreach ($patterns as $key => $pattern) {
            $this->pattern($key, $pattern);
        }
    }

    /**
     * Set a global parameter pattern.
     */
    public function pattern(string $key, string $pattern): void
    {
        $this->patterns[$key] = $pattern;
    }

    /**
     * Determine if the given array of patterns and the given request match.
     */
    public function hasValidSignature(RequestInterface $request, bool $absolute = true, array $ignoreQuery = []): bool
    {
        return URL::hasValidSignature($request, $absolute, $ignoreQuery);
    }

    /**
     * Register a route matched event listener.
     */
    public function matched(callable $callback): void
    {
        $this->events->listen(Events\RouteMatched::class, $callback);
    }

    /**
     * Get all middleware, including global middleware.
     */
    public static function uniqueMiddleware(array $middleware): array
    {
        return array_unique($middleware, SORT_REGULAR);
    }

    /**
     * Set the unmapped global resource parameters to singular.
     */
    public function singularResourceParameters(bool $singular = true): static
    {
        ResourceRegistrar::singularParameters($singular);

        return $this;
    }

    /**
     * Set the global resource parameter mapping.
     */
    public function resourceParameters(array $parameters = []): static
    {
        ResourceRegistrar::setParameters($parameters);

        return $this;
    }

    /**
     * Get or set the verbs used in the resource URIs.
     */
    public function resourceVerbs(array $verbs = []): array|static
    {
        if (!empty($verbs)) {
            ResourceRegistrar::verbs($verbs);

            return $this;
        }

        return ResourceRegistrar::$verbs;
    }

    /**
     * The available router verbs.
     */
    public static array $verbs = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
}