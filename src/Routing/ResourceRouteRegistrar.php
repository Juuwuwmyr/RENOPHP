<?php

declare(strict_types=1);

namespace Horizon\Routing;

use Horizon\Routing\RouteGroup;
use Horizon\Routing\Route;
use InvalidArgumentException;

/**
 * ResourceRouteRegistrar
 * 
 * Handles registration of RESTful resource routes with comprehensive
 * customization options and nested resource support.
 */
class ResourceRouteRegistrar
{
    /**
     * The route group instance.
     */
    protected RouteGroup $group;

    /**
     * Resource name.
     */
    protected string $name;

    /**
     * Controller class.
     */
    protected string $controller;

    /**
     * Registration options.
     */
    protected array $options;

    /**
     * Default resource actions.
     */
    protected static array $defaultActions = [
        'index' => ['GET', '', 'index'],
        'create' => ['GET', '/create', 'create'],
        'store' => ['POST', '', 'store'],
        'show' => ['GET', '/{id}', 'show'],
        'edit' => ['GET', '/{id}/edit', 'edit'],
        'update' => ['PUT', '/{id}', 'update'],
        'destroy' => ['DELETE', '/{id}', 'destroy'],
    ];

    /**
     * API resource actions (no create/edit forms).
     */
    protected static array $apiActions = [
        'index' => ['GET', '', 'index'],
        'store' => ['POST', '', 'store'],
        'show' => ['GET', '/{id}', 'show'],
        'update' => ['PUT', '/{id}', 'update'],
        'destroy' => ['DELETE', '/{id}', 'destroy'],
    ];

    /**
     * Custom action definitions.
     */
    protected array $customActions = [];

    /**
     * Parameter name for resource ID.
     */
    protected string $parameterName = 'id';

    /**
     * Create a new ResourceRouteRegistrar instance.
     */
    public function __construct(RouteGroup $group, string $name, string $controller, array $options = [])
    {
        $this->group = $group;
        $this->name = $name;
        $this->controller = $controller;
        $this->options = $options;

        $this->parseOptions();
    }

    // ====================================================================
    // Configuration Methods
    // ====================================================================

    /**
     * Limit routes to specific actions.
     */
    public function only(array $actions): static
    {
        $this->options['only'] = $actions;
        return $this;
    }

    /**
     * Exclude specific actions.
     */
    public function except(array $actions): static
    {
        $this->options['except'] = $actions;
        return $this;
    }

    /**
     * Set parameter name for resource ID.
     */
    public function parameter(string $parameter): static
    {
        $this->parameterName = $parameter;
        return $this;
    }

    /**
     * Add parameter constraints.
     */
    public function where(array $constraints): static
    {
        $this->options['where'] = array_merge($this->options['where'] ?? [], $constraints);
        return $this;
    }

    /**
     * Add middleware to resource routes.
     */
    public function middleware(string|array $middleware): static
    {
        if (!isset($this->options['middleware'])) {
            $this->options['middleware'] = [];
        }

        $middleware = is_array($middleware) ? $middleware : [$middleware];
        $this->options['middleware'] = array_merge($this->options['middleware'], $middleware);
        
        return $this;
    }

    /**
     * Set names for resource routes.
     */
    public function names(array $names): static
    {
        $this->options['names'] = array_merge($this->options['names'] ?? [], $names);
        return $this;
    }

    /**
     * Set name prefix.
     */
    public function as(string $prefix): static
    {
        $this->options['as'] = $prefix;
        return $this;
    }

    /**
     * Use shallow routing (no nested prefixes for show/edit/update/destroy).
     */
    public function shallow(): static
    {
        $this->options['shallow'] = true;
        return $this;
    }

    // ====================================================================
    // Custom Actions
    // ====================================================================

    /**
     * Add custom member action.
     */
    public function member(string $action, array $methods, string $controllerMethod = null): static
    {
        $controllerMethod = $controllerMethod ?: $action;
        
        $this->customActions['member'][$action] = [
            'methods' => $methods,
            'uri' => '/{id}/' . $action,
            'controller' => $controllerMethod,
        ];

        return $this;
    }

