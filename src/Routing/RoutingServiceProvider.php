<?php

declare(strict_types=1);

namespace Horizon\Routing;

use Horizon\Contracts\Routing\RouterInterface;
use Horizon\Contracts\Routing\UrlGeneratorInterface;
use Horizon\Support\ServiceProvider;

class RoutingServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerRouter();
        $this->registerRouteModelBinding();
        $this->registerUrlGenerator();
    }

    /**
     * Register the router instance.
     */
    protected function registerRouter(): void
    {
        $this->app->singleton('router', function ($app) {
            return new Router($app);
        });

        $this->app->alias('router', RouterInterface::class);
        $this->app->alias('router', Router::class);
    }

    /**
     * Register the route model binding service.
     */
    protected function registerRouteModelBinding(): void
    {
        $this->app->singleton(RouteModelBinding::class, function ($app) {
            return new RouteModelBinding($app);
        });
    }

    /**
     * Register the URL generator service.
     */
    protected function registerUrlGenerator(): void
    {
        $this->app->singleton('url', function ($app) {
            $routes = $app['router']->getRoutes();
            $request = $app['request'];

            return new UrlGenerator($routes, $request);
        });

        $this->app->alias('url', UrlGeneratorInterface::class);
        $this->app->alias('url', UrlGenerator::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}