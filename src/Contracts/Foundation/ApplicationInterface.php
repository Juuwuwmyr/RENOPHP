<?php

declare(strict_types=1);

namespace Reno\Contracts\Foundation;

use Reno\Contracts\Container\ContainerInterface;

interface ApplicationInterface extends ContainerInterface
{
    /**
     * Get the version number of the application.
     */
    public function version(): string;

    /**
     * Get the base path of the application installation.
     */
    public function basePath(string $path = ''): string;

    /**
     * Get the path to the bootstrap directory.
     */
    public function bootstrapPath(string $path = ''): string;

    /**
     * Get the path to the application configuration files.
     */
    public function configPath(string $path = ''): string;

    /**
     * Get the path to the database directory.
     */
    public function databasePath(string $path = ''): string;

    /**
     * Get the path to the language files.
     */
    public function langPath(string $path = ''): string;

    /**
     * Get the path to the public directory.
     */
    public function publicPath(string $path = ''): string;

    /**
     * Get the path to the resources directory.
     */
    public function resourcePath(string $path = ''): string;

    /**
     * Get the path to the storage directory.
     */
    public function storagePath(string $path = ''): string;

    /**
     * Get or check the current application environment.
     */
    public function environment(string ...$environments): string|bool;

    /**
     * Determine if the application is running in the console.
     */
    public function runningInConsole(): bool;

    /**
     * Determine if the application is in debug mode.
     */
    public function hasDebugModeEnabled(): bool;

    /**
     * Get the application namespace.
     */
    public function getNamespace(): string;

    /**
     * Register all of the configured providers.
     */
    public function registerConfiguredProviders(): void;

    /**
     * Register a service provider with the application.
     */
    public function register(mixed $provider, bool $force = false): mixed;

    /**
     * Get the registered service provider instances.
     */
    public function getProviders(mixed $provider): array;

    /**
     * Boot the application's service providers.
     */
    public function boot(): void;

    /**
     * Determine if the application has booted.
     */
    public function isBooted(): bool;

    /**
     * Register a callback to be run after loading the environment.
     */
    public function afterLoadingEnvironment(callable $callback): void;

    /**
     * Register a callback to be run before a bootstrapper.
     */
    public function beforeBootstrapping(string $bootstrapper, callable $callback): void;

    /**
     * Register a callback to be run after a bootstrapper.
     */
    public function afterBootstrapping(string $bootstrapper, callable $callback): void;

    /**
     * Terminate the application.
     */
    public function terminate(): void;
}