    /**
     * Add custom collection action.
     */
    public function collection(string $action, array $methods, string $controllerMethod = null): static
    {
        $controllerMethod = $controllerMethod ?: $action;
        
        $this->customActions['collection'][$action] = [
            'methods' => $methods,
            'uri' => '/' . $action,
            'controller' => $controllerMethod,
        ];

        return $this;
    }

    /**
     * Add multiple member actions.
     */
    public function members(array $actions): static
    {
        foreach ($actions as $action => $config) {
            if (is_string($config)) {
                // Simple string method
                $this->member($action, [$config]);
            } elseif (is_array($config)) {
                // Array with methods and optional controller method
                $methods = $config['methods'] ?? $config[0] ?? ['GET'];
                $controllerMethod = $config['controller'] ?? $config[1] ?? null;
                $this->member($action, $methods, $controllerMethod);
            }
        }

        return $this;
    }

    /**
     * Add multiple collection actions.
     */
    public function collections(array $actions): static
    {
        foreach ($actions as $action => $config) {
            if (is_string($config)) {
                // Simple string method
                $this->collection($action, [$config]);
            } elseif (is_array($config)) {
                // Array with methods and optional controller method
                $methods = $config['methods'] ?? $config[0] ?? ['GET'];
                $controllerMethod = $config['controller'] ?? $config[1] ?? null;
                $this->collection($action, $methods, $controllerMethod);
            }
        }

        return $this;
    }

    // ====================================================================
    // Nested Resources
    // ====================================================================

    /**
     * Register nested resource.
     */
    public function nested(string $nestedName, string $nestedController, array $options = [], callable $callback = null): static
    {
        $parentParam = $this->parameterName;
        $nestedParam = $options['parameter'] ?? 'id';

        // Build nested URI
        $nestedUri = "{{$parentParam}}/{$nestedName}";
        
        // Create nested group
        $this->group->group(['prefix' => $nestedUri], function (RouteGroup $group) use ($nestedName, $nestedController, $options, $callback) {
            $registrar = $group->resource($nestedName, $nestedController, $options);
            
            if ($callback) {
                $callback($registrar);
            }
        });

        return $this;
    }

    // ====================================================================
    // Route Registration
    // ====================================================================

    /**
     * Register all resource routes.
     */
    public function register(): void
    {
        $actions = $this->getActionsToRegister();
        
        foreach ($actions as $action => $config) {
            $this->registerAction($action, $config);
        }

        $this->registerCustomActions();
    }

    /**
     * Get actions to register based on options.
     */
    protected function getActionsToRegister(): array
    {
        $actions = $this->isApiResource() ? static::$apiActions : static::$defaultActions;

        if (isset($this->options['only'])) {
            $actions = array_intersect_key($actions, array_flip($this->options['only']));
        }

        if (isset($this->options['except'])) {
            $actions = array_diff_key($actions, array_flip($this->options['except']));
        }

        return $actions;
    }

    /**
     * Check if this is an API resource.
     */
    protected function isApiResource(): bool
    {
        return $this->options['api'] ?? false;
    }

    /**
     * Register a single resource action.
     */
    protected function registerAction(string $action, array $config): void
    {
        [$method, $uri, $controllerMethod] = $config;
        
        // Replace parameter placeholder
        $uri = str_replace('{id}', '{' . $this->parameterName . '}', $uri);
        
        // Build full URI
        $fullUri = $this->name . $uri;
        
        // Create route
        $route = $this->group->match([$method], $fullUri, $this->controller . '@' . $controllerMethod);
        
        // Set route name
        $routeName = $this->getRouteName($action);
        if ($routeName) {
            $route->name($routeName);
        }

        // Apply constraints
        if (isset($this->options['where'])) {
            $route->where($this->options['where']);
        }

        // Apply middleware
        if (isset($this->options['middleware'])) {
            $route->middleware($this->options['middleware']);
        }

        // Add default ID constraint for routes with ID parameter
        if (str_contains($uri, '{' . $this->parameterName . '}')) {
            $route->whereNumber($this->parameterName);
        }
    }

