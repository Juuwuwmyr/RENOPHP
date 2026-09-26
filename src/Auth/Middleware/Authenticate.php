<?php

namespace Reno\Auth\Middleware;

use Closure;

/**
 * Authenticate Middleware
 * 
 * Ensures the user is authenticated before accessing a route.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Clear authentication check
 * - Configurable guards
 * - Explicit redirects
 * 
 * Security:
 * - Prevents unauthorized access
 * - Preserves intended URL
 * - Supports multiple guards
 */
class Authenticate
{
    /**
     * Handle an incoming request
     *
     * @param mixed $request
     * @param Closure $next
     * @param string ...$guards
     * @return mixed
     */
    public function handle($request, Closure $next, ...$guards)
    {
        $this->authenticate($request, $guards);

        return $next($request);
    }

    /**
     * Determine if the user is logged in to any of the given guards
     *
     * @param mixed $request
     * @param array $guards
     * @return void
     * @throws \Reno\Auth\AuthenticationException
     */
    protected function authenticate($request, array $guards): void
    {
        if (empty($guards)) {
            $guards = [null];
        }

        foreach ($guards as $guard) {
            if (auth($guard)->check()) {
                auth()->setDefaultGuard($guard);
                return;
            }
        }

        $this->unauthenticated($request, $guards);
    }

    /**
     * Handle an unauthenticated user
     *
     * @param mixed $request
     * @param array $guards
     * @return void
     * @throws \Reno\Auth\AuthenticationException
     */
    protected function unauthenticated($request, array $guards): void
    {
        throw new \Reno\Auth\AuthenticationException(
            'Unauthenticated.',
            $guards,
            $this->redirectTo($request)
        );
    }

    /**
     * Get the path the user should be redirected to when they are not authenticated
     *
     * @param mixed $request
     * @return string|null
     */
    protected function redirectTo($request): ?string
    {
        // Check if it's an API request
        if ($request->expectsJson ?? false) {
            return null;
        }

        return '/login';
    }
}
