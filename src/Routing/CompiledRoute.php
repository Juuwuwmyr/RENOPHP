<?php

declare(strict_types=1);

namespace Horizon\Routing;

use Horizon\Contracts\Routing\RouteInterface;

class CompiledRoute
{
    /**
     * The route instance.
     */
    protected RouteInterface $route;

    /**
     * The compiled regex pattern.
     */
    protected string $regex;

    /**
     * The parameter names.
     */
    protected array $parameterNames;

    /**
     * The optional parameters.
     */
    protected array $optionals;

    /**
     * The host regex pattern.
     */
    protected ?string $hostRegex = null;

    /**
     * Create a new compiled route instance.
     */
    public function __construct(RouteInterface $route, string $regex, array $parameterNames, array $optionals, ?string $hostRegex = null)
    {
        $this->route = $route;
        $this->regex = $regex;
        $this->parameterNames = $parameterNames;
        $this->optionals = $optionals;
        $this->hostRegex = $hostRegex;
    }

    /**
     * Get the route instance.
     */
    public function getRoute(): RouteInterface
    {
        return $this->route;
    }

    /**
     * Get the compiled regex pattern.
     */
    public function getRegex(): string
    {
        return '#^' . $this->regex . '$#u';
    }

    /**
     * Get the parameter names.
     */
    public function getParameterNames(): array
    {
        return $this->parameterNames;
    }

    /**
     * Get the optional parameters.
     */
    public function getOptionals(): array
    {
        return $this->optionals;
    }

    /**
     * Get the host regex pattern.
     */
    public function getHostRegex(): ?string
    {
        return $this->hostRegex;
    }

    /**
     * Test if the route matches the given path.
     */
    public function matches(string $path): bool
    {
        return preg_match($this->getRegex(), $path);
    }

    /**
     * Extract parameters from the given path.
     */
    public function extractParameters(string $path): array
    {
        if (!preg_match($this->getRegex(), $path, $matches)) {
            return [];
        }

        $parameters = [];

        foreach ($matches as $key => $value) {
            if (is_string($key) && $value !== '') {
                $parameters[$key] = $value;
            }
        }

        // Fill in optional parameters that weren't matched
        foreach ($this->optionals as $name => $default) {
            if (!isset($parameters[$name])) {
                $parameters[$name] = $default;
            }
        }

        return $parameters;
    }
}