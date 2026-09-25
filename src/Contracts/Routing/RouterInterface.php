<?php

declare(strict_types=1);

namespace Horizon\Contracts\Routing;

use Horizon\Contracts\Http\RequestInterface;
use Horizon\Contracts\Http\ResponseInterface;

interface RouterInterface
{
    /**
     * Register a new GET route with the router.
     */
    public function get(string $uri, mixed $action): RouteInterface;

    /**
     * Register a new POST route with the router.
     */
    public function post(string $uri, mixed $action): RouteInterface;

    /**
     * Register a new PUT route with the router.
     */
    public function put(string $uri, mixed $action): RouteInterface;

    /**
     * Register a new PATCH route with the router.
     */
    public function patch(string $uri, mixed $action): RouteInterface;

    /**
     * Register a new DELETE route with the router.
     */
    public function delete(string $uri, mixed $action): RouteInterface;

    /**
     * Register a new OPTIONS route with the router.
     */
    public function options(string $uri, mixed $action): RouteInterface;

    /**
     * Register a new route responding to all verbs.
     */
    public function any(string $uri, mixed $action): RouteInterface;

    /**
     * Register a new route with the given verbs.
     */
    public function match(array $methods, string $uri, mixed $action): RouteInterface;

    /**
     * Create a route group with shared attributes.
     */
    public function group(array $attributes, \Closure $routes): void;

    /**
     * Dispatch the request to the application.
     */
    public function dispatch(RequestInterface $request): ResponseInterface;

    /**
     * Get all of the defined routes.
     */
    public function getRoutes(): RouteCollectionInterface;
}

interface RouteInterface
{
    /**
     * Get the HTTP methods for the route.
     */
    public function methods(): array;

    /**
     * Get the URI associated with the route.
     */
    public function uri(): string;

    /**
     * Get the action associated with the route.
     */
    public function getAction(): mixed;

    /**
     * Set or get the middlewares attached to the route.
     */
    public function middleware(mixed $middleware = null): static|array;

    /**
     * Set the route name.
     */
    public function name(string $name): static;

    /**
     * Get the route name.
     */
    public function getName(): ?string;

    /**
     * Determine if the route matches given request.
     */
    public function matches(RequestInterface $request): bool;

    /**
     * Run the route action and return the response.
     */
    public function run(): ResponseInterface;

    /**
     * Get the compiled version of the route.
     */
    public function getCompiled(): mixed;

    /**
     * Bind the route to a given request for execution.
     */
    public function bind(RequestInterface $request): static;

    /**
     * Get route parameters.
     */
    public function parameters(): array;

    /**
     * Get a specific parameter by key.
     */
    public function parameter(string $name, mixed $default = null): mixed;
}

interface RouteCollectionInterface
{
    /**
     * Add a Route instance to the collection.
     */
    public function add(RouteInterface $route): RouteInterface;

    /**
     * Find the first route matching a given request.
     */
    public function match(RequestInterface $request): RouteInterface;

    /**
     * Get all routes in the collection.
     */
    public function all(): array;

    /**
     * Get routes by method.
     */
    public function get(string $method): array;
}