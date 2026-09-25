<?php

declare(strict_types=1);

namespace Horizon\Routing;

use Horizon\Contracts\Foundation\ApplicationInterface;
use Horizon\Contracts\Http\RequestInterface;
use Horizon\Contracts\Routing\RouteInterface;
use Horizon\Container\BoundMethod;
use Horizon\Http\Middleware\Pipeline;
use ReflectionClass;
use ReflectionMethod;

class ControllerDispatcher
{
    /**
     * The container instance.
     */
    protected ApplicationInterface $container;

    /**
     * Create a new controller dispatcher instance.
     */
    public function __construct(ApplicationInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Dispatch a request to a controller method.
     */
    public function dispatch(RouteInterface $route, RequestInterface $request, string $controller, string $method): mixed
    {
        return $this->callWithinStack(
            $this->makeController($controller),
            $route,
            $request,
            $method
        );
    }

    /**
     * Make a controller instance via the IoC container.
     */
    protected function makeController(string $controller): object
    {
        $controller = $this->getController($controller);

        return $this->container->make(ltrim($controller, '\\'));
    }

    /**
     * Get the fully qualified controller class name.
     */
    protected function getController(string $controller): string
    {
        if (!str_contains($controller, '\\')) {
            // If no namespace provided, assume it's in the default controller namespace
            $namespace = $this->getControllerNamespace();
            return $namespace . '\\' . $controller;
        }

        return $controller;
    }

    /**
     * Get the default controller namespace.
     */
    protected function getControllerNamespace(): string
    {
        return 'App\\Http\\Controllers';
    }

    /**
     * Call the controller method within the middleware stack.
     */
    protected function callWithinStack(object $controller, RouteInterface $route, RequestInterface $request, string $method): mixed
    {
        $shouldSkipMiddleware = $this->container->bound('middleware.disable') &&
                               $this->container->make('middleware.disable') === true;

        if ($shouldSkipMiddleware) {
            return $this->callController($controller, $method, $route, $request);
        }

        $middleware = $this->getMiddleware($controller, $method);

        return (new Pipeline($this->container))
            ->send($request)
            ->through($middleware)
            ->then(function ($request) use ($controller, $method, $route) {
                return $this->callController($controller, $method, $route, $request);
            });
    }

    /**
     * Call the controller method.
     */
    protected function callController(object $controller, string $method, RouteInterface $route, RequestInterface $request): mixed
    {
        $parameters = $this->resolveMethodDependencies(
            $controller, $method, $route->parametersWithoutNulls(), $request
        );

        return BoundMethod::call(
            $this->container,
            [$controller, $method],
            $parameters
        );
    }

    /**
     * Get the middleware for the controller instance.
     */
    public function getMiddleware(object|string $controller, string $method): array
    {
        if (is_string($controller)) {
            $controller = $this->makeController($controller);
        }

        if (!method_exists($controller, 'getMiddleware')) {
            return [];
        }

        return $controller->getMiddleware();
    }

    /**
     * Resolve the object method's type-hinted dependencies.
     */
    public function resolveMethodDependencies(object $controller, string $method, array $parameters, RequestInterface $request): array
    {
        $reflectionMethod = new ReflectionMethod($controller, $method);

        return $this->resolveMethodParameters($reflectionMethod, $parameters, $request);
    }

    /**
     * Resolve method parameters from reflection.
     */
    protected function resolveMethodParameters(ReflectionMethod $method, array $parameters, RequestInterface $request): array
    {
        $resolved = [];

        foreach ($method->getParameters() as $parameter) {
            $name = $parameter->getName();
            $type = $parameter->getType();

            // If parameter has a value in route parameters, use it
            if (array_key_exists($name, $parameters)) {
                $resolved[] = $parameters[$name];
                continue;
            }

            // If parameter is type-hinted, try to resolve from container
            if ($type && !$type->isBuiltin()) {
                $typeName = $type->getName();
                
                // Special case for Request injection
                if ($typeName === RequestInterface::class || is_subclass_of($typeName, RequestInterface::class)) {
                    $resolved[] = $request;
                    continue;
                }

                // Resolve from container
                if ($this->container->bound($typeName)) {
                    $resolved[] = $this->container->make($typeName);
                    continue;
                }

                // Try to auto-resolve the class
                try {
                    $resolved[] = $this->container->make($typeName);
                    continue;
                } catch (\Exception $e) {
                    // Fall through to default handling
                }
            }

            // If parameter has default value, use it
            if ($parameter->isDefaultValueAvailable()) {
                $resolved[] = $parameter->getDefaultValue();
                continue;
            }

            // If parameter is optional (nullable), pass null
            if ($parameter->allowsNull()) {
                $resolved[] = null;
                continue;
            }

            // If we can't resolve the parameter, throw an exception
            throw new \InvalidArgumentException(
                "Unable to resolve parameter [{$name}] for method [{$method->getDeclaringClass()->getName()}::{$method->getName()}]"
            );
        }

        return $resolved;
    }

    /**
     * Get the class name of the given controller instance or class.
     */
    protected function getControllerClass(object|string $controller): string
    {
        if (is_string($controller)) {
            return $controller;
        }

        return get_class($controller);
    }

    /**
     * Determine if the controller uses the given trait.
     */
    protected function controllerUsesTrait(object|string $controller, string $trait): bool
    {
        $class = is_string($controller) ? $controller : get_class($controller);

        $reflection = new ReflectionClass($class);
        $traits = $reflection->getTraitNames();

        return in_array($trait, $traits, true);
    }
}