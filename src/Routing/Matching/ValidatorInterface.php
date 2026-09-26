<?php

declare(strict_types=1);

namespace Reno\Routing\Matching;

use Reno\Contracts\Http\RequestInterface;
use Reno\Contracts\Routing\RouteInterface;

interface ValidatorInterface
{
    /**
     * Validate a given rule against a route and request.
     */
    public function matches(RouteInterface $route, RequestInterface $request): bool;
}