    /**
     * Register custom actions.
     */
    protected function registerCustomActions(): void
    {
        // Register member actions
        if (isset($this->customActions['member'])) {
            foreach ($this->customActions['member'] as $action => $config) {
                $uri = str_replace('{id}', '{' . $this->parameterName . '}', $this->name . $config['uri']);
                
                $route = $this->group->match($config['methods'], $uri, $this->controller . '@' . $config['controller']);
                $route->name($this->getCustomActionName($action, 'member'));
                
                if (isset($this->options['where'])) {
                    $route->where($this->options['where']);
                }
                
                $route->whereNumber($this->parameterName);
            }
        }

        // Register collection actions
        if (isset($this->customActions['collection'])) {
            foreach ($this->customActions['collection'] as $action => $config) {
                $uri = $this->name . $config['uri'];
                
                $route = $this->group->match($config['methods'], $uri, $this->controller . '@' . $config['controller']);
                $route->name($this->getCustomActionName($action, 'collection'));
                
                if (isset($this->options['where'])) {
                    $route->where($this->options['where']);
                }
            }
        }
    }

    /**
     * Get route name for action.
     */
    protected function getRouteName(string $action): string
    {
        $baseName = $this->options['as'] ?? $this->name;
        
        if (isset($this->options['names'][$action])) {
            return $this->options['names'][$action];
        }

        return $baseName . '.' . $action;
    }

    /**
     * Get custom action route name.
     */
    protected function getCustomActionName(string $action, string $type): string
    {
        $baseName = $this->options['as'] ?? $this->name;
        return $baseName . '.' . $action;
    }

    /**
     * Parse registration options.
     */
    protected function parseOptions(): void
    {
        // Set parameter name if provided
        if (isset($this->options['parameter'])) {
            $this->parameterName = $this->options['parameter'];
        }

        // Check if this is an API resource
        if (in_array('create', $this->options['except'] ?? []) && 
            in_array('edit', $this->options['except'] ?? [])) {
            $this->options['api'] = true;
        }

        // Set default constraints
        if (!isset($this->options['where'])) {
            $this->options['where'] = [];
        }
    }

    // ====================================================================
    // Utility Methods
    // ====================================================================

    /**
     * Get resource name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get controller class.
     */
    public function getController(): string
    {
        return $this->controller;
    }

    /**
     * Get registration options.
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Get parameter name.
     */
    public function getParameterName(): string
    {
        return $this->parameterName;
    }

    /**
     * Get custom actions.
     */
    public function getCustomActions(): array
    {
        return $this->customActions;
    }

    /**
     * Get all routes that will be registered.
     */
    public function getRoutes(): array
    {
        $routes = [];
        $actions = $this->getActionsToRegister();

        foreach ($actions as $action => $config) {
            [$method, $uri, $controllerMethod] = $config;
            $uri = str_replace('{id}', '{' . $this->parameterName . '}', $uri);
            
            $routes[$action] = [
                'method' => $method,
                'uri' => $this->name . $uri,
                'controller' => $this->controller . '@' . $controllerMethod,
                'name' => $this->getRouteName($action),
            ];
        }

        return $routes;
    }

    /**
     * Create multiple resource registrars.
     */
    public static function multiple(RouteGroup $group, array $resources): array
    {
        $registrars = [];

        foreach ($resources as $name => $config) {
            if (is_string($config)) {
                $controller = $config;
                $options = [];
            } else {
                $controller = $config['controller'] ?? $config[0];
                $options = $config['options'] ?? $config[1] ?? [];
            }

            $registrar = new static($group, $name, $controller, $options);
            $registrar->register();
            $registrars[$name] = $registrar;
        }

        return $registrars;
    }
}