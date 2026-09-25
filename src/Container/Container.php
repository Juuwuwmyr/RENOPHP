<?php

declare(strict_types=1);

namespace Horizon\Container;

use Closure;
use Exception;
use Horizon\Contracts\Container\ContainerInterface;
use Horizon\Contracts\Container\ContextualBindingBuilder;
use Horizon\Container\Exceptions\BindingResolutionException;
use Horizon\Container\Exceptions\CircularDependencyException;
use LogicException;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;

class Container implements ContainerInterface
{
    /**
     * The current globally available container (if any).
     */
    protected static ?Container $instance = null;

    /**
     * The container's bindings.
     */
    protected array $bindings = [];

    /**
     * The container's shared instances.
     */
    protected array $instances = [];

    /**
     * The registered type aliases.
     */
    protected array $aliases = [];

    /**
     * The registered aliases keyed by the abstract name.
     */
    protected array $abstractAliases = [];

    /**
     * The extension closures for services.
     */
    protected array $extenders = [];

    /**
     * All of the registered tags.
     */
    protected array $tags = [];

    /**
     * The stack of concretions currently being built.
     */
    protected array $buildStack = [];

    /**
     * The parameter override stack.
     */
    protected array $with = [];

    /**
     * The contextual binding map.
     */
    public array $contextual = [];

    /**
     * All of the registered method bindings.
     */
    protected array $methodBindings = [];

    /**
     * All of the resolved instances.
     */
    protected array $resolved = [];

    /**
     * All of the scoped instances.
     */
    protected array $scopedInstances = [];

    /**
     * All of the registered rebound callbacks.
     */
    protected array $reboundCallbacks = [];

    /**
     * All of the global resolving callbacks.
     */
    protected array $globalResolvingCallbacks = [];

    /**
     * All of the global after resolving callbacks.
     */
    protected array $globalAfterResolvingCallbacks = [];

    /**
     * All of the resolving callbacks by class type.
     */
    protected array $resolvingCallbacks = [];

    /**
     * All of the after resolving callbacks by class type.
     */
    protected array $afterResolvingCallbacks = [];

    /**
     * Set the globally available instance of the container.
     */
    public static function setInstance(?ContainerInterface $container = null): ?ContainerInterface
    {
        return static::$instance = $container;
    }

    /**
     * Get the globally available instance of the container.
     */
    public static function getInstance(): static
    {
        if (is_null(static::$instance)) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    /**
     * Determine if a given offset exists.
     */
    public function offsetExists(mixed $offset): bool
    {
        return $this->bound($offset);
    }

    /**
     * Get the value at a given offset.
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->make($offset);
    }

    /**
     * Set the value at a given offset.
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->bind($offset, $value instanceof Closure ? $value : function () use ($value) {
            return $value;
        });
    }

    /**
     * Unset the value at a given offset.
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->bindings[$offset], $this->instances[$offset], $this->resolved[$offset]);
    }

    /**
     * Dynamically access container services.
     */
    public function __get(string $key): mixed
    {
        return $this->get($key);
    }

    /**
     * Dynamically set container services.
     */
    public function __set(string $key, mixed $value): void
    {
        $this->instance($key, $value);
    }

    /**
     * Define a contextual binding.
     */
    public function when(string $concrete): ContextualBindingBuilder
    {
        return new ContextualBindingBuilderImpl(
            $this, $this->getAlias($concrete)
        );
    }

    /**
     * Determine if the given abstract type has been bound.
     */
    public function bound(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) ||
               isset($this->instances[$abstract]) ||
               $this->isAlias($abstract);
    }

    /**
     * Determine if the given abstract type has been resolved.
     */
    public function resolved(string $abstract): bool
    {
        if ($this->isAlias($abstract)) {
            $abstract = $this->getAlias($abstract);
        }

        return isset($this->resolved[$abstract]) ||
               isset($this->instances[$abstract]);
    }

    /**
     * Determine if a given string is an alias.
     */
    public function isAlias(string $name): bool
    {
        return isset($this->aliases[$name]);
    }

