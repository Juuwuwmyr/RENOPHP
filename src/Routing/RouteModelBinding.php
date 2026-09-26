<?php

declare(strict_types=1);

namespace Reno\Routing;

use Reno\Contracts\Foundation\ApplicationInterface;
use Reno\Contracts\Http\RequestInterface;
use Reno\Contracts\Routing\RouteInterface;
use Reno\Contracts\Routing\UrlRoutableInterface;
use Reno\Http\Exceptions\NotFoundHttpException;
use ReflectionClass;
use ReflectionParameter;

class RouteModelBinding
{
    /**
     * The application instance.
     */
    protected ApplicationInterface $container;

    /**
     * The registered model bindings.
     */
    protected array $bindings = [];

    /**
     * The registered model binding callbacks.
     */
    protected array $callbacks = [];

    /**
     * Create a new route model binding instance.
     */
    public function __construct(ApplicationInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Register a model binding.
     */
    public function bind(string $key, string $class, ?\Closure $callback = null): void
    {
        $this->bindings[$key] = compact('class', 'callback');
    }

    /**
     * Register a callback to resolve the binding.
     */
    public function resolveUsing(string $key, \Closure $callback): void
    {
        $this->callbacks[$key] = $callback;
    }

    /**
     * Resolve the route model binding.
     */
    public function resolveBinding(RouteInterface $route, RequestInterface $request): void
    {
        $parameters = $route->parameters();
        
        foreach ($this->getRouteParameterClasses($route) as $parameter => $class) {
            if (!isset($parameters[$parameter])) {
                continue;
            }

            $value = $parameters[$parameter];

            if ($value === null) {
                continue;
            }

            $resolved = $this->resolveParameter($parameter, $value, $class, $route);
            
            if ($resolved !== null) {
                $route->setParameter($parameter, $resolved);
            }
        }
    }

    /**
     * Get the parameter classes for the route.
     */
    protected function getRouteParameterClasses(RouteInterface $route): array
    {
        $action = $route->getAction();
        
        if (!isset($action['uses']) || !is_string($action['uses'])) {
            return [];
        }

        // Handle controller@method format
        if (str_contains($action['uses'], '@')) {
            [$controller, $method] = explode('@', $action['uses']);
            
            if (!class_exists($controller) || !method_exists($controller, $method)) {
                return [];
            }
            
            $reflection = new \ReflectionMethod($controller, $method);
        } elseif (is_callable($action['uses'])) {
            $reflection = new \ReflectionFunction($action['uses']);
        } else {
            return [];
        }

        $parameters = [];
        
        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();
            
            if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }
            
            $class = $type->getName();
            
            // Check if the class implements UrlRoutableInterface or has explicit binding
            if ($this->isBindable($class)) {
                $parameters[$parameter->getName()] = $class;
            }
        }

        return $parameters;
    }

    /**
     * Determine if the class is bindable.
     */
    protected function isBindable(string $class): bool
    {
        if (!class_exists($class)) {
            return false;
        }

        $reflection = new ReflectionClass($class);
        
        return $reflection->implementsInterface(UrlRoutableInterface::class) ||
               isset($this->bindings[class_basename($class)]) ||
               isset($this->callbacks[class_basename($class)]);
    }

    /**
     * Resolve a route parameter.
     */
    protected function resolveParameter(string $parameter, mixed $value, string $class, RouteInterface $route): ?object
    {
        $key = class_basename($class);

        // Check for explicit callback
        if (isset($this->callbacks[$key])) {
            return $this->callbacks[$key]($value, $route);
        }

        // Check for explicit binding
        if (isset($this->bindings[$key])) {
            $binding = $this->bindings[$key];
            
            if ($binding['callback']) {
                return $binding['callback']($value, $route);
            }
            
            $class = $binding['class'];
        }

        // Try to resolve using the model's route binding
        if (!class_exists($class)) {
            return null;
        }

        $instance = $this->container->make($class);
        
        if (!$instance instanceof UrlRoutableInterface) {
            return null;
        }

        $resolved = $instance->resolveRouteBinding($value, $this->getBindingField($route, $parameter));
        
        if ($resolved === null) {
            throw new NotFoundHttpException("No model found for parameter [{$parameter}] with value [{$value}]");
        }

        return $resolved;
    }

    /**
     * Get the binding field for the parameter.
     */
    protected function getBindingField(RouteInterface $route, string $parameter): ?string
    {
        $action = $route->getAction();
        
        return $action['bindings'][$parameter] ?? null;
    }

    /**
     * Get the registered bindings.
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Get the registered callbacks.
     */
    public function getCallbacks(): array
    {
        return $this->callbacks;
    }
}