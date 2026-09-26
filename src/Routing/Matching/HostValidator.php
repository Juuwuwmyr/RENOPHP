<?php

declare(strict_types=1);

namespace Reno\Routing\Matching;

use Reno\Contracts\Http\RequestInterface;
use Reno\Contracts\Routing\RouteInterface;

class HostValidator implements ValidatorInterface
{
    /**
     * Validate a given rule against a route and request.
     */
    public function matches(RouteInterface $route, RequestInterface $request): bool
    {
        $hostRegex = $route->getCompiled()->getHostRegex();

        if (is_null($hostRegex)) {
            return true;
        }

        return preg_match($hostRegex, $request->getHost());
    }
}