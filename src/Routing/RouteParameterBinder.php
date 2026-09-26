<?php

declare(strict_types=1);

namespace Reno\Routing;

use Reno\Http\Request;
use Reno\Routing\Route;
use Reno\Routing\Exceptions\ModelNotFoundException;
use Closure;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use InvalidArgumentException;

/**
 * RouteParameterBinder
 * 
 * Handles parameter binding for routes including model binding,
 * dependency injection, and custom parameter resolvers.
 */
class RouteParameterBinder
{
    /**
     * Model bindings registry.
     */
    protected array $bindings = [];

    /**
     * Custom parameter resolvers.
     */
    protected array $resolvers = [];

    /**
     * Parameter transformers.
     */
    protected array $transformers = [];

    /**
     * Container resolver for dependency injection.
     */
    protected ?Closure $containerResolver = null;

    /**
     * Create a new RouteParameterBinder instance.
     */
    public function __construct(?Closure $containerResolver = null)
    {
        $this->containerResolver = $containerResolver;
    }

    // ====================================================================
    // Model Bindings
    // ====================================================================

    /**
     * Register a model binding.
     */
    public function model(string $key, string $class, ?Closure $callback = null): void
    {
        $this->bindings[$key] = [
            'type' => 'model',
            'class' => $class,
            'callback' => $callback,
        ];
    }

    /**
     * Register a custom binding resolver.
     */
    public function bind(string $key, Closure $resolver): void
    {
        $this->bindings[$key] = [
            'type' => 'custom',
            'resolver' => $resolver,
        ];
    }

    /**
     * Register implicit model binding.
     */
    public function implicitModel(string $class, string $field = 'id'): void
    {
        $key = $this->getImplicitBindingKey($class);
        
        $this->bindings[$key] = [
            'type' => 'implicit',
            'class' => $class,
            'field' => $field,
        ];
    }

