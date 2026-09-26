<?php

namespace Horizon\Auth;

use Horizon\Auth\Contracts\Guard;
use Horizon\Auth\Contracts\UserProvider;
use Horizon\Auth\Guards\SessionGuard;
use Horizon\Auth\Providers\EloquentUserProvider;
use InvalidArgumentException;

/**
 * AuthManager
 * 
 * Main authentication manager that creates and manages guards.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Central auth coordination
 * - Multiple guard support
 * - Clear guard resolution
 * - Explicit configuration
 * 
 * Security:
 * - Guards are stateful and isolated
 * - User providers are configurable
 * - Default guard pattern
 */
class AuthManager
{
    /**
     * The application instance
     *
     * @var mixed
     */
    protected $app;

    /**
     * The registered custom driver creators
     *
     * @var array
     */
    protected array $customCreators = [];

    /**
     * The array of created guards
     *
     * @var array
     */
    protected array $guards = [];

    /**
     * The array of created user providers
     *
     * @var array
     */
    protected array $userProviders = [];

    /**
     * The default guard name
     *
     * @var string
     */
    protected string $defaultGuard = 'web';

    /**
     * Create a new Auth manager instance
     *
     * @param mixed $app
     */
    public function __construct($app = null)
    {
        $this->app = $app;
    }

    /**
     * Get a guard instance by name
     *
     * @param string|null $name
     * @return Guard
     */
    public function guard(?string $name = null): Guard
    {
        $name = $name ?: $this->getDefaultGuard();

        return $this->guards[$name] ?? $this->resolve($name);
    }

    /**
     * Resolve the given guard
     *
     * @param string $name
     * @return Guard
     * @throws InvalidArgumentException
     */
    protected function resolve(string $name): Guard
    {
        $config = $this->getConfig($name);

        if (is_null($config)) {
            throw new InvalidArgumentException("Auth guard [{$name}] is not defined.");
        }

        // Check for custom creator
        if (isset($this->customCreators[$config['driver']])) {
            return $this->callCustomCreator($name, $config);
        }

        $driverMethod = 'create' . ucfirst($config['driver']) . 'Driver';

        if (method_exists($this, $driverMethod)) {
            return $this->guards[$name] = $this->{$driverMethod}($name, $config);
        }

        throw new InvalidArgumentException("Auth driver [{$config['driver']}] is not supported.");
    }

    /**
     * Call a custom driver creator
     *
     * @param string $name
     * @param array $config
     * @return Guard
     */
    protected function callCustomCreator(string $name, array $config): Guard
    {
        return $this->customCreators[$config['driver']]($this->app, $name, $config);
    }

    /**
     * Create a session based authentication guard
     *
     * @param string $name
     * @param array $config
     * @return SessionGuard
     */
    protected function createSessionDriver(string $name, array $config): SessionGuard
    {
        $provider = $this->createUserProvider($config['provider'] ?? null);

        $guard = new SessionGuard(
            $name,
            $provider,
            $this->app['session'] ?? null
        );

        return $guard;
    }

    /**
     * Create the user provider implementation for the driver
     *
     * @param string|null $provider
     * @return UserProvider|null
     * @throws InvalidArgumentException
     */
    public function createUserProvider(?string $provider = null): ?UserProvider
    {
        if (is_null($provider)) {
            return null;
        }

        if (isset($this->userProviders[$provider])) {
            return $this->userProviders[$provider];
        }

        $config = $this->getProviderConfig($provider);

        if (is_null($config)) {
            throw new InvalidArgumentException("User provider [{$provider}] is not defined.");
        }

        $driverMethod = 'create' . ucfirst($config['driver']) . 'Provider';

        if (method_exists($this, $driverMethod)) {
            return $this->userProviders[$provider] = $this->{$driverMethod}($config);
        }

        throw new InvalidArgumentException("User provider driver [{$config['driver']}] is not supported.");
    }

    /**
     * Create an instance of the Eloquent user provider
     *
     * @param array $config
     * @return EloquentUserProvider
     */
    protected function createEloquentProvider(array $config): EloquentUserProvider
    {
        return new EloquentUserProvider($config['model']);
    }

    /**
     * Get the guard configuration
     *
     * @param string $name
     * @return array|null
     */
    protected function getConfig(string $name): ?array
    {
        // In a real app, this would come from config
        // For now, return default configuration
        return [
            'driver' => 'session',
            'provider' => 'users',
        ];
    }

    /**
     * Get the user provider configuration
     *
     * @param string $provider
     * @return array|null
     */
    protected function getProviderConfig(string $provider): ?array
    {
        // In a real app, this would come from config
        // For now, return default configuration
        return [
            'driver' => 'eloquent',
            'model' => 'App\\Models\\User',
        ];
    }

    /**
     * Register a custom driver creator Closure
     *
     * @param string $driver
     * @param callable $callback
     * @return $this
     */
    public function extend(string $driver, callable $callback): self
    {
        $this->customCreators[$driver] = $callback;

        return $this;
    }

    /**
     * Get the default guard name
     *
     * @return string
     */
    public function getDefaultGuard(): string
    {
        return $this->defaultGuard;
    }

    /**
     * Set the default guard name
     *
     * @param string $name
     * @return void
     */
    public function setDefaultGuard(string $name): void
    {
        $this->defaultGuard = $name;
    }

    /**
     * Dynamically call the default guard instance
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->guard()->{$method}(...$parameters);
    }
}
