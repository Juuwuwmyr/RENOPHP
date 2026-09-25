<?php

declare(strict_types=1);

namespace Horizon\Container;

use Closure;
use InvalidArgumentException;
use ReflectionFunction;
use ReflectionMethod;

class BoundMethod
{
    /**
     * Call the given Closure / class@method and inject its dependencies.
     */
    public static function call(Container $container, callable|string $callback, array $parameters = [], ?string $defaultMethod = null): mixed
    {
        if (static::isCallableWithAtSign($callback) || $defaultMethod) {
            return static::callClass($container, $callback, $parameters, $defaultMethod);
        }

        return static::callBoundMethod($container, $callback, function () use ($container, $callback, $parameters) {
            return $callback(...array_values(static::getMethodDependencies($container, $callback, $parameters)));
        });
    }

    /**
     * Call a string reference to a class using Class@method syntax.
     */
    protected static function callClass(Container $container, string $target, array $parameters = [], ?string $defaultMethod = null): mixed
    {
        $segments = explode('@', $target);

        $method = count($segments) === 2
                        ? $segments[1] : $defaultMethod;

        if (is_null($method)) {
            throw new InvalidArgumentException('Method not provided.');
        }

        return static::call(
            $container, [$container->make($segments[0]), $method], $parameters
        );
    }

    /**
     * Call a method that has been bound to the container.
     */
    protected static function callBoundMethod(Container $container, callable $callback, Closure $default): mixed
    {
        if (!is_array($callback)) {
            return $default();
        }

        $method = static::normalizeMethod($callback);

        if ($container->hasMethodBinding($method)) {
            return $container->callMethodBinding($method, $callback[0]);
        }

        return $default();
    }

    /**
     * Normalize the given callback into a Class@method string.
     */
    protected static function normalizeMethod(callable $callback): string
    {
        $class = is_string($callback[0]) ? $callback[0] : get_class($callback[0]);

        return "{$class}@{$callback[1]}";
    }

    /**
     * Get all dependencies for a given method.
     */
    protected static function getMethodDependencies(Container $container, callable $callback, array $parameters = []): array
    {
        $dependencies = [];

        foreach (static::getCallReflector($callback)->getParameters() as $parameter) {
            static::addDependencyForCallParameter($container, $parameter, $parameters, $dependencies);
        }

        return array_merge($dependencies, array_values($parameters));
    }

    /**
     * Get the proper reflection instance for the given callback.
     */
    protected static function getCallReflector(callable|string $callback): ReflectionFunction|ReflectionMethod
    {
        if (is_string($callback) && strpos($callback, '::') !== false) {
            $callback = explode('::', $callback);
        } elseif (is_string($callback) && strpos($callback, '@') !== false) {
            $callback = explode('@', $callback);
        }

        return is_array($callback)
                        ? new ReflectionMethod($callback[0], $callback[1])
                        : new ReflectionFunction($callback);
    }

    /**
     * Get the dependency for the given call parameter.
     */
    protected static function addDependencyForCallParameter(Container $container, \ReflectionParameter $parameter, array &$parameters, array &$dependencies): void
    {
        if (array_key_exists($parameter->name, $parameters)) {
            $dependencies[] = $parameters[$parameter->name];

            unset($parameters[$parameter->name]);
        } elseif (!is_null($className = Util::getParameterClassName($parameter))) {
            if (array_key_exists($className, $parameters)) {
                $dependencies[] = $parameters[$className];

                unset($parameters[$className]);
            } else {
                $dependencies[] = $container->make($className);
            }
        } elseif ($parameter->isDefaultValueAvailable()) {
            $dependencies[] = $parameter->getDefaultValue();
        } else {
            throw new InvalidArgumentException("Unable to resolve dependency [{$parameter}] in class {$parameter->getDeclaringClass()->getName()}");
        }
    }

   
    protected static function isCallableWithAtSign(mixed $callback): bool
    {
        return is_string($callback) && strpos($callback, '@') !== false;
    }
}