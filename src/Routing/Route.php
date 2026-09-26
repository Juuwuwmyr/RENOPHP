<?php

declare(strict_types=1);

namespace Horizon\Routing;

use Horizon\Http\Request;
use Horizon\Http\Response;
use Closure;
use InvalidArgumentException;

/**
 * Route
 * 
 * Represents a single route with methods, URI pattern, action,
 * parameters, constraints, and middleware.
 */
class Route
{
    /**
     * HTTP methods for this route.
     */
    protected array $methods = [];

    /**
     * Route URI pattern.
     */
    protected string $uri;

    /**
     * Route action (closure, controller, etc.).
     */
    protected mixed $action;

    /**
     * Route parameters extracted from URI.
     */
    protected array $parameters = [];

    /**
     * Parameter constraints (where conditions).
     */
    protected array $wheres = [];

    /**
     * Route middleware.
     */
    protected array $middleware = [];

    /**
     * Route name.
     */
    protected ?string $name = null;

    /**
     * Route namespace.
     */
    protected ?string $namespace = null;

    /**
     * Route domain constraint.
     */
    protected ?string $domain = null;

    /**
     * Whether route has parameters.
     */
    protected ?bool $hasParameters = null;

    /**
     * Compiled route data.
     */
    protected ?array $compiled = null;

    /**
     * Create a new Route instance.
     */
    public function __construct(array $methods, string $uri, mixed $action)
    {
        $this->methods = array_map('strtoupper', $methods);
        $this->uri = $uri;
        $this->action = $this->parseAction($action);
    }

    // ====================================================================
    // Getters
    // ====================================================================

    /**
     * Get route methods.
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    /**
     * Get route URI.
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Get route action.
     */
    public function getAction(): mixed
    {
        return $this->action;
    }

    /**
     * Get route parameters.
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Get specific parameter.
     */
    public function getParameter(string $name, mixed $default = null): mixed
    {
        return $this->parameters[$name] ?? $default;
    }

    /**
     * Get route name.
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Get route middleware.
     */
    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * Get route namespace.
     */
    public function getNamespace(): ?string
    {
        return $this->namespace;
    }

    /**
     * Get route domain.
     */
    public function getDomain(): ?string
    {
        return $this->domain;
    }

    /**
     * Get where constraints.
     */
    public function getWheres(): array
    {
        return $this->wheres;
    }

    // ====================================================================
    // Setters (Fluent Interface)
    // ====================================================================

    /**
     * Set route parameters.
     */
    public function setParameters(array $parameters): static
    {
        $this->parameters = $parameters;
        return $this;
    }

    /**
     * Set specific parameter.
     */
    public function setParameter(string $name, mixed $value): static
    {
        $this->parameters[$name] = $value;
        return $this;
    }

