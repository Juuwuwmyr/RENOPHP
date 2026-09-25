<?php

declare(strict_types=1);

namespace Horizon\Foundation;

use Horizon\Container\Container;
use Horizon\Contracts\Foundation\ApplicationInterface;
use Horizon\Support\ServiceProvider;
use RuntimeException;

class Application extends Container implements ApplicationInterface
{
    /**
     * The Horizon framework version.
     */
    public const VERSION = '1.0.0-dev';

    /**
     * The base path for the application installation.
     */
    protected string $basePath;

    /**
     * The custom storage path defined by the developer.
     */
    protected ?string $storagePath = null;

    /**
     * The custom database path defined by the developer.
     */
    protected ?string $databasePath = null;

    /**
     * The custom lang path defined by the developer.
     */
    protected ?string $langPath = null;

    /**
     * The custom public path defined by the developer.
     */
    protected ?string $publicPath = null;

    /**
     * The environment file to load during bootstrapping.
     */
    protected string $environmentFile = '.env';

    /**
     * The current application environment.
     */
    protected ?string $environment = null;

    /**
     * Indicates if the application has been "booted".
     */
    protected bool $booted = false;

    /**
     * All of the registered service providers.
     */
    protected array $serviceProviders = [];

    /**
     * The names of the loaded service providers.
     */
    protected array $loadedProviders = [];

    /**
     * The deferred services and their providers.
     */
    protected array $deferredServices = [];

    /**
     * The after loading environment callbacks.
     */
    protected array $afterLoadingEnvironmentCallbacks = [];

    /**
     * The before bootstrapping callbacks.
     */
    protected array $beforeBootstrappingCallbacks = [];

    /**
     * The after bootstrapping callbacks.
     */
    protected array $afterBootstrappingCallbacks = [];

    /**
     * Create a new application instance.
     */
    public function __construct(?string $basePath = null)
    {
        if ($basePath) {
            $this->setBasePath($basePath);
        }

        $this->registerBaseBindings();
        $this->registerBaseServiceProviders();
        $this->registerCoreContainerAliases();
    }

    /**
     * Get the version number of the application.
     */
    public function version(): string
    {
        return static::VERSION;
    }

    /**
     * Set the base path for the application.
     */
    public function setBasePath(string $basePath): static
    {
        $this->basePath = rtrim($basePath, '\/');

        $this->bindPathsInContainer();

        return $this;
    }

    /**
     * Bind all of the application paths in the container.
     */
    protected function bindPathsInContainer(): void
    {
        $this->instance('path', $this->path());
        $this->instance('path.base', $this->basePath());
        $this->instance('path.config', $this->configPath());
        $this->instance('path.public', $this->publicPath());
        $this->instance('path.resources', $this->resourcePath());
        $this->instance('path.storage', $this->storagePath());
        $this->instance('path.database', $this->databasePath());
        $this->instance('path.lang', $this->langPath());
        $this->instance('path.bootstrap', $this->bootstrapPath());
    }

    /**
     * Get the path to the application "app" directory.
     */
    public function path(string $path = ''): string
    {
        $appPath = $this->basePath . DIRECTORY_SEPARATOR . 'app';

        return $path === '' ? $appPath : $appPath . DIRECTORY_SEPARATOR . $path;
    }

