<?php

declare(strict_types=1);

namespace Tests\Routing;

use Horizon\Routing\Router;
use Horizon\Routing\RouteGroup;
use Tests\TestCase;

class RouteGroupTest extends TestCase
{
    protected Router $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new Router($this->app);
    }

    /** @test */
    public function it_can_register_route_groups_with_prefix(): void
    {
        $this->router->prefix('api')->group(function (Router $router) {
            $router->get('/users', function () {
                return 'api users';
            });
        });

        $routes = $this->router->getRoutes()->getRoutes();
        $route = $routes[0];

        $this->assertEquals('/api/users', $route->uri());
    }

    /** @test */
    public function it_can_register_route_groups_with_middleware(): void
    {
        $this->router->middleware('auth')->group(function (Router $router) {
            $router->get('/dashboard', function () {
                return 'dashboard';
            });
        });

        $routes = $this->router->getRoutes()->getRoutes();
        $route = $routes[0];

        $this->assertContains('auth', $route->gatherMiddleware());
    }

    /** @test */
    public function it_can_register_route_groups_with_namespace(): void
    {
        $this->router->namespace('App\\Http\\Controllers\\Admin')->group(function (Router $router) {
            $router->get('/users', 'UserController@index');
        });

        $routes = $this->router->getRoutes()->getRoutes();
        $route = $routes[0];

        $action = $route->getAction();
        $this->assertStringContains('App\\Http\\Controllers\\Admin\\UserController', $action['uses']);
    }

    /** @test */
    public function it_can_register_nested_route_groups(): void
    {
        $this->router->prefix('api')->middleware('cors')->group(function (Router $router) {
            $router->prefix('v1')->middleware('api')->group(function (Router $router) {
                $router->get('/users', function () {
                    return 'api v1 users';
                });
            });
        });

        $routes = $this->router->getRoutes()->getRoutes();
        $route = $routes[0];

        $this->assertEquals('/api/v1/users', $route->uri());
        
        $middleware = $route->gatherMiddleware();
        $this->assertContains('cors', $middleware);
        $this->assertContains('api', $middleware);
    }

    /** @test */
    public function it_can_register_route_groups_with_name_prefix(): void
    {
        $this->router->name('admin.')->prefix('admin')->group(function (Router $router) {
            $router->get('/users', function () {
                return 'admin users';
            })->name('users');
        });

        $routes = $this->router->getRoutes()->getRoutes();
        $route = $routes[0];

        $this->assertEquals('admin.users', $route->getName());
    }

    /** @test */
    public function it_can_register_route_groups_with_domain(): void
    {
        $this->router->domain('api.example.com')->group(function (Router $router) {
            $router->get('/status', function () {
                return 'api status';
            });
        });

        $routes = $this->router->getRoutes()->getRoutes();
        $route = $routes[0];

        $this->assertEquals('api.example.com', $route->domain());
    }

    /** @test */
    public function it_can_merge_route_group_attributes(): void
    {
        $old = [
            'prefix' => 'api',
            'middleware' => ['cors'],
            'namespace' => 'App\\Http\\Controllers'
        ];

        $new = [
            'prefix' => 'v1',
            'middleware' => ['auth'],
            'where' => ['id' => '[0-9]+']
        ];

        $merged = RouteGroup::mergeAttributes($new, $old);

        $this->assertEquals('api/v1', $merged['prefix']);
        $this->assertEquals('App\\Http\\Controllers', $merged['namespace']);
        $this->assertEquals(['cors', 'auth'], $merged['middleware']);
        $this->assertEquals(['id' => '[0-9]+'], $merged['where']);
    }

    /** @test */
    public function it_merges_prefix_attributes_correctly(): void
    {
        // Test empty prefixes
        $this->assertEquals('', RouteGroup::mergePrefix([], []));
        
        // Test one empty prefix
        $this->assertEquals('api', RouteGroup::mergePrefix(['prefix' => 'api'], []));
        $this->assertEquals('v1', RouteGroup::mergePrefix([], ['prefix' => 'v1']));
        
        // Test both prefixes
        $this->assertEquals('api/v1', RouteGroup::mergePrefix(
            ['prefix' => 'v1'], 
            ['prefix' => 'api']
        ));
        
        // Test with slashes
        $this->assertEquals('api/v1', RouteGroup::mergePrefix(
            ['prefix' => '/v1/'], 
            ['prefix' => '/api/']
        ));
    }

    /** @test */
    public function it_merges_namespace_attributes_correctly(): void
    {
        // Test empty namespaces
        $this->assertNull(RouteGroup::mergeNamespace([], []));
        
        // Test one empty namespace
        $this->assertEquals('App\\Controllers', RouteGroup::mergeNamespace(
            ['namespace' => 'App\\Controllers'], []
        ));
        
        // Test both namespaces
        $this->assertEquals('App\\Http\\Controllers\\Api', RouteGroup::mergeNamespace(
            ['namespace' => 'Api'], 
            ['namespace' => 'App\\Http\\Controllers']
        ));
        
        // Test with backslashes
        $this->assertEquals('App\\Http\\Controllers\\Api\\V1', RouteGroup::mergeNamespace(
            ['namespace' => '\\Api\\V1\\'], 
            ['namespace' => '\\App\\Http\\Controllers\\']
        ));
    }

    /** @test */
    public function it_merges_middleware_attributes_correctly(): void
    {
        // Test empty middleware
        $this->assertEquals([], RouteGroup::mergeMiddleware([], []));
        
        // Test string middleware
        $this->assertEquals(['cors', 'auth'], RouteGroup::mergeMiddleware(
            ['middleware' => 'auth'], 
            ['middleware' => 'cors']
        ));
        
        // Test array middleware
        $this->assertEquals(['cors', 'api', 'auth', 'throttle'], RouteGroup::mergeMiddleware(
            ['middleware' => ['auth', 'throttle']], 
            ['middleware' => ['cors', 'api']]
        ));
        
        // Test unique middleware
        $this->assertEquals(['auth', 'cors'], RouteGroup::mergeMiddleware(
            ['middleware' => ['auth', 'cors']], 
            ['middleware' => ['auth']]
        ));
    }

    /** @test */
    public function it_can_dispatch_grouped_routes(): void
    {
        $this->router->prefix('api/v1')->group(function (Router $router) {
            $router->get('/users/{id}', function ($id) {
                return "API User {$id}";
            });
        });

        $request = $this->createRequest('GET', '/api/v1/users/123');
        $response = $this->router->dispatch($request);

        $this->assertEquals('API User 123', $response->getContent());
    }
}