<?php

declare(strict_types=1);

namespace Reno\Routing\Matching;

use Reno\Contracts\Http\RequestInterface;
use Reno\Contracts\Routing\RouteInterface;

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