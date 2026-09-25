<?php

declare(strict_types=1);

namespace Horizon\Http;

use Horizon\Contracts\Foundation\ApplicationInterface;
use Horizon\Contracts\Http\KernelInterface;
use Horizon\Contracts\Http\RequestInterface;
use Horizon\Contracts\Http\ResponseInterface;
use Horizon\Http\Middleware\Pipeline;
use Throwable;

class Kernel implements KernelInterface
{
    /**
     * The application instance.
     */
    protected ApplicationInterface $app;

    /**
     * The application's global HTTP middleware stack.
     */
    protected array $middleware = [];

    /**
     * The application's route middleware groups.
     */
    protected array $middlewareGroups = [
        'web' => [],
        'api' => [],
    ];

    /**
     * The application's middleware aliases.
     */
    protected array $middlewareAliases = [];

    /**
     * The bootstrap classes for the application.
     */
    protected array $bootstrappers = [];

    /**
     * Create a new HTTP kernel instance.
     */
    public function __construct(ApplicationInterface $app)
    {
        $this->app = $app;
    }

    /**
     * Handle an incoming HTTP request.
     */
    public function handle(RequestInterface $request): ResponseInterface
    {
        try {
            // Bootstrap the application if not already done
            $this->bootstrap();

            // Bind the request in the container
            $this->app->instance('request', $request);

            // Send the request through the middleware pipeline
            return $this->sendRequestThroughRouter($request);
        } catch (Throwable $e) {
            // Handle exceptions through the exception handler
            return $this->renderException($request, $e);
        }
    }

    /**
     * Send the request through the router middleware.
     */
    protected function sendRequestThroughRouter(RequestInterface $request): ResponseInterface
    {
        return (new Pipeline($this->app))
            ->send($request)
            ->through($this->app->shouldSkipMiddleware() ? [] : $this->middleware)
            ->then($this->dispatchToRouter());
    }

    /**
     * Get the route dispatcher closure.
     */
    protected function dispatchToRouter(): \Closure
    {
        return function (RequestInterface $request): ResponseInterface {
            $this->app->instance('request', $request);

            return $this->app->make('router')->dispatch($request);
        };
    }

    /**
     * Bootstrap the application for HTTP requests.
     */
    public function bootstrap(): void
    {
        if (!$this->app->hasBeenBootstrapped()) {
            $this->app->bootstrapWith($this->bootstrappers());
        }
    }

    /**
     * Get the bootstrap classes for the application.
     */
    public function bootstrappers(): array
    {
        return $this->bootstrappers;
    }

    /**
     * Perform any final actions for the request lifecycle.
     */
    public function terminate(RequestInterface $request, ResponseInterface $response): void
    {
        $this->terminateMiddleware($request, $response);

        $this->app->terminate();
    }

    /**
     * Call the terminate method on any terminable middleware.
     */
    protected function terminateMiddleware(RequestInterface $request, ResponseInterface $response): void
    {
        $middlewares = $this->app->shouldSkipMiddleware() ? [] : array_merge(
            $this->gatherRouteMiddleware($request),
            $this->middleware
        );

        foreach ($middlewares as $middleware) {
            if (!is_string($middleware)) {
                continue;
            }

            [$name] = $this->parseMiddleware($middleware);

            $instance = $this->app->make($name);

            if (method_exists($instance, 'terminate')) {
                $instance->terminate($request, $response);
            }
        }
    }

    /**
     * Gather the route middleware for the given request.
     */
    protected function gatherRouteMiddleware(RequestInterface $request): array
    {
        if ($route = $request->route()) {
            return $this->resolveMiddleware($route->gatherMiddleware());
        }

        return [];
    }

    /**
     * Resolve an array of middleware classes.
     */
    public function resolveMiddleware(array $middleware): array
    {
        $resolved = [];

        foreach ($middleware as $name) {
            if (isset($this->middlewareAliases[$name])) {
                $name = $this->middlewareAliases[$name];
            }

            if (isset($this->middlewareGroups[$name])) {
                $resolved = array_merge($resolved, $this->resolveMiddleware($this->middlewareGroups[$name]));
            } else {
                $resolved[] = $name;
            }
        }

        return $resolved;
    }

    /**
     * Parse a middleware string to get the name and parameters.
     */
    protected function parseMiddleware(string $middleware): array
    {
        [$name, $parameters] = array_pad(explode(':', $middleware, 2), 2, []);

        if (is_string($parameters)) {
            $parameters = explode(',', $parameters);
        }

        return [$name, $parameters];
    }

    /**
     * Render an exception to a response.
     */
    protected function renderException(RequestInterface $request, Throwable $e): ResponseInterface
    {
        return $this->app->make('Horizon\Exceptions\Handler')->render($request, $e);
    }

    /**
     * Add middleware to the global middleware stack.
     */
    public function pushMiddleware(string $middleware): static
    {
        if (!in_array($middleware, $this->middleware)) {
            $this->middleware[] = $middleware;
        }

        return $this;
    }

    /**
     * Prepend middleware to the global middleware stack.
     */
    public function prependMiddleware(string $middleware): static
    {
        if (!in_array($middleware, $this->middleware)) {
            array_unshift($this->middleware, $middleware);
        }

        return $this;
    }

    /**
     * Add a middleware group.
     */
    public function middlewareGroup(string $name, array $middleware): static
    {
        $this->middlewareGroups[$name] = $middleware;

        return $this;
    }

    /**
     * Register a middleware alias.
     */
    public function alias(string $alias, string $middleware): static
    {
        $this->middlewareAliases[$alias] = $middleware;

        return $this;
    }

    /**
     * Get the application's middleware groups.
     */
    public function getMiddlewareGroups(): array
    {
        return $this->middlewareGroups;
    }

    /**
     * Get the application's middleware aliases.
     */
    public function getMiddlewareAliases(): array
    {
        return $this->middlewareAliases;
    }

    /**
     * Get the global middleware.
     */
    public function getGlobalMiddleware(): array
    {
        return $this->middleware;
    }
}