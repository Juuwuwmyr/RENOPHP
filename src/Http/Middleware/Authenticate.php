<?php

declare(strict_types=1);

namespace Horizon\Http\Middleware;

use Horizon\Contracts\Http\MiddlewareInterface;
use Horizon\Contracts\Http\RequestInterface;
use Horizon\Contracts\Http\ResponseInterface;
use Horizon\Http\Exceptions\HttpException;

class Authenticate implements MiddlewareInterface
{
    /**
     * The authentication guards.
     */
    protected array $guards;

    /**
     * Create a new authenticate middleware instance.
     */
    public function __construct(array $guards = [])
    {
        $this->guards = $guards;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(RequestInterface $request, \Closure $next, ...$guards): ResponseInterface
    {
        $this->authenticate($request, $guards ?: $this->guards);

        return $next($request);
    }

    /**
     * Determine if the user is logged in to any of the given guards.
     */
    protected function authenticate(RequestInterface $request, array $guards): void
    {
        if (empty($guards)) {
            $guards = [null];
        }

        foreach ($guards as $guard) {
            if ($this->auth($guard)->check()) {
                return $this->auth($guard)->setRequest($request);
            }
        }

        $this->unauthenticated($request, $guards);
    }

    /**
     * Handle an unauthenticated user.
     */
    protected function unauthenticated(RequestInterface $request, array $guards): void
    {
        throw new HttpException(401, 'Unauthenticated.');
    }

    /**
     * Get the auth instance.
     */
    protected function auth(?string $guard = null)
    {
        return app('auth')->guard($guard);
    }
}