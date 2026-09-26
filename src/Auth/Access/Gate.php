<?php

namespace Reno\Auth\Access;

use Closure;
use Reno\Auth\Contracts\Authenticatable;
use InvalidArgumentException;

/**
 * Gate
 * 
 * Authorization gate for defining and checking abilities.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Simple ability definitions
 * - Clear authorization checks
 * - Policy support
 * - Before/after hooks
 * 
 * Security:
 * - Default deny (must explicitly allow)
 * - User context required
 * - Clear authorization flow
 */
class Gate
{
    /**
     * The user resolver callable
     *
     * @var callable
     */
    protected $userResolver;

    /**
     * All of the defined abilities
     *
     * @var array
     */
    protected array $abilities = [];

    /**
     * All of the defined policies
     *
     * @var array
     */
    protected array $policies = [];

    /**
     * All of the registered before callbacks
     *
     * @var array
     */
    protected array $beforeCallbacks = [];

    /**
     * All of the registered after callbacks
     *
     * @var array
     */
    protected array $afterCallbacks = [];

    /**
     * Create a new gate instance
     *
     * @param callable $userResolver
     */
    public function __construct(callable $userResolver)
    {
        $this->userResolver = $userResolver;
    }

    /**
     * Determine if a given ability has been defined
     *
     * @param string $ability
     * @return bool
     */
    public function has(string $ability): bool
    {
        return isset($this->abilities[$ability]);
    }

    /**
     * Define a new ability
     *
     * @param string $ability
     * @param callable|string $callback
     * @return $this
     */
    public function define(string $ability, $callback): self
    {
        if (is_string($callback) && str_contains($callback, '@')) {
            $callback = $this->buildAbilityCallback($callback);
        }

        $this->abilities[$ability] = $callback;

        return $this;
    }

    /**
     * Define a policy class for a given class type
     *
     * @param string $class
     * @param string $policy
     * @return $this
     */
    public function policy(string $class, string $policy): self
    {
        $this->policies[$class] = $policy;

        return $this;
    }

    /**
     * Register a callback to run before all Gate checks
     *
     * @param callable $callback
     * @return $this
     */
    public function before(callable $callback): self
    {
        $this->beforeCallbacks[] = $callback;

        return $this;
    }

    /**
     * Register a callback to run after all Gate checks
     *
     * @param callable $callback
     * @return $this
     */
    public function after(callable $callback): self
    {
        $this->afterCallbacks[] = $callback;

        return $this;
    }

    /**
     * Determine if the given ability should be granted for the current user
     *
     * @param string $ability
     * @param mixed ...$arguments
     * @return bool
     */
    public function allows(string $ability, ...$arguments): bool
    {
        return $this->check($ability, $arguments);
    }

    /**
     * Determine if the given ability should be denied for the current user
     *
     * @param string $ability
     * @param mixed ...$arguments
     * @return bool
     */
    public function denies(string $ability, ...$arguments): bool
    {
        return !$this->allows($ability, ...$arguments);
    }

    /**
     * Determine if the given ability should be granted for the current user
     *
     * @param string $ability
     * @param array $arguments
     * @return bool
     */
    public function check(string $ability, array $arguments = []): bool
    {
        try {
            $result = $this->raw($ability, $arguments);

            return (bool) $result;
        } catch (AuthorizationException $e) {
            return false;
        }
    }

    /**
     * Determine if the given ability should be granted for the current user
     *
     * @param string $ability
     * @param array $arguments
     * @return mixed
     */
    protected function raw(string $ability, array $arguments = [])
    {
        $user = $this->resolveUser();

        // Run before callbacks
        $result = $this->callBeforeCallbacks($user, $ability, $arguments);

        if (!is_null($result)) {
            return $result;
        }

        // Get the callback
        $callback = $this->resolveAuthCallback($user, $ability, $arguments);

        // Call the callback
        $result = $callback($user, ...$arguments);

        // Run after callbacks
        return $this->callAfterCallbacks($user, $ability, $arguments, $result);
    }

    /**
     * Resolve and call the appropriate authorization callback
     *
     * @param Authenticatable|null $user
     * @param string $ability
     * @param array $arguments
     * @return callable
     */
    protected function resolveAuthCallback($user, string $ability, array $arguments): callable
    {
        // Check for policy
        if (isset($arguments[0]) && !is_null($policy = $this->getPolicyFor($arguments[0]))) {
            return $this->resolvePolicyCallback($user, $ability, $arguments, $policy);
        }

        // Check for direct ability
        if (isset($this->abilities[$ability])) {
            return $this->abilities[$ability];
        }

        // Default deny
        return function () {
            return false;
        };
    }

    /**
     * Get a policy instance for a given class
     *
     * @param mixed $class
     * @return mixed
     */
    protected function getPolicyFor($class)
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        if (!is_string($class)) {
            return null;
        }

        return $this->policies[$class] ?? null;
    }

    /**
     * Resolve the callback for a policy method
     *
     * @param Authenticatable|null $user
     * @param string $ability
     * @param array $arguments
     * @param string $policy
     * @return callable
     */
    protected function resolvePolicyCallback($user, string $ability, array $arguments, string $policy): callable
    {
        return function () use ($user, $ability, $arguments, $policy) {
            $instance = new $policy;

            // Check if method exists
            if (!method_exists($instance, $ability)) {
                return false;
            }

            return $instance->{$ability}($user, ...$arguments);
        };
    }

    /**
     * Call all of the before callbacks and return if a result is given
     *
     * @param Authenticatable|null $user
     * @param string $ability
     * @param array $arguments
     * @return mixed
     */
    protected function callBeforeCallbacks($user, string $ability, array $arguments)
    {
        foreach ($this->beforeCallbacks as $before) {
            $result = $before($user, $ability, $arguments);

            if (!is_null($result)) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Call all of the after callbacks with the given result
     *
     * @param Authenticatable|null $user
     * @param string $ability
     * @param array $arguments
     * @param mixed $result
     * @return mixed
     */
    protected function callAfterCallbacks($user, string $ability, array $arguments, $result)
    {
        foreach ($this->afterCallbacks as $after) {
            $afterResult = $after($user, $ability, $arguments, $result);

            if (!is_null($afterResult)) {
                return $afterResult;
            }
        }

        return $result;
    }

    /**
     * Resolve the user from the user resolver
     *
     * @return Authenticatable|null
     */
    protected function resolveUser()
    {
        return call_user_func($this->userResolver);
    }

    /**
     * Get a gate instance for the given user
     *
     * @param Authenticatable|mixed $user
     * @return static
     */
    public function forUser($user): self
    {
        $callback = function () use ($user) {
            return $user;
        };

        return new static($callback);
    }

    /**
     * Build an ability callback from a string
     *
     * @param string $callback
     * @return Closure
     */
    protected function buildAbilityCallback(string $callback): Closure
    {
        [$class, $method] = explode('@', $callback);

        return function (...$args) use ($class, $method) {
            return (new $class)->{$method}(...$args);
        };
    }

    /**
     * Get all of the defined abilities
     *
     * @return array
     */
    public function abilities(): array
    {
        return $this->abilities;
    }

    /**
     * Get all of the defined policies
     *
     * @return array
     */
    public function policies(): array
    {
        return $this->policies;
    }
}
