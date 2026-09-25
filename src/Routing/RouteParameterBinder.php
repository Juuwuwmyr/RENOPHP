<?php

declare(strict_types=1);

namespace Horizon\Routing;

use Horizon\Contracts\Http\RequestInterface;
use Horizon\Contracts\Routing\RouteInterface;

class RouteParameterBinder
{
    /**
     * The route instance.
     */
    protected RouteInterface $route;

    /**
     * Create a new route parameter binder instance.
     */
    public function __construct(RouteInterface $route)
    {
        $this->route = $route;
    }

    /**
     * Get the parameters for the route.
     */
    public function parameters(RequestInterface $request): array
    {
        $parameters = $this->bindPathParameters($request);

        // If the route has a regular expression for the host part of the URI, we will
        // compile that and get the parameter matches for this domain. This way we
        // will be able to obtain the parameters just like we do for the path.
        if (!is_null($this->route->getCompiled()->getHostRegex())) {
            $parameters = $this->bindHostParameters(
                $request, $parameters
            );
        }

        return $this->replaceDefaults($parameters);
    }

    /**
     * Get the parameter matches for the path portion of the URI.
     */
    protected function bindPathParameters(RequestInterface $request): array
    {
        $compiled = $this->route->getCompiled();
        
        return $compiled->extractParameters(
            rawurldecode($request->path())
        );
    }

    /**
     * Get the parameter matches for the host portion of the URI.
     */
    protected function bindHostParameters(RequestInterface $request, array $parameters): array
    {
        preg_match($this->route->getCompiled()->getHostRegex(), $request->getHost(), $matches);

        foreach ($matches as $key => $value) {
            if (is_string($key) && $value !== '') {
                $parameters[$key] = $value;
            }
        }

        return $parameters;
    }

    /**
     * Replace null parameters with their defaults.
     */
    protected function replaceDefaults(array $parameters): array
    {
        foreach ($parameters as $key => $value) {
            $parameters[$key] = $value ?? $this->route->defaults[$key] ?? null;
        }

        foreach ($this->route->defaults as $key => $value) {
            if (!isset($parameters[$key])) {
                $parameters[$key] = $value;
            }
        }

        return $parameters;
    }
}