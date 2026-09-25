<?php

declare(strict_types=1);

namespace Horizon\Container;

use Closure;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;

class Util
{
    /**
     * Get the class name of the given parameter's type, if possible.
     */
    public static function getParameterClassName(ReflectionParameter $parameter): ?string
    {
        $type = $parameter->getType();

        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        $name = $type->getName();

        if (!is_null($class = $parameter->getDeclaringClass())) {
            if ($name === 'self') {
                return $class->getName();
            }

            if ($name === 'parent' && $parent = $class->getParentClass()) {
                return $parent->getName();
            }
        }

        return $name;
    }

    /**
     * Return the default value of the given value.
     */
    public static function unwrapIfClosure(mixed $value, mixed $container = null): mixed
    {
        return $value instanceof Closure ? $value($container) : $value;
    }
}