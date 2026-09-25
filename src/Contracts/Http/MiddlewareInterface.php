<?php

declare(strict_types=1);

namespace Horizon\Contracts\Http;

interface MiddlewareInterface
{
    /**
     * Handle an incoming request.
     */
    public function handle(RequestInterface $request, \Closure $next): ResponseInterface;
}