    /**
     * Set route name.
     */
    public function name(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Add middleware to route.
     */
    public function middleware(string|array $middleware): static
    {
        $middleware = is_array($middleware) ? $middleware : func_get_args();
        $this->middleware = array_unique(array_merge($this->middleware, $middleware));
        return $this;
    }

    /**
     * Set route namespace.
     */
    public function namespace(string $namespace): static
    {
        $this->namespace = $namespace;
        return $this;
    }

    /**
     * Set route domain constraint.
     */
    public function domain(string $domain): static
    {
        $this->domain = $domain;
        return $this;
    }

    /**
     * Add where constraint for parameter.
     */
    public function where(string|array $name, ?string $expression = null): static
    {
        if (is_array($name)) {
            foreach ($name as $key => $value) {
                $this->wheres[$key] = $value;
            }
        } else {
            $this->wheres[$name] = $expression;
        }

        return $this;
    }

    /**
     * Add where constraint for alphabetic parameter.
     */
    public function whereAlpha(string $name): static
    {
        return $this->where($name, '[a-zA-Z]+');
    }

    /**
     * Add where constraint for numeric parameter.
     */
    public function whereNumber(string $name): static
    {
        return $this->where($name, '[0-9]+');
    }

    /**
     * Add where constraint for alphanumeric parameter.
     */
    public function whereAlphaNumeric(string $name): static
    {
        return $this->where($name, '[a-zA-Z0-9]+');
    }

    /**
     * Add where constraint for UUID parameter.
     */
    public function whereUuid(string $name): static
    {
        return $this->where($name, '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');
    }

    /**
     * Add where constraint for slug parameter.
     */
    public function whereSlug(string $name): static
    {
        return $this->where($name, '[a-z0-9-]+');
    }

    /**
     * Add where constraint that matches specific values.
     */
    public function whereIn(string $name, array $values): static
    {
        return $this->where($name, '(' . implode('|', array_map('preg_quote', $values)) . ')');
    }

    // ====================================================================
    // Route Matching
    // ====================================================================

    /**
     * Check if route matches request method.
     */
    public function matchesMethod(string $method): bool
    {
        return in_array(strtoupper($method), $this->methods);
    }

    /**
     * Check if route matches domain.
     */
    public function matchesDomain(?string $domain): bool
    {
        if ($this->domain === null) {
            return true;
        }

        return $this->domain === $domain;
    }

    /**
     * Check if parameter satisfies constraints.
     */
    public function matchesWhere(string $name, mixed $value): bool
    {
        if (!isset($this->wheres[$name])) {
            return true;
        }

        return preg_match('/^' . $this->wheres[$name] . '$/', (string) $value) === 1;
    }

    /**
     * Check if all parameters satisfy constraints.
     */
    public function satisfiesConstraints(): bool
    {
        foreach ($this->parameters as $name => $value) {
            if (!$this->matchesWhere($name, $value)) {
                return false;
            }
        }

        return true;
    }

    // ====================================================================
    // Route Analysis
    // ====================================================================

    /**
     * Check if route has parameters.
     */
    public function hasParameters(): bool
    {
        if ($this->hasParameters === null) {
            $this->hasParameters = str_contains($this->uri, '{');
        }

        return $this->hasParameters;
    }

    /**
     * Get parameter names from URI.
     */
    public function getParameterNames(): array
    {
        if (!$this->hasParameters()) {
            return [];
        }

        preg_match_all('/\{([^}]+)\}/', $this->uri, $matches);
        
        return array_map(function ($match) {
            return str_replace('?', '', $match);
        }, $matches[1] ?? []);
    }

    /**
     * Get optional parameter names.
     */
    public function getOptionalParameterNames(): array
    {
        if (!$this->hasParameters()) {
            return [];
        }

        preg_match_all('/\{([^}]+\?)\}/', $this->uri, $matches);
        
        return array_map(function ($match) {
            return str_replace('?', '', $match);
        }, $matches[1] ?? []);
    }

    /**
     * Check if route has middleware.
     */
    public function hasMiddleware(): bool
    {
        return !empty($this->middleware);
    }

    /**
     * Check if route is named.
     */
    public function isNamed(): bool
    {
        return $this->name !== null;
    }

    // ====================================================================
    // Route Execution
    // ====================================================================

    /**
     * Run the route action.
     */
    public function run(Request $request): mixed
    {
        if ($this->action instanceof Closure) {
            return $this->runClosure($request);
        }

        if (is_string($this->action)) {
            return $this->runController($request);
        }

        if (is_array($this->action) && count($this->action) === 2) {
            return $this->runControllerAction($request);
        }

        throw new InvalidArgumentException('Invalid route action type.');
    }

    /**
     * Run closure action.
     */
    protected function runClosure(Request $request): mixed
    {
        return call_user_func($this->action, $request, ...$this->parameters);
    }

    /**
     * Run controller string action.
     */
    protected function runController(Request $request): mixed
    {
        [$controller, $method] = $this->parseControllerAction($this->action);
        
        $controllerInstance = $this->resolveController($controller);
        
        return $controllerInstance->$method($request, ...$this->parameters);
    }

    /**
     * Run controller array action.
     */
    protected function runControllerAction(Request $request): mixed
    {
        [$controller, $method] = $this->action;
        
        if (is_string($controller)) {
            $controller = $this->resolveController($controller);
        }

        return $controller->$method($request, ...$this->parameters);
    }

    /**
     * Parse controller@method string.
     */
    protected function parseControllerAction(string $action): array
    {
        if (!str_contains($action, '@')) {
            throw new InvalidArgumentException("Controller action [{$action}] must contain '@' separator.");
        }

        return explode('@', $action, 2);
    }

    /**
     * Resolve controller instance.
     */
    protected function resolveController(string $controller): object
    {
        // Add namespace if set
        if ($this->namespace) {
            $controller = $this->namespace . '\\' . $controller;
        }

        // In a real implementation, this would use the container
        if (!class_exists($controller)) {
            throw new InvalidArgumentException("Controller [{$controller}] not found.");
        }

        return new $controller();
    }

    // ====================================================================
    // Route Compilation
    // ====================================================================

    /**
     * Get compiled route data.
     */
    public function getCompiled(): ?array
    {
        return $this->compiled;
    }

    /**
     * Set compiled route data.
     */
    public function setCompiled(array $compiled): static
    {
        $this->compiled = $compiled;
        return $this;
    }

    // ====================================================================
    // Helper Methods
    // ====================================================================

    /**
     * Parse action into consistent format.
     */
    protected function parseAction(mixed $action): mixed
    {
        if ($action instanceof Closure) {
            return $action;
        }

        if (is_string($action)) {
            if (str_contains($action, '@')) {
                return $action;
            }
            
            // Assume it's a controller with __invoke method
            return [$action, '__invoke'];
        }

        if (is_array($action) && count($action) === 2) {
            return $action;
        }

        if (is_callable($action)) {
            return $action;
        }

        throw new InvalidArgumentException('Route action must be a Closure, controller@method string, or callable array.');
    }

    /**
     * Convert route to array.
     */
    public function toArray(): array
    {
        return [
            'methods' => $this->methods,
            'uri' => $this->uri,
            'name' => $this->name,
            'action' => $this->formatActionForArray(),
            'middleware' => $this->middleware,
            'parameters' => $this->parameters,
            'wheres' => $this->wheres,
            'namespace' => $this->namespace,
            'domain' => $this->domain,
        ];
    }

    /**
     * Format action for array representation.
     */
    protected function formatActionForArray(): string
    {
        if ($this->action instanceof Closure) {
            return 'Closure';
        }

        if (is_string($this->action)) {
            return $this->action;
        }

        if (is_array($this->action)) {
            return implode('@', $this->action);
        }

        return 'Unknown';
    }

    /**
     * Convert route to string representation.
     */
    public function __toString(): string
    {
        $methods = implode('|', $this->methods);
        return "{$methods} {$this->uri}";
    }

    /**
     * Clone route.
     */
    public function __clone()
    {
        // Reset compiled data for cloned route
        $this->compiled = null;
    }
}