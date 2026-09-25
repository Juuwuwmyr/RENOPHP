<?php

declare(strict_types=1);

namespace Horizon\Routing;

use Horizon\Contracts\Http\RequestInterface;
use Horizon\Contracts\Routing\RouteInterface;
use Horizon\Contracts\Routing\UrlRoutableInterface;
use Horizon\Http\Exceptions\NotFoundHttpException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;

class ImplicitRouteBinding
{
    /**
     * Resolve the implicit route bindings for the given route.
     */
    public static function resolveForRoute(RouteInterface $route, RequestInterface $request): void
    {
        $parameters = static::getParameterBindings($route);

        foreach ($parameters as $parameterName => $parameterClass) {
            $parameterValue = $route->parameter($parameterName);

            if ($parameterValue === null) {
                continue;
            }

            $instance = static::resolveBinding($parameterClass, $parameterValue, $route, $parameterName);

            if ($instance !== null) {
                $route->setParameter($parameterName, $instance);
            }
        }
    }

    /**
     * Get the parameter bindings for a route.
     */
    protected static function getParameterBindings(RouteInterface $route): array
    {
        $action = $route->getAction();

        if (!isset($action['uses'])) {
            return [];
        }

        if ($action['uses'] instanceof \Closure) {
            $reflection = new ReflectionFunction($action['uses']);
        } elseif (is_string($action['uses']) && str_contains($action['uses'], '@')) {
            [$class, $method] = explode('@', $action['uses']);
            
            if (!class_exists($class) || !method_exists($class, $method)) {
                return [];
            }
            
            $reflection = new ReflectionMethod($class, $method);
        } else {
            return [];
        }

        $bindings = [];

        foreach ($reflection->getParameters() as $parameter) {
            if (!static::isImplicitlyBindable($parameter->getType())) {
                continue;
            }

            $class = $parameter->getType()->getName();

            if (static::isModel($class)) {
                $bindings[$parameter->getName()] = $class;
            }
        }

        return $bindings;
    }

    /**
     * Determine if the parameter type is implicitly bindable.
     */
    protected static function isImplicitlyBindable(?\ReflectionType $type): bool
    {
        return $type instanceof ReflectionNamedType && 
               !$type->isBuiltin() && 
               class_exists($type->getName());
    }

    /**
     * Determine if the class is a model.
     */
    protected static function isModel(string $class): bool
    {
        return is_subclass_of($class, UrlRoutableInterface::class);
    }

    /**
     * Resolve the binding for the given class and value.
     */
    protected static function resolveBinding(string $class, mixed $value, RouteInterface $route, string $parameterName): ?object
    {
        $instance = app()->make($class);

        if (!$instance instanceof UrlRoutableInterface) {
            return null;
        }

        $field = static::getBindingFieldName($route, $parameterName);

        $model = $instance->resolveRouteBinding($value, $field);

        if ($model === null && !static::isOptional($route, $parameterName)) {
            static::throwModelNotFound();
        }

        return $model;
    }

    /**
     * Get the binding field name for the parameter.
     */
    protected static function getBindingFieldName(RouteInterface $route, string $parameterName): ?string
    {
        $bindings = $route->getAction()['bindings'] ?? [];

        return $bindings[$parameterName] ?? null;
    }

    /**
     * Determine if the parameter is optional.
     */
    protected static function isOptional(RouteInterface $route, string $parameterName): bool
    {
        $compiled = $route->getCompiled();
        
        return in_array($parameterName, $compiled->getParameterNames()) &&
               array_key_exists($parameterName, $compiled->getOptionals());
    }

    /**
     * Throw a model not found exception.
     */
    protected static function throwModelNotFound(): never
    {
        throw new NotFoundHttpException('Model not found.');
    }
}