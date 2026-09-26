<?php

declare(strict_types=1);

namespace Reno\Routing\Matching;

use Reno\Contracts\Http\RequestInterface;
use Reno\Contracts\Routing\RouteInterface;

class UriValidator implements ValidatorInterface
{
    /**
     * Validate a given rule against a route and request.
     */
    public function matches(RouteInterface $route, RequestInterface $request): bool
    {
        $path = rawurldecode($request->path());

        return preg_match($route->getCompiled()->getRegex(), $path);
    }
}