    /**
     * Get the base path of the application installation.
     */
    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    /**
     * Get the path to the bootstrap directory.
     */
    public function bootstrapPath(string $path = ''): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'bootstrap' . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    /**
     * Get the path to the application configuration files.
     */
    public function configPath(string $path = ''): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'config' . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    /**
     * Get the path to the database directory.
     */
    public function databasePath(string $path = ''): string
    {
        return ($this->databasePath ?? $this->basePath . DIRECTORY_SEPARATOR . 'database') . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    /**
     * Get the path to the language files.
     */
    public function langPath(string $path = ''): string
    {
        return $this->resourcePath() . DIRECTORY_SEPARATOR . 'lang' . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    /**
     * Get the path to the public directory.
     */
    public function publicPath(string $path = ''): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'public' . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    /**
     * Get the path to the resources directory.
     */
    public function resourcePath(string $path = ''): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'resources' . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    /**
     * Get the path to the storage directory.
     */
    public function storagePath(string $path = ''): string
    {
        return ($this->storagePath ?? $this->basePath . DIRECTORY_SEPARATOR . 'storage') . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    /**
     * Get or check the current application environment.
     */
    public function environment(string ...$environments): string|bool
    {
        if (count($environments) > 0) {
            return in_array($this->environment(), $environments);
        }

        return $this->environment ?: 'production';
    }

    /**
     * Detect the application's current environment.
     */
    public function detectEnvironment(callable $callback): string
    {
        return $this->environment = $callback();
    }

    /**
     * Determine if the application is running in the console.
     */
    public function runningInConsole(): bool
    {
        return php_sapi_name() === 'cli' || php_sapi_name() === 'phpdbg';
    }

    /**
     * Determine if the application is in debug mode.
     */
    public function hasDebugModeEnabled(): bool
    {
        return (bool) $this->make('config')->get('app.debug', false);
    }

    /**
     * Get the application namespace.
     */
    public function getNamespace(): string
    {
        if (!is_null($namespace = $this->make('config')->get('app.namespace'))) {
            return $namespace;
        }

        $composer = json_decode(file_get_contents($this->basePath('composer.json')), true);

        foreach ((array) data_get($composer, 'autoload.psr-4') as $namespace => $path) {
            foreach ((array) $path as $pathChoice) {
                if (realpath($this->path()) === realpath($this->basePath($pathChoice))) {
                    return $namespace;
                }
            }
        }

        throw new RuntimeException('Unable to detect application namespace.');
    }

    /**
     * Register the basic bindings into the container.
     */
    protected function registerBaseBindings(): void
    {
        static::setInstance($this);

        $this->instance('app', $this);
        $this->instance(Container::class, $this);
        // Package manifest will be implemented in later phases
    }

    /**
     * Register the core service providers.
     */
    protected function registerBaseServiceProviders(): void
    {
        // Register essential service providers here
        // These will be implemented in later phases
    }

    /**
     * Register the core container aliases.
     */
    protected function registerCoreContainerAliases(): void
    {
        foreach ([
            'app' => [self::class, \Horizon\Contracts\Foundation\ApplicationInterface::class, \Horizon\Contracts\Container\ContainerInterface::class, \Psr\Container\ContainerInterface::class],
            'config' => [\Horizon\Config\Repository::class, \Horizon\Contracts\Config\ConfigInterface::class],
            'request' => [\Horizon\Http\Request::class, \Horizon\Contracts\Http\RequestInterface::class],
            'response' => [\Horizon\Http\Response::class, \Horizon\Contracts\Http\ResponseInterface::class],
        ] as $key => $aliases) {
            foreach ($aliases as $alias) {
                $this->alias($key, $alias);
            }
        }
    }

    /**
     * Register all of the configured providers.
     */
    public function registerConfiguredProviders(): void
    {
        $providers = $this->make('config')->get('app.providers', []);

        foreach ($providers as $provider) {
            $this->register($provider);
        }
    }

    /**
     * Register a service provider with the application.
     */
    public function register(mixed $provider, bool $force = false): mixed
    {
        if (($registered = $this->getProvider($provider)) && !$force) {
            return $registered;
        }

        if (is_string($provider)) {
            $provider = $this->resolveProvider($provider);
        }

        $provider->register();

        if (property_exists($provider, 'bindings')) {
            foreach ($provider->bindings as $key => $value) {
                $this->bind($key, $value);
            }
        }

        if (property_exists($provider, 'singletons')) {
            foreach ($provider->singletons as $key => $value) {
                $this->singleton($key, $value);
            }
        }

        $this->markAsRegistered($provider);

        if ($this->isBooted()) {
            $this->bootProvider($provider);
        }

        return $provider;
    }

    /**
     * Get the registered service provider instance if it exists.
     */
    public function getProvider(mixed $provider): ?ServiceProvider
    {
        return array_values($this->getProviders($provider))[0] ?? null;
    }

    /**
     * Get the registered service provider instances if any exist.
     */
    public function getProviders(mixed $provider): array
    {
        $name = is_string($provider) ? $provider : get_class($provider);

        return array_filter($this->serviceProviders, function ($value) use ($name) {
            return $value instanceof $name;
        });
    }

    /**
     * Resolve a service provider instance from the class name.
     */
    public function resolveProvider(string $provider): ServiceProvider
    {
        return new $provider($this);
    }

    /**
     * Mark the given provider as registered.
     */
    protected function markAsRegistered(ServiceProvider $provider): void
    {
        $this->serviceProviders[] = $provider;
        $this->loadedProviders[get_class($provider)] = true;
    }

    /**
     * Boot the application's service providers.
     */
    public function boot(): void
    {
        if ($this->isBooted()) {
            return;
        }

        array_walk($this->serviceProviders, function ($p) {
            $this->bootProvider($p);
        });

        $this->booted = true;
    }

    /**
     * Boot the given service provider.
     */
    protected function bootProvider(ServiceProvider $provider): void
    {
        $provider->callBootingCallbacks();

        if (method_exists($provider, 'boot')) {
            $this->call([$provider, 'boot']);
        }

        $provider->callBootedCallbacks();
    }

    /**
     * Determine if the application has booted.
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Register a callback to be run after loading the environment.
     */
    public function afterLoadingEnvironment(callable $callback): void
    {
        $this->afterLoadingEnvironmentCallbacks[] = $callback;
    }

    /**
     * Register a callback to be run before a bootstrapper.
     */
    public function beforeBootstrapping(string $bootstrapper, callable $callback): void
    {
        $this->beforeBootstrappingCallbacks[$bootstrapper][] = $callback;
    }

    /**
     * Register a callback to be run after a bootstrapper.
     */
    public function afterBootstrapping(string $bootstrapper, callable $callback): void
    {
        $this->afterBootstrappingCallbacks[$bootstrapper][] = $callback;
    }

    /**
     * Fire the callbacks for the after loading environment event.
     */
    protected function fireAfterLoadingEnvironmentCallbacks(): void
    {
        foreach ($this->afterLoadingEnvironmentCallbacks as $callback) {
            $callback($this);
        }
    }

    /**
     * Fire the callbacks for the before bootstrapping event.
     */
    protected function fireBeforeBootstrappingCallbacks(string $bootstrapper): void
    {
        foreach ($this->beforeBootstrappingCallbacks[$bootstrapper] ?? [] as $callback) {
            $callback($this);
        }
    }

    /**
     * Fire the callbacks for the after bootstrapping event.
     */
    protected function fireAfterBootstrappingCallbacks(string $bootstrapper): void
    {
        foreach ($this->afterBootstrappingCallbacks[$bootstrapper] ?? [] as $callback) {
            $callback($this);
        }
    }

    /**
     * Load environment file in given directory.
     */
    public function loadEnvironmentFrom(string $file): static
    {
        $this->environmentFile = $file;

        return $this;
    }

    /**
     * Get the environment file the application is using.
     */
    public function environmentFile(): string
    {
        return $this->environmentFile ?: '.env';
    }

    /**
     * Determine if the application has been bootstrapped before.
     */
    public function hasBeenBootstrapped(): bool
    {
        return $this->booted;
    }

    /**
     * Bootstrap the application with the given bootstrappers.
     */
    public function bootstrapWith(array $bootstrappers): void
    {
        foreach ($bootstrappers as $bootstrapper) {
            $this->make($bootstrapper)->bootstrap($this);
        }
    }

    /**
     * Determine if middleware should be skipped.
     */
    public function shouldSkipMiddleware(): bool
    {
        return false; // Will be implemented later
    }

    /**
     * Get the fully qualified path to the environment file.
     */
    public function environmentFilePath(): string
    {
        return $this->basePath() . DIRECTORY_SEPARATOR . $this->environmentFile();
    }

    /**
     * Determine if the application configuration is cached.
     */
    public function configurationIsCached(): bool
    {
        return false; // Will be implemented in later phases
    }

    /**
     * Determine if the application routes are cached.
     */
    public function routesAreCached(): bool
    {
        return file_exists($this->getCachedRoutesPath());
    }

    /**
     * Get the path to the cached routes file.
     */
    public function getCachedRoutesPath(): string
    {
        return $this->bootstrapPath('cache/routes.php');
    }

    /**
     * Terminate the application.
     */
    public function terminate(): void
    {
        // Terminate all registered services
        foreach ($this->serviceProviders as $provider) {
            if (method_exists($provider, 'terminate')) {
                $provider->terminate();
            }
        }
    }
}