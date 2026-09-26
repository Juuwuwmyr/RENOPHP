<?php

declare(strict_types=1);

namespace Reno\Support;

use Reno\Contracts\Foundation\ApplicationInterface;

abstract class ServiceProvider
{
    /**
     * The application instance.
     */
    protected ApplicationInterface $app;

    /**
     * All of the registered booting callbacks.
     */
    protected array $bootingCallbacks = [];

    /**
     * All of the registered booted callbacks.
     */
    protected array $bootedCallbacks = [];

    /**
     * The paths that should be published.
     */
    public static array $publishes = [];

    /**
     * The paths that should be published by group.
     */
    public static array $publishGroups = [];

    /**
     * Indicates if loading of the provider is deferred.
     */
    protected bool $defer = false;

    /**
     * The bindings to register.
     */
    public array $bindings = [];

    /**
     * The singletons to register.
     */
    public array $singletons = [];

    /**
     * Create a new service provider instance.
     */
    public function __construct(ApplicationInterface $app)
    {
        $this->app = $app;
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Register a booting callback to be run before the "boot" method is called.
     */
    public function booting(callable $callback): void
    {
        $this->bootingCallbacks[] = $callback;
    }

    /**
     * Register a booted callback to be run after the "boot" method is called.
     */
    public function booted(callable $callback): void
    {
        $this->bootedCallbacks[] = $callback;
    }

    /**
     * Call the registered booting callbacks.
     */
    public function callBootingCallbacks(): void
    {
        $index = 0;

        while ($index < count($this->bootingCallbacks)) {
            $this->app->call($this->bootingCallbacks[$index]);

            $index++;
        }
    }

    /**
     * Call the registered booted callbacks.
     */
    public function callBootedCallbacks(): void
    {
        $index = 0;

        while ($index < count($this->bootedCallbacks)) {
            $this->app->call($this->bootedCallbacks[$index]);

            $index++;
        }
    }

    /**
     * Merge the given configuration with the existing configuration.
     */
    protected function mergeConfigFrom(string $path, string $key): void
    {
        if (!($this->app->configurationIsCached() && $this->app->bound('config'))) {
            $config = $this->app->make('config');

            $config->set($key, array_merge(
                require $path, $config->get($key, [])
            ));
        }
    }

    /**
     * Load the given routes file if routes are not already cached.
     */
    protected function loadRoutesFrom(string $path): void
    {
        if (!($this->app->routesAreCached() && $this->app->bound('router'))) {
            require $path;
        }
    }

    /**
     * Register a view file namespace.
     */
    protected function loadViewsFrom(string|array $path, string $namespace): void
    {
        $this->callAfterResolving('view', function ($view) use ($path, $namespace) {
            if (isset($this->app->config['view']['paths']) &&
                is_array($this->app->config['view']['paths'])) {
                foreach ($this->app->config['view']['paths'] as $viewPath) {
                    if (is_dir($appPath = $viewPath.'/vendor/'.$namespace)) {
                        $view->addNamespace($namespace, $appPath);
                    }
                }
            }

            $view->addNamespace($namespace, $path);
        });
    }

    /**
     * Register a translation file namespace.
     */
    protected function loadTranslationsFrom(string $path, string $namespace): void
    {
        $this->callAfterResolving('translator', function ($translator) use ($path, $namespace) {
            $translator->addNamespace($namespace, $path);
        });
    }

    /**
     * Register a JSON translation file path.
     */
    protected function loadJsonTranslationsFrom(string $path): void
    {
        $this->callAfterResolving('translator', function ($translator) use ($path) {
            $translator->addJsonPath($path);
        });
    }

    /**
     * Register database migration paths.
     */
    protected function loadMigrationsFrom(string|array $paths): void
    {
        $this->callAfterResolving('migrator', function ($migrator) use ($paths) {
            foreach ((array) $paths as $path) {
                $migrator->path($path);
            }
        });
    }

    /**
     * Register paths to be published by the publish command.
     */
    protected function publishes(array $paths, ?string $groups = null): void
    {
        $this->ensurePublishArrayInitialized($class = static::class);

        static::$publishes[$class] = array_merge(static::$publishes[$class], $paths);

        if ($groups) {
            $this->addPublishGroup($groups, $paths);
        }
    }

    /**
     * Register paths to be published by the publish command.
     */
    protected function publishesConfig(array $paths, ?string $groups = null): void
    {
        $this->publishes($paths, $groups ?: 'config');
    }

    /**
     * Add a publish group / tag to the list of publishable assets.
     */
    protected function addPublishGroup(string $group, array $paths): void
    {
        if (!array_key_exists($group, static::$publishGroups)) {
            static::$publishGroups[$group] = [];
        }

        static::$publishGroups[$group] = array_merge(
            static::$publishGroups[$group], $paths
        );
    }

    /**
     * Ensure the publish array for the service provider is initialized.
     */
    protected function ensurePublishArrayInitialized(string $class): void
    {
        if (!array_key_exists($class, static::$publishes)) {
            static::$publishes[$class] = [];
        }
    }

    /**
     * Register an after resolving callback for the given abstract.
     */
    protected function callAfterResolving(string $name, callable $callback): void
    {
        $this->app->afterResolving($name, $callback);

        if ($this->app->resolved($name)) {
            $callback($this->app->make($name), $this->app);
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [];
    }

    /**
     * Get the events that trigger this service provider to register.
     */
    public function when(): array
    {
        return [];
    }

    /**
     * Determine if the provider is deferred.
     */
    public function isDeferred(): bool
    {
        return $this->defer;
    }

    /**
     * Get the default providers for a Laravel application.
     */
    public static function defaultProviders(): array
    {
        return [
            // Core providers would go here
        ];
    }

    /**
     * Get all of the paths to publish.
     */
    public static function pathsToPublish(?string $provider = null, ?string $group = null): array
    {
        if (!is_null($paths = static::pathsForProviderOrGroup($provider, $group))) {
            return $paths;
        }

        return collect(static::$publishes)->reduce(function ($paths, $p) {
            return array_merge($paths, $p);
        }, []);
    }

    /**
     * Get the paths for the provider or group (or both).
     */
    protected static function pathsForProviderOrGroup(?string $provider, ?string $group): ?array
    {
        if ($provider && $group) {
            return static::pathsForProviderAndGroup($provider, $group);
        } elseif ($group && array_key_exists($group, static::$publishGroups)) {
            return static::$publishGroups[$group];
        } elseif ($provider && array_key_exists($provider, static::$publishes)) {
            return static::$publishes[$provider];
        } elseif ($group || $provider) {
            return [];
        }

        return null;
    }

    /**
     * Get the paths for the provider and group.
     */
    protected static function pathsForProviderAndGroup(string $provider, string $group): array
    {
        if (!empty(static::$publishes[$provider]) && !empty(static::$publishGroups[$group])) {
            return array_intersect_key(static::$publishes[$provider], static::$publishGroups[$group]);
        }

        return [];
    }

    /**
     * Get the service providers available for publishing.
     */
    public static function publishableProviders(): array
    {
        return array_keys(static::$publishes);
    }

    /**
     * Get the groups available for publishing.
     */
    public static function publishableGroups(): array
    {
        return array_keys(static::$publishGroups);
    }
}