    /**
     * Register a binding with the container.
     */
    public function bind(string $abstract, mixed $concrete = null, bool $shared = false): void
    {
        $this->dropStaleInstances($abstract);

        if (is_null($concrete)) {
            $concrete = $abstract;
        }

        if (!$concrete instanceof Closure) {
            if (!is_string($concrete)) {
                throw new TypeError(self::class.'::bind(): Argument #2 ($concrete) must be of type Closure|string|null');
            }

            $concrete = $this->getClosure($abstract, $concrete);
        }

        $this->bindings[$abstract] = compact('concrete', 'shared');

        if ($this->resolved($abstract)) {
            $this->rebound($abstract);
        }
    }

    /**
     * Get the Closure to be used when building a type.
     */
    protected function getClosure(string $abstract, string $concrete): Closure
    {
        return function (Container $container, array $parameters = []) use ($abstract, $concrete) {
            if ($abstract == $concrete) {
                return $container->build($concrete);
            }

            return $container->resolve($concrete, $parameters, $raiseEvents = false);
        };
    }

    /**
     * Determine if the container has a method binding.
     */
    public function hasMethodBinding(string $method): bool
    {
        return isset($this->methodBindings[$method]);
    }

    /**
     * Bind a callback to resolve with Container::call.
     */
    public function bindMethod(string $method, callable $callback): void
    {
        $this->methodBindings[$this->parseBindMethod($method)] = $callback;
    }

    /**
     * Get the method binding for the given method.
     */
    public function callMethodBinding(string $method, mixed $instance): mixed
    {
        return call_user_func($this->methodBindings[$method], $instance, $this);
    }

    /**
     * Parse a bind method for the given method.
     */
    protected function parseBindMethod(string $method): string
    {
        return $method;
    }

    /**
     * Add a contextual binding to the container.
     */
    public function addContextualBinding(string $concrete, string $abstract, mixed $implementation): void
    {
        $this->contextual[$concrete][$this->getAlias($abstract)] = $implementation;
    }

    /**
     * Register a binding if it hasn't already been registered.
     */
    public function bindIf(string $abstract, mixed $concrete = null, bool $shared = false): void
    {
        if (!$this->bound($abstract)) {
            $this->bind($abstract, $concrete, $shared);
        }
    }

