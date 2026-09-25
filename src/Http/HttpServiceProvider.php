<?php

declare(strict_types=1);

namespace Horizon\Http;

use Horizon\Contracts\Http\KernelInterface;
use Horizon\Http\Middleware\MiddlewareStack;
use Horizon\Support\ServiceProvider;

class HttpServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerHttpKernel();
        $this->registerMiddleware();
    }

    /**
     * Register the HTTP kernel.
     */
    protected function registerHttpKernel(): void
    {
        $this->app->singleton(KernelInterface::class, function ($app) {
            return new Kernel($app);
        });

        $this->app->alias(KernelInterface::class, 'kernel');
        $this->app->alias(KernelInterface::class, Kernel::class);
    }

    /**
     * Register middleware services.
     */
    protected function registerMiddleware(): void
    {
        $this->app->singleton(MiddlewareStack::class);

        // Register default middleware aliases
        $this->registerMiddlewareAliases();

        // Register default middleware groups
        $this->registerMiddlewareGroups();
    }

    /**
     * Register middleware aliases.
     */
    protected function registerMiddlewareAliases(): void
    {
        $middlewareStack = $this->app->make(MiddlewareStack::class);

        $middlewareStack
            ->alias('cors', Middleware\HandleCors::class)
            ->alias('csrf', Middleware\VerifyCsrfToken::class)
            ->alias('throttle', Middleware\ThrottleRequests::class)
            ->alias('trim', Middleware\TrimStrings::class)
            ->alias('convert.empty', Middleware\ConvertEmptyStringsToNull::class)
            ->alias('security.headers', Middleware\SecurityHeaders::class)
            ->alias('auth', Middleware\Authenticate::class)
            ->alias('signed', Middleware\ValidateSignature::class)
            ->alias('maintenance', Middleware\PreventRequestsDuringMaintenance::class);
    }

    /**
     * Register middleware groups.
     */
    protected function registerMiddlewareGroups(): void
    {
        $middlewareStack = $this->app->make(MiddlewareStack::class);

        $middlewareStack
            ->group('web', [
                'maintenance',
                'security.headers',
                'trim',
                'convert.empty',
                'csrf',
            ])
            ->group('api', [
                'maintenance',
                'security.headers', 
                'cors',
                'trim',
                'convert.empty',
                'throttle:60,1',
            ])
            ->group('auth', [
                'auth',
            ])
            ->group('signed', [
                'signed',
            ])
            ->group('secure', [
                'maintenance',
                'security.headers',
                'auth',
                'csrf',
                'throttle:30,1',
            ]);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}