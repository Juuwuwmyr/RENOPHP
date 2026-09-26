<?php

namespace Reno\Auth\Middleware;

use Closure;
use Reno\Auth\Access\AuthorizationException;

/**
 * Authorize Middleware
 * 
 * Authorizes requests based on abilities.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Clear authorization check
 * - Ability-based access
 * - Customizable denial response
 * 
 * Usage:
 *   Route::middleware('can:update,post')->...
 */
class Authorize
{
    /**
     * Handle an incoming request
     *
     * @param mixed $request
     * @param Closure $next
     * @param string $ability
     * @param mixed ...$models
     * @return mixed
     * @throws AuthorizationException
     */
    public function handle($request, Closure $next, string $ability, ...$models)
    {
        // Resolve models from route parameters
        $models = $this->resolveModels($request, $models);

        // Check authorization
        if (!gate()->allows($ability, $models)) {
            throw new AuthorizationException(
                "You are not authorized to {$ability} this resource."
            );
        }

        return $next($request);
    }

    /**
     * Resolve models from route parameters
     *
     * @param mixed $request
     * @param array $models
     * @return array
     */
    protected function resolveModels($request, array $models): array
    {
        return array_map(function ($model) use ($request) {
            // If it's already an object, return it
            if (is_object($model)) {
                return $model;
            }

            // Try to resolve from route parameters
            return $request->route($model) ?? $model;
        }, $models);
    }
}
