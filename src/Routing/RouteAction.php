<?php

declare(strict_types=1);

namespace Reno\Routing;

use Closure;
use LogicException;
use UnexpectedValueException;

class RouteAction
{
    /**
     * Parse the given action into an array.
     */
    public static function parse(string $uri, mixed $action): array
    {
        // If no action is provided, we will make the URI the action and assume this
        // route simply returns the URI as-is when executed. This makes it much
        // more convenient to define simple routes that just return a simple view.
        if (is_null($action)) {
            return static::missingAction($uri);
        }

        // If the action is already a Closure instance, we will just set that instance
        // as the "uses" property, because there is nothing else we need to do when
        // it is available. Otherwise we will need to find it in the action list.
        if ($action instanceof Closure) {
            return ['uses' => $action];
        }

        // If it's an invokable object, we'll convert it to a closure
        if (is_object($action) && method_exists($action, '__invoke')) {
            return ['uses' => $action];
        }

        // If no "uses" property has been set, we will dig through the array to find a
        // Closure instance within this list. We will set the first Closure we come
        // across into the "uses" property that will get fired off by this route.
        if (is_array($action)) {
            if (isset($action['uses'])) {
                return static::makeAction($action);
            }

            return static::findCallable($action);
        }

        // If it's a string, we'll assume it's pointing to a controller
        if (is_string($action)) {
            return static::makeAction(['uses' => $action]);
        }

        throw new UnexpectedValueException('Invalid route action: ' . json_encode($action));
    }

    /**
     * Make an action array for the given action and parameters.
     */
    protected static function makeAction(array $action): array
    {
        if (!isset($action['uses'])) {
            $action = static::findCallable($action);
        }

        return $action;
    }

    /**
     * Find the callable in an action array.
     */
    protected static function findCallable(array $action): array
    {
        foreach ($action as $key => $value) {
            if ($value instanceof Closure || (is_object($value) && method_exists($value, '__invoke'))) {
                $action['uses'] = $value;
                unset($action[$key]);
                return $action;
            }
        }

        throw new UnexpectedValueException('Invalid route action.');
    }

    /**
     * Make an action for a route that has no explicit action.
     */
    protected static function missingAction(string $uri): array
    {
        return ['uses' => function () use ($uri) {
            throw new LogicException("Route for [{$uri}] has no action.");
        }];
    }

    /**
     * Determine if the given array actions contain a serialized Closure.
     */
    public static function containsSerializedClosure(array $action): bool
    {
        return is_string($action['uses']) && str_starts_with($action['uses'], 'C:');
    }
}