<?php

declare(strict_types=1);

namespace Tests\Routing;

use Horizon\Routing\Router;
use Horizon\Routing\Route;
use Horizon\Http\Exceptions\NotFoundHttpException;
use Horizon\Http\Exceptions\MethodNotAllowedException;
use Tests\TestCase;

class RouterTest extends TestCase
{
    protected Router $router;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new Router($this->app);
    }

    /** @test */
    public function it_can_register_get_routes(): void
    {
        $route = $this->router->get('/users', function () {
            return 'users index';
        });

        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals(['GET', 'HEAD'], $route->methods());
        $this->assertEquals('/users', $route->uri());
    }

    /** @test */
    public function it_can_register_post_routes(): void
    {
        $route = $this->router->post('/users', function () {
            return 'create user';
        });

        $this->assertInstanceOf(Route::class, $route);
        $this->assertEquals(['POST'], $route->methods());
        $this->assertEquals('/users', $route->uri());
    }

    /** @test */
    public function it_can_register_put_routes(): void
    {
        $route = $this->router->put('/users/{id}', function ($id) {
            return "update user {$id}";
        });

        $this->assertEquals(['PUT'], $route->methods());
        $this->assertEquals('/users/{id}', $route->uri());
    }

    /** @test */
    public function it_can_register_patch_routes(): void
    {
        $route = $this->router->patch('/users/{id}', function ($id) {
            return "patch user {$id}";
        });

        $this->assertEquals(['PATCH'], $route->methods());
    }

    /** @test */
    public function it_can_register_delete_routes(): void
    {
        $route = $this->router->delete('/users/{id}', function ($id) {
            return "delete user {$id}";
        });

        $this->assertEquals(['DELETE'], $route->methods());
    }

    /** @test */
    public function it_can_register_options_routes(): void
    {
        $route = $this->router->options('/users', function () {
            return 'options';
        });

        $this->assertEquals(['OPTIONS'], $route->methods());
    }

    /** @test */
    public function it_can_register_any_routes(): void
    {
        $route = $this->router->any('/catch-all', function () {
            return 'any method';
        });

        $this->assertEquals(Router::$verbs, $route->methods());
    }

    /** @test */
    public function it_can_register_match_routes(): void
    {
        $route = $this->router->match(['GET', 'POST'], '/contact', function () {
            return 'contact';
        });

        $this->assertEquals(['GET', 'POST'], $route->methods());
    }

    /** @test */
    public function it_can_dispatch_simple_routes(): void
    {
        $this->router->get('/test', function () {
            return 'test response';
        });

        $request = $this->createRequest('GET', '/test');
        $response = $this->router->dispatch($request);

        $this->assertEquals('test response', $response->getContent());
    }

    /** @test */
    public function it_can_dispatch_routes_with_parameters(): void
    {
        $this->router->get('/users/{id}', function ($id) {
            return "User ID: {$id}";
        });

        $request = $this->createRequest('GET', '/users/123');
        $response = $this->router->dispatch($request);

        $this->assertEquals('User ID: 123', $response->getContent());
    }

    /** @test */
    public function it_can_dispatch_routes_with_optional_parameters(): void
    {
        $this->router->get('/posts/{category?}', function ($category = 'all') {
            return "Category: {$category}";
        });

        // With parameter
        $request1 = $this->createRequest('GET', '/posts/tech');
        $response1 = $this->router->dispatch($request1);
        $this->assertEquals('Category: tech', $response1->getContent());

        // Without parameter
        $request2 = $this->createRequest('GET', '/posts');
        $response2 = $this->router->dispatch($request2);
        $this->assertEquals('Category: all', $response2->getContent());
    }

    /** @test */
    public function it_throws_not_found_for_unregistered_routes(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $request = $this->createRequest('GET', '/nonexistent');
        $this->router->dispatch($request);
    }

    /** @test */
    public function it_throws_method_not_allowed_for_wrong_method(): void
    {
        $this->expectException(MethodNotAllowedException::class);

        $this->router->get('/users', function () {
            return 'users';
        });

        $request = $this->createRequest('POST', '/users');
        $this->router->dispatch($request);
    }

    /** @test */
    public function it_can_register_fallback_routes(): void
    {
        $this->router->get('/existing', function () {
            return 'existing';
        });

        $this->router->fallback(function () {
            return 'fallback';
        });

        // Normal route works
        $request1 = $this->createRequest('GET', '/existing');
        $response1 = $this->router->dispatch($request1);
        $this->assertEquals('existing', $response1->getContent());

        // Fallback works for non-existing routes
        $request2 = $this->createRequest('GET', '/nonexistent');
        $response2 = $this->router->dispatch($request2);
        $this->assertEquals('fallback', $response2->getContent());
    }

    /** @test */
    public function it_can_register_named_routes(): void
    {
        $route = $this->router->get('/users', function () {
            return 'users';
        })->name('users.index');

        $this->assertEquals('users.index', $route->getName());
        
        $namedRoute = $this->router->getRoutes()->getByName('users.index');
        $this->assertNotNull($namedRoute);
        $this->assertEquals('/users', $namedRoute->uri());
    }

    /** @test */
    public function it_can_register_routes_with_middleware(): void
    {
        $route = $this->router->get('/protected', function () {
            return 'protected';
        })->middleware('auth');

        $middleware = $route->gatherMiddleware();
        $this->assertContains('auth', $middleware);
    }

    /** @test */
    public function it_can_register_routes_with_constraints(): void
    {
        $route = $this->router->get('/users/{id}', function ($id) {
            return "User {$id}";
        })->where('id', '[0-9]+');

        $this->assertEquals('[0-9]+', $route->wheres()['id']);
    }

    /** @test */
    public function it_enforces_route_constraints(): void
    {
        $this->router->get('/users/{id}', function ($id) {
            return "User {$id}";
        })->where('id', '[0-9]+');

        // Valid ID should work
        $request1 = $this->createRequest('GET', '/users/123');
        $response1 = $this->router->dispatch($request1);
        $this->assertEquals('User 123', $response1->getContent());

        // Invalid ID should not match
        $this->expectException(NotFoundHttpException::class);
        $request2 = $this->createRequest('GET', '/users/abc');
        $this->router->dispatch($request2);
    }

    /** @test */
    public function it_can_register_controller_routes(): void
    {
        $route = $this->router->get('/users', 'UserController@index');

        $action = $route->getAction();
        $this->assertEquals('UserController@index', $action['uses']);
        $this->assertEquals('UserController', $action['controller']);
        $this->assertEquals('index', $action['method']);
    }
}