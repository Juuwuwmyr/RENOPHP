<?php

namespace Horizon\Auth\Middleware;

use Closure;

/**
 * RedirectIfAuthenticated Middleware
 * 
 * Redirects authenticated users away from guest-only routes.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Clear guest-only protection
 * - Configurable redirect
 * - Multiple guard support
 * 
 * Use Cases:
 * - Login page (authenticated users shouldn't see it)
 * - Registration page
 * - Password reset page
 */
class RedirectIfAuthenticated
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
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (auth($guard)->check()) {
                return $this->redirectTo($request);
            }
        }

        return $next($request);
    }

    /**
     * Get the redirect destination
     *
     * @param mixed $request
     * @return mixed
     */
    protected function redirectTo($request)
    {
        // Return redirect response (framework-specific)
        // For now, return a simple redirect indicator
        return '/dashboard';
    }
}