    /**
     * Get implicit binding key from class name.
     */
    protected function getImplicitBindingKey(string $class): string
    {
        $parts = explode('\\', $class);
        $className = end($parts);
        
        // Convert PascalCase to snake_case
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className));
    }

    // ====================================================================
    // Parameter Transformers
    // ====================================================================

    /**
     * Register a parameter transformer.
     */
    public function transformer(string $key, Closure $transformer): void
    {
        $this->transformers[$key] = $transformer;
    }

    /**
     * Register multiple transformers.
     */
    public function transformers(array $transformers): void
    {
        foreach ($transformers as $key => $transformer) {
            $this->transformer($key, $transformer);
        }
    }

    // ====================================================================
    // Parameter Resolution
    // ====================================================================

    /**
     * Resolve route parameters.
     */
    public function resolveParameters(Route $route, Request $request): array
    {
        $parameters = $route->getParameters();
        $resolved = [];

        foreach ($parameters as $key => $value) {
            $resolved[$key] = $this->resolveParameter($key, $value, $route, $request);
        }

        return $resolved;
    }

    /**
     * Resolve a single parameter.
     */
    protected function resolveParameter(string $key, mixed $value, Route $route, Request $request): mixed
    {
        // Apply transformers first
        if (isset($this->transformers[$key])) {
            $value = ($this->transformers[$key])($value, $route, $request);
        }

        // Check for explicit bindings
        if (isset($this->bindings[$key])) {
            return $this->resolveBinding($key, $value, $this->bindings[$key], $route, $request);
        }

        // Check for implicit model bindings
        $implicitBinding = $this->findImplicitBinding($key, $value);
        if ($implicitBinding) {
            return $this->resolveBinding($key, $value, $implicitBinding, $route, $request);
        }

        // Return raw value if no binding found
        return $value;
    }

    /**
     * Resolve a binding.
     */
    protected function resolveBinding(string $key, mixed $value, array $binding, Route $route, Request $request): mixed
    {
        switch ($binding['type']) {
            case 'model':
                return $this->resolveModelBinding($key, $value, $binding, $route, $request);
                
            case 'implicit':
                return $this->resolveImplicitBinding($key, $value, $binding, $route, $request);
                
            case 'custom':
                return $binding['resolver']($value, $route, $request);
                
            default:
                throw new InvalidArgumentException("Unknown binding type: {$binding['type']}");
        }
    }

    /**
     * Resolve model binding.
     */
    protected function resolveModelBinding(string $key, mixed $value, array $binding, Route $route, Request $request): mixed
    {
        $class = $binding['class'];
        $callback = $binding['callback'];

        if ($callback) {
            $result = $callback($value, $route, $request);
        } else {
            $result = $this->findModel($class, $value);
        }

        if ($result === null) {
            throw new ModelNotFoundException("No model found for {$class} with value: {$value}");
        }

        return $result;
    }

    /**
     * Resolve implicit model binding.
     */
    protected function resolveImplicitBinding(string $key, mixed $value, array $binding, Route $route, Request $request): mixed
    {
        $class = $binding['class'];
        $field = $binding['field'];

        $result = $this->findModel($class, $value, $field);

        if ($result === null) {
            throw new ModelNotFoundException("No {$class} found with {$field}: {$value}");
        }

        return $result;
    }

    /**
     * Find model instance.
     */
    protected function findModel(string $class, mixed $value, string $field = 'id'): ?object
    {
        if (!class_exists($class)) {
            throw new InvalidArgumentException("Model class {$class} does not exist");
        }

        // Try to find the model using a find method
        if (method_exists($class, 'find')) {
            return $class::find($value);
        }

        // Try to find using a where method
        if (method_exists($class, 'where')) {
            return $class::where($field, $value)->first();
        }

        // Try to instantiate and find manually (basic example)
        $instance = new $class();
        if (method_exists($instance, 'findBy')) {
            return $instance->findBy($field, $value);
        }

        return null;
    }

    /**
     * Find implicit binding for parameter.
     */
    protected function findImplicitBinding(string $key, mixed $value): ?array
    {
        foreach ($this->bindings as $bindingKey => $binding) {
            if ($binding['type'] === 'implicit' && $bindingKey === $key) {
                return $binding;
            }
        }

        return null;
    }

    // ====================================================================
    // Dependency Injection
    // ====================================================================

    /**
     * Resolve method dependencies for route action.
     */
    public function resolveMethodDependencies(Route $route, Request $request, array $parameters = []): array
    {
        $action = $route->getAction();
        
        if ($action instanceof Closure) {
            return $this->resolveClosureDependencies($action, $request, $parameters);
        }

        if (is_string($action) && str_contains($action, '@')) {
            [$controller, $method] = explode('@', $action, 2);
            return $this->resolveControllerDependencies($controller, $method, $request, $parameters);
        }

        if (is_array($action) && count($action) === 2) {
            [$controller, $method] = $action;
            return $this->resolveControllerDependencies($controller, $method, $request, $parameters);
        }

        return $parameters;
    }

    /**
     * Resolve closure dependencies.
     */
    protected function resolveClosureDependencies(Closure $closure, Request $request, array $parameters): array
    {
        $reflection = new \ReflectionFunction($closure);
        return $this->resolveDependenciesFromReflection($reflection->getParameters(), $request, $parameters);
    }

    /**
     * Resolve controller method dependencies.
     */
    protected function resolveControllerDependencies(string|object $controller, string $method, Request $request, array $parameters): array
    {
        if (is_string($controller)) {
            if (!class_exists($controller)) {
                throw new InvalidArgumentException("Controller {$controller} does not exist");
            }
            $reflection = new ReflectionClass($controller);
        } else {
            $reflection = new ReflectionClass($controller);
        }

        if (!$reflection->hasMethod($method)) {
            throw new InvalidArgumentException("Method {$method} does not exist on controller");
        }

        $methodReflection = $reflection->getMethod($method);
        return $this->resolveDependenciesFromReflection($methodReflection->getParameters(), $request, $parameters);
    }

    /**
     * Resolve dependencies from reflection parameters.
     */
    protected function resolveDependenciesFromReflection(array $reflectionParameters, Request $request, array $parameters): array
    {
        $resolved = [];
        $parameterIndex = 0;

        foreach ($reflectionParameters as $parameter) {
            $name = $parameter->getName();
            $type = $parameter->getType();

            // If parameter exists in route parameters, use it
            if (array_key_exists($name, $parameters)) {
                $resolved[] = $parameters[$name];
                continue;
            }

            // If parameter is the Request, inject it
            if ($type && $type->getName() === Request::class) {
                $resolved[] = $request;
                continue;
            }

            // Try to resolve via container
            if ($type && !$type->isBuiltin() && $this->containerResolver) {
                $dependency = ($this->containerResolver)($type->getName());
                if ($dependency !== null) {
                    $resolved[] = $dependency;
                    continue;
                }
            }

            // Use positional parameters
            if (isset(array_values($parameters)[$parameterIndex])) {
                $resolved[] = array_values($parameters)[$parameterIndex];
                $parameterIndex++;
                continue;
            }

            // Use default value if available
            if ($parameter->isDefaultValueAvailable()) {
                $resolved[] = $parameter->getDefaultValue();
                continue;
            }

            // Parameter is required but not provided
            throw new InvalidArgumentException("Cannot resolve parameter: {$name}");
        }

        return $resolved;
    }

    // ====================================================================
    // Constraint Validation
    // ====================================================================

    /**
     * Validate parameter constraints.
     */
    public function validateConstraints(Route $route, array $parameters): bool
    {
        $wheres = $route->getWheres();

        foreach ($parameters as $name => $value) {
            if (isset($wheres[$name])) {
                if (!$this->validateConstraint($name, $value, $wheres[$name])) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Validate a single constraint.
     */
    protected function validateConstraint(string $name, mixed $value, string $constraint): bool
    {
        // Handle special constraint types
        if (str_starts_with($constraint, 'exists:')) {
            return $this->validateExistsConstraint($value, $constraint);
        }

        if (str_starts_with($constraint, 'unique:')) {
            return $this->validateUniqueConstraint($value, $constraint);
        }

        // Regular expression constraint
        return preg_match('/^' . $constraint . '$/', (string) $value) === 1;
    }

    /**
     * Validate exists constraint.
     */
    protected function validateExistsConstraint(mixed $value, string $constraint): bool
    {
        // Parse exists:table,column format
        $parts = explode(':', $constraint, 2);
        if (count($parts) !== 2) {
            return false;
        }

        [$table, $column] = explode(',', $parts[1], 2);
        $column = $column ?: 'id';

        // In a real implementation, this would query the database
        // For now, we'll simulate it
        return !empty($value);
    }

    /**
     * Validate unique constraint.
     */
    protected function validateUniqueConstraint(mixed $value, string $constraint): bool
    {
        // Parse unique:table,column,except format
        $parts = explode(':', $constraint, 2);
        if (count($parts) !== 2) {
            return false;
        }

        $constraintParts = explode(',', $parts[1]);
        $table = $constraintParts[0];
        $column = $constraintParts[1] ?? 'id';
        $except = $constraintParts[2] ?? null;

        // In a real implementation, this would query the database
        // For now, we'll simulate it
        return $value !== $except;
    }

    // ====================================================================
    // Utility Methods
    // ====================================================================

    /**
     * Get all bindings.
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Check if binding exists.
     */
    public function hasBinding(string $key): bool
    {
        return isset($this->bindings[$key]);
    }

    /**
     * Remove binding.
     */
    public function removeBinding(string $key): void
    {
        unset($this->bindings[$key]);
    }

    /**
     * Clear all bindings.
     */
    public function clearBindings(): void
    {
        $this->bindings = [];
    }

    /**
     * Get binding statistics.
     */
    public function getStats(): array
    {
        $types = [];
        foreach ($this->bindings as $binding) {
            $type = $binding['type'];
            $types[$type] = ($types[$type] ?? 0) + 1;
        }

        return [
            'total_bindings' => count($this->bindings),
            'binding_types' => $types,
            'transformers' => count($this->transformers),
            'has_container' => $this->containerResolver !== null,
        ];
    }

    /**
     * Set container resolver.
     */
    public function setContainerResolver(Closure $resolver): void
    {
        $this->containerResolver = $resolver;
    }
}