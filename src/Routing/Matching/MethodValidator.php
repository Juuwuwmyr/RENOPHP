<?php

declare(strict_types=1);

namespace Horizon\Routing\Matching;

use Horizon\Contracts\Http\RequestInterface;
use Horizon\Contracts\Routing\RouteInterface;

class MethodValidator implements ValidatorInterface
{
    /**
     * Validate a given rule against a route and request.
     */
    public function matches(RouteInterface $route, RequestInterface $request): bool
    {
        return in_array($request->method(), $route->methods());
    }
}