<?php

declare(strict_types=1);

namespace Horizon\Middleware;

use Horizon\Http\Request;
use Horizon\Http\Response;
use Closure;

/**
 * MiddlewareInterface
 * 
 * Contract for all middleware in the Horizon Framework.
 * Middleware can process requests before they reach the route handler
 * and responses before they are sent to the client.
 */
interface MiddlewareInterface
{
    /**
     * Handle an incoming request.
     * 
     * @param Request $request The incoming request
     * @param Closure $next The next middleware in the pipeline
     * @return Response The response after processing
     */
    public function handle(Request $request, Closure $next): Response;
}