    /**
     * Register a shared binding in the container.
     */
    public function singleton(string $abstract, mixed $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Register a shared binding if it hasn't already been registered.
     */
    public function singletonIf(string $abstract, mixed $concrete = null): void
    {
        if (!$this->bound($abstract)) {
            $this->singleton($abstract, $concrete);
        }
    }

    /**
     * "Extend" an abstract type in the container.
     */
    public function extend(string $abstract, Closure $closure): void
    {
        $abstract = $this->getAlias($abstract);

        if (isset($this->instances[$abstract])) {
            $this->instances[$abstract] = $closure($this->instances[$abstract], $this);

            $this->rebound($abstract);
        } else {
            $this->extenders[$abstract][] = $closure;

            if ($this->resolved($abstract)) {
                $this->rebound($abstract);
            }
        }
    }

    /**
     * Register an existing instance as shared in the container.
     */
    public function instance(string $abstract, mixed $instance): mixed
    {
        $this->removeAbstractAlias($abstract);

        $isBound = $this->bound($abstract);

        unset($this->aliases[$abstract]);

        $this->instances[$abstract] = $instance;

        if ($isBound) {
            $this->rebound($abstract);
        }

        return $instance;
    }

    /**
     * Remove an alias from the contextual binding alias cache.
     */
    protected function removeAbstractAlias(string $searched): void
    {
        if (!isset($this->aliases[$searched])) {
            return;
        }

        foreach ($this->abstractAliases as $abstract => $aliases) {
            foreach ($aliases as $index => $alias) {
                if ($alias == $searched) {
                    unset($this->abstractAliases[$abstract][$index]);
                }
            }
        }
    }

    /**
     * Assign a set of tags to a given binding.
     */
    public function tag(array|string $abstracts, array|string $tags): void
    {
        $tags = is_array($tags) ? $tags : array_slice(func_get_args(), 1);

        foreach ($tags as $tag) {
            if (!isset($this->tags[$tag])) {
                $this->tags[$tag] = [];
            }

            foreach ((array) $abstracts as $abstract) {
                $this->tags[$tag][] = $abstract;
            }
        }
    }

    /**
     * Resolve all of the bindings for a given tag.
     */
    public function tagged(string $tag): iterable
    {
        if (!isset($this->tags[$tag])) {
            return [];
        }

        return new RewindableGenerator(function () use ($tag) {
            foreach ($this->tags[$tag] as $abstract) {
                yield $this->make($abstract);
            }
        }, count($this->tags[$tag]));
    }

    /**
     * Alias a type to a different name.
     */
    public function alias(string $abstract, string $alias): void
    {
        if ($alias === $abstract) {
            throw new LogicException("[{$abstract}] is aliased to itself.");
        }

        $this->aliases[$alias] = $abstract;

        $this->abstractAliases[$abstract][] = $alias;
    }

    /**
     * Bind a new callback to an abstract's rebind event.
     */
    public function rebinding(string $abstract, Closure $callback): mixed
    {
        $this->reboundCallbacks[$this->getAlias($abstract)][] = $callback;

        if ($this->bound($abstract)) {
            return $this->make($abstract);
        }
    }

    /**
     * Refresh an instance on the given target and method.
     */
    public function refresh(string $abstract, mixed $target, string $method): mixed
    {
        return $this->rebinding($abstract, function ($app, $instance) use ($target, $method) {
            $target->{$method}($instance);
        });
    }

    /**
     * Fire the "rebound" callbacks for the given abstract type.
     */
    protected function rebound(string $abstract): void
    {
        $instance = $this->make($abstract);

        foreach ($this->getReboundCallbacks($abstract) as $callback) {
            $callback($this, $instance);
        }
    }

    /**
     * Get the rebound callbacks for a given type.
     */
    protected function getReboundCallbacks(string $abstract): array
    {
        return $this->reboundCallbacks[$abstract] ?? [];
    }

    /**
     * Wrap the given closure such that its dependencies will be injected when executed.
     */
    public function wrap(Closure $callback, array $parameters = []): Closure
    {
        return function () use ($callback, $parameters) {
            return $this->call($callback, $parameters);
        };
    }

    /**
     * Call the given Closure / class@method and inject its dependencies.
     */
    public function call(callable|string $callback, array $parameters = [], ?string $defaultMethod = null): mixed
    {
        return BoundMethod::call($this, $callback, $parameters, $defaultMethod);
    }

    /**
     * Get a closure to resolve the given type from the container.
     */
    public function factory(string $abstract): Closure
    {
        return function () use ($abstract) {
            return $this->make($abstract);
        };
    }

    /**
     * An alias function name for make().
     */
    public function makeWith(string $abstract, array $parameters = []): mixed
    {
        return $this->make($abstract, $parameters);
    }

    /**
     * Resolve the given type from the container.
     */
    public function make(string $abstract, array $parameters = []): mixed
    {
        return $this->resolve($abstract, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $id): mixed
    {
        try {
            return $this->resolve($id);
        } catch (Exception $e) {
            if ($this->has($id) || $e instanceof CircularDependencyException) {
                throw $e;
            }

            throw new EntryNotFoundException($id, is_int($e->getCode()) ? $e->getCode() : 0, $e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $id): bool
    {
        return $this->bound($id);
    }

    /**
     * Resolve the given type from the container.
     */
    protected function resolve(string $abstract, array $parameters = [], bool $raiseEvents = true): mixed
    {
        $abstract = $this->getAlias($abstract);

        if ($raiseEvents) {
            $this->fireBeforeResolvingCallbacks($abstract, $parameters);
        }

        $concrete = $this->getContextualConcrete($abstract);

        $needsContextualBuild = !empty($parameters) || !is_null($concrete);

        if (isset($this->instances[$abstract]) && !$needsContextualBuild) {
            return $this->instances[$abstract];
        }

        $this->with[] = $parameters;

        if (is_null($concrete)) {
            $concrete = $this->getConcrete($abstract);
        }

        $object = $this->isBuildable($concrete, $abstract)
                    ? $this->build($concrete)
                    : $this->make($concrete);

        foreach ($this->getExtenders($abstract) as $extender) {
            $object = $extender($object, $this);
        }

        if ($this->isShared($abstract) && !$needsContextualBuild) {
            $this->instances[$abstract] = $object;
        }

        if ($raiseEvents) {
            $this->fireAfterResolvingCallbacks($abstract, $object);
        }

        $this->resolved[$abstract] = true;

        array_pop($this->with);

        return $object;
    }

    /**
     * Get the concrete type for a given abstract.
     */
    protected function getConcrete(string $abstract): mixed
    {
        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]['concrete'];
        }

        return $abstract;
    }

    /**
     * Get the contextual concrete binding for the given abstract.
     */
    protected function getContextualConcrete(string $abstract): ?string
    {
        if (!is_null($binding = $this->findInContextualBindings($abstract))) {
            return $binding;
        }

        if (empty($this->abstractAliases[$abstract])) {
            return null;
        }

        foreach ($this->abstractAliases[$abstract] as $alias) {
            if (!is_null($binding = $this->findInContextualBindings($alias))) {
                return $binding;
            }
        }

        return null;
    }

    /**
     * Find the concrete binding for the given abstract in the contextual binding array.
     */
    protected function findInContextualBindings(string $abstract): ?string
    {
        return $this->contextual[end($this->buildStack)][$abstract] ?? null;
    }

    /**
     * Determine if the given concrete is buildable.
     */
    protected function isBuildable(mixed $concrete, string $abstract): bool
    {
        return $concrete === $abstract || $concrete instanceof Closure;
    }

    /**
     * Instantiate a concrete instance of the given type.
     */
    public function build(mixed $concrete): mixed
    {
        if ($concrete instanceof Closure) {
            return $concrete($this, $this->getLastParameterOverride());
        }

        try {
            $reflector = new ReflectionClass($concrete);
        } catch (ReflectionException $e) {
            throw new BindingResolutionException("Target class [$concrete] does not exist.", 0, $e);
        }

        if (!$reflector->isInstantiable()) {
            return $this->notInstantiable($concrete);
        }

        $this->buildStack[] = $concrete;

        $constructor = $reflector->getConstructor();

        if (is_null($constructor)) {
            array_pop($this->buildStack);

            return new $concrete;
        }

        $dependencies = $constructor->getParameters();

        try {
            $instances = $this->resolveDependencies($dependencies);
        } catch (BindingResolutionException $e) {
            array_pop($this->buildStack);

            throw $e;
        }

        array_pop($this->buildStack);

        return $reflector->newInstanceArgs($instances);
    }

    /**
     * Resolve all of the dependencies from the ReflectionParameters.
     */
    protected function resolveDependencies(array $dependencies): array
    {
        $results = [];

        foreach ($dependencies as $dependency) {
            if ($this->hasParameterOverride($dependency)) {
                $results[] = $this->getParameterOverride($dependency);

                continue;
            }

            $result = is_null(Util::getParameterClassName($dependency))
                            ? $this->resolvePrimitive($dependency)
                            : $this->resolveClass($dependency);

            if ($dependency->isVariadic()) {
                $results = array_merge($results, $result);
            } else {
                $results[] = $result;
            }
        }

        return $results;
    }

    /**
     * Determine if the given dependency has a parameter override.
     */
    protected function hasParameterOverride(ReflectionParameter $dependency): bool
    {
        return array_key_exists(
            $dependency->name, $this->getLastParameterOverride()
        );
    }

    /**
     * Get a parameter override for a dependency.
     */
    protected function getParameterOverride(ReflectionParameter $dependency): mixed
    {
        return $this->getLastParameterOverride()[$dependency->name];
    }

    /**
     * Get the last parameter override.
     */
    protected function getLastParameterOverride(): array
    {
        return count($this->with) ? end($this->with) : [];
    }

    /**
     * Resolve a non-class hinted primitive dependency.
     */
    protected function resolvePrimitive(ReflectionParameter $parameter): mixed
    {
        if (!is_null($concrete = $this->getContextualConcrete('$'.$parameter->getName()))) {
            return Util::unwrapIfClosure($concrete, $this);
        }

        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($parameter->isVariadic()) {
            return [];
        }

        $this->unresolvablePrimitive($parameter);
    }

    /**
     * Resolve a class based dependency from the container.
     */
    protected function resolveClass(ReflectionParameter $parameter): mixed
    {
        try {
            return $parameter->isVariadic()
                        ? $this->resolveVariadicClass($parameter)
                        : $this->make(Util::getParameterClassName($parameter));
        } catch (BindingResolutionException $e) {
            if ($parameter->isOptional()) {
                return $parameter->getDefaultValue();
            }

            if ($parameter->isVariadic()) {
                return [];
            }

            throw $e;
        }
    }

    /**
     * Resolve a class based variadic dependency from the container.
     */
    protected function resolveVariadicClass(ReflectionParameter $parameter): array
    {
        $className = Util::getParameterClassName($parameter);

        $abstract = $this->getAlias($className);

        if (!is_array($concrete = $this->getContextualConcrete($abstract))) {
            return $this->make($abstract);
        }

        return array_map(function ($abstract) {
            return $this->resolve($abstract);
        }, $concrete);
    }

    /**
     * Throw an exception that the concrete is not instantiable.
     */
    protected function notInstantiable(string $concrete): never
    {
        if (!empty($this->buildStack)) {
            $previous = implode(', ', $this->buildStack);

            $message = "Target [$concrete] is not instantiable while building [$previous].";
        } else {
            $message = "Target [$concrete] is not instantiable.";
        }

        throw new BindingResolutionException($message);
    }

    /**
     * Throw an exception for an unresolvable primitive.
     */
    protected function unresolvablePrimitive(ReflectionParameter $parameter): never
    {
        $message = "Unresolvable dependency resolving [$parameter] in class {$parameter->getDeclaringClass()->getName()}";

        throw new BindingResolutionException($message);
    }

    /**
     * Register a new before resolving callback for all types.
     */
    public function beforeResolving(Closure|string $abstract, ?Closure $callback = null): void
    {
        if (is_string($abstract)) {
            $abstract = $this->getAlias($abstract);
        }

        if ($abstract instanceof Closure && is_null($callback)) {
            $this->globalResolvingCallbacks[] = $abstract;
        } else {
            $this->resolvingCallbacks[$abstract][] = $callback;
        }
    }

    /**
     * Register a new resolving callback.
     */
    public function resolving(Closure|string $abstract, ?Closure $callback = null): void
    {
        if (is_string($abstract)) {
            $abstract = $this->getAlias($abstract);
        }

        if ($abstract instanceof Closure && is_null($callback)) {
            $this->globalResolvingCallbacks[] = $abstract;
        } else {
            $this->resolvingCallbacks[$abstract][] = $callback;
        }
    }

    /**
     * Register a new after resolving callback for all types.
     */
    public function afterResolving(Closure|string $abstract, ?Closure $callback = null): void
    {
        if (is_string($abstract)) {
            $abstract = $this->getAlias($abstract);
        }

        if ($abstract instanceof Closure && is_null($callback)) {
            $this->globalAfterResolvingCallbacks[] = $abstract;
        } else {
            $this->afterResolvingCallbacks[$abstract][] = $callback;
        }
    }

    /**
     * Fire all of the before resolving callbacks.
     */
    protected function fireBeforeResolvingCallbacks(string $abstract, array $parameters = []): void
    {
        $this->fireBeforeCallbackArray($abstract, $parameters, $this->globalResolvingCallbacks);

        foreach ($this->resolvingCallbacks[$abstract] ?? [] as $callback) {
            $callback($abstract, $parameters, $this);
        }

        $this->fireBeforeCallbackArray($abstract, $parameters, $this->globalResolvingCallbacks);
    }

    /**
     * Fire an array of callbacks with an object.
     */
    protected function fireBeforeCallbackArray(string $abstract, array $parameters, array $callbacks): void
    {
        foreach ($callbacks as $callback) {
            $callback($abstract, $parameters, $this);
        }
    }

    /**
     * Fire all of the resolving callbacks.
     */
    protected function fireResolvingCallbacks(string $abstract, mixed $object): void
    {
        $this->fireCallbackArray($object, $this->globalResolvingCallbacks);

        $this->fireCallbackArray(
            $object, $this->getCallbacksForType($abstract, $object, $this->resolvingCallbacks)
        );

        $this->fireAfterResolvingCallbacks($abstract, $object);
    }

    /**
     * Fire all of the after resolving callbacks.
     */
    protected function fireAfterResolvingCallbacks(string $abstract, mixed $object): void
    {
        $this->fireCallbackArray($object, $this->globalAfterResolvingCallbacks);

        $this->fireCallbackArray(
            $object, $this->getCallbacksForType($abstract, $object, $this->afterResolvingCallbacks)
        );
    }

    /**
     * Get all callbacks for a given type.
     */
    protected function getCallbacksForType(string $abstract, object $object, array $callbacksPerType): array
    {
        $results = [];

        foreach ($callbacksPerType as $type => $callbacks) {
            if ($type === $abstract || $object instanceof $type) {
                $results = array_merge($results, $callbacks);
            }
        }

        return $results;
    }

    /**
     * Fire an array of callbacks with an object.
     */
    protected function fireCallbackArray(object $object, array $callbacks): void
    {
        foreach ($callbacks as $callback) {
            $callback($object, $this);
        }
    }

    /**
     * Get the container's bindings.
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Get the alias for an abstract if available.
     */
    public function getAlias(string $abstract): string
    {
        return isset($this->aliases[$abstract])
                    ? $this->getAlias($this->aliases[$abstract])
                    : $abstract;
    }

    /**
     * Get the extender callbacks for a given type.
     */
    protected function getExtenders(string $abstract): array
    {
        return $this->extenders[$this->getAlias($abstract)] ?? [];
    }

    /**
     * Remove all of the extender callbacks for a given type.
     */
    public function forgetExtenders(string $abstract): void
    {
        unset($this->extenders[$this->getAlias($abstract)]);
    }

    /**
     * Drop all of the stale instances and aliases.
     */
    protected function dropStaleInstances(string $abstract): void
    {
        unset($this->instances[$abstract], $this->aliases[$abstract]);
    }

    /**
     * Remove a resolved instance from the instance cache.
     */
    public function forgetInstance(string $abstract): void
    {
        unset($this->instances[$abstract]);
    }

    /**
     * Clear all of the instances from the container.
     */
    public function forgetInstances(): void
    {
        $this->instances = [];
    }

    /**
     * Clear all of the scoped instances from the container.
     */
    public function forgetScopedInstances(): void
    {
        foreach ($this->scopedInstances as $scoped) {
            unset($this->instances[$scoped]);
        }
    }

    /**
     * Flush the container of all bindings and resolved instances.
     */
    public function flush(): void
    {
        $this->aliases = [];
        $this->resolved = [];
        $this->bindings = [];
        $this->instances = [];
        $this->abstractAliases = [];
        $this->scopedInstances = [];
    }

    /**
     * Determine if a given type is shared.
     */
    public function isShared(string $abstract): bool
    {
        return isset($this->instances[$abstract]) ||
               (isset($this->bindings[$abstract]['shared']) &&
                $this->bindings[$abstract]['shared'] === true);
    }

    /**
     * Determine if the given concrete is buildable.
     */
    protected function isCallable(mixed $concrete): bool
    {
        return method_exists($concrete, '__invoke');
    }
}

/**
 * Contextual binding builder implementation.
 */
class ContextualBindingBuilderImpl implements ContextualBindingBuilder
{
    /**
     * The underlying container instance.
     */
    protected Container $container;

    /**
     * The concrete instance.
     */
    protected string $concrete;

    /**
     * The abstract target.
     */
    protected string $needs;

    /**
     * Create a new contextual binding builder.
     */
    public function __construct(Container $container, string $concrete)
    {
        $this->container = $container;
        $this->concrete = $concrete;
    }

    /**
     * Define the abstract target that depends on the context.
     */
    public function needs(string $abstract): ContextualBindingBuilder
    {
        $this->needs = $abstract;

        return $this;
    }

    /**
     * Define the implementation that should be used for the given context.
     */
    public function give(mixed $implementation): void
    {
        $this->container->addContextualBinding(
            $this->concrete, $this->needs, $implementation
        );
    }

    /**
     * Define tagged services to be used for the given context.
     */
    public function giveTagged(string $tag): void
    {
        $this->give(function () use ($tag) {
            return iterator_to_array($this->container->tagged($tag));
        });
    }
}