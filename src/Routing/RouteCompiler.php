<?php

declare(strict_types=1);

namespace Horizon\Routing;

use Horizon\Contracts\Routing\RouteInterface;

class RouteCompiler
{
    /**
     * The route instance.
     */
    protected RouteInterface $route;

    /**
     * Create a new route compiler instance.
     */
    public function __construct(RouteInterface $route)
    {
        $this->route = $route;
    }

    /**
     * Compile the route into a CompiledRoute instance.
     */
    public function compile(): CompiledRoute
    {
        $optionals = $this->getOptionalParameters();

        $uri = preg_replace('/\{(\w+?)\?\}/', '(?P<$1>[^/]++)?', $this->route->uri());

        return new CompiledRoute(
            $this->route,
            $this->replaceParameters($uri),
            array_keys($optionals),
            $optionals
        );
    }

    /**
     * Get the optional parameters for the route.
     */
    protected function getOptionalParameters(): array
    {
        preg_match_all('/\{(\w+)\?\}/', $this->route->uri(), $matches);

        return isset($matches[1]) ? array_fill_keys($matches[1], null) : [];
    }

    /**
     * Replace the route parameters in the given URI.
     */
    protected function replaceParameters(string $uri): string
    {
        return preg_replace_callback('/\{(\w+?)(\?)?\}/', function ($matches) {
            $optional = isset($matches[2]);
            $paramName = $matches[1];

            // Get constraints from route's where clauses
            $constraints = $this->route->wheres();
            $pattern = $constraints[$paramName] ?? '[^/]+';

            if ($optional) {
                return sprintf('(?P<%s>%s)?', $paramName, $pattern);
            }

            return sprintf('(?P<%s>%s)', $paramName, $pattern);
        }, $uri);
    }
}