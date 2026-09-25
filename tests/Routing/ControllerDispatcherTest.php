<?php

declare(strict_types=1);

namespace Tests\Routing;

use Horizon\Routing\ControllerDispatcher;
use Horizon\Routing\Router;
use Horizon\Http\Controllers\Controller;
use Tests\TestCase;

class TestController extends Controller
{
    public function index()
    {
        return 'controller index';
    }

    public function show(int $id)
    {
        return "show user {$id}";
    }

    public function withRequest(\Horizon\Contracts\Http\RequestInterface $request)
    {
        return 'request injected: ' . $request->method();
    }

    public function withMultipleDependencies(int $id, \Horizon\Contracts\Http\RequestInterface $request, ?string $optional = 'default')
    {
        return "id: {$id}, method: {$request->method()}, optional: {$optional}";
    }
}

class ControllerDispatcherTest extends TestCase
{
    protected ControllerDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = new ControllerDispatcher($this->app);
    }

    /** @test */
    public function it_can_dispatch_controller_methods(): void
    {
        $router = new Router($this->app);
        $route = $router->get('/test', TestController::class . '@index');
        
        $request = $this->createRequest('GET', '/test');
        
        $response = $this->dispatcher->dispatch($route, $request, TestController::class, 'index');
        
        $this->assertEquals('controller index', $response);
    }

    /** @test */
    public function it_can_inject_route_parameters(): void
    {
        $router = new Router($this->app);
        $route = $router->get('/users/{id}', TestController::class . '@show');
        $route->bind($this->createRequest('GET', '/users/123'));
        
        $request = $this->createRequest('GET', '/users/123');
        
        $response = $this->dispatcher->dispatch($route, $request, TestController::class, 'show');
        
        $this->assertEquals('show user 123', $response);
    }

    /** @test */
    public function it_can_inject_request_interface(): void
    {
        $router = new Router($this->app);
        $route = $router->get('/request-test', TestController::class . '@withRequest');
        
        $request = $this->createRequest('POST', '/request-test');
        
        $response = $this->dispatcher->dispatch($route, $request, TestController::class, 'withRequest');
        
        $this->assertEquals('request injected: POST', $response);
    }

    /** @test */
    public function it_can_resolve_multiple_dependencies(): void
    {
        $router = new Router($this->app);
        $route = $router->get('/complex/{id}', TestController::class . '@withMultipleDependencies');
        $route->bind($this->createRequest('GET', '/complex/456'));
        
        $request = $this->createRequest('PUT', '/complex/456');
        
        $response = $this->dispatcher->dispatch($route, $request, TestController::class, 'withMultipleDependencies');
        
        $this->assertEquals('id: 456, method: PUT, optional: default', $response);
    }

    /** @test */
    public function it_resolves_controller_from_container(): void
    {
        // Bind controller in container with specific configuration
        $this->app->bind(TestController::class, function () {
            $controller = new TestController();
            // Could set properties or call methods here
            return $controller;
        });

        $router = new Router($this->app);
        $route = $router->get('/test', TestController::class . '@index');
        
        $request = $this->createRequest('GET', '/test');
        
        $response = $this->dispatcher->dispatch($route, $request, TestController::class, 'index');
        
        $this->assertEquals('controller index', $response);
    }

    /** @test */
    public function it_can_get_controller_namespace(): void
    {
        $reflection = new \ReflectionClass($this->dispatcher);
        $method = $reflection->getMethod('getController');
        $method->setAccessible(true);

        // Test without namespace (should add default namespace)
        $result1 = $method->invoke($this->dispatcher, 'UserController');
        $this->assertEquals('App\\Http\\Controllers\\UserController', $result1);

        // Test with namespace (should use as-is)
        $result2 = $method->invoke($this->dispatcher, 'Tests\\Routing\\TestController');
        $this->assertEquals('Tests\\Routing\\TestController', $result2);
    }

    /** @test */
    public function it_handles_method_parameter_resolution_failures_gracefully(): void
    {
        $router = new Router($this->app);
        
        // Create a route with a controller method that has unresolvable parameters
        $route = $router->get('/fail', function (UnresolvableClass $unresolvable) {
            return 'should not reach here';
        });
        
        $request = $this->createRequest('GET', '/fail');
        
        $this->expectException(\InvalidArgumentException::class);
        
        // Try to resolve method dependencies
        $this->dispatcher->resolveMethodDependencies(
            new TestController(),
            'nonExistentMethod',
            [],
            $request
        );
    }

    /** @test */
    public function it_can_resolve_method_dependencies(): void
    {
        $controller = new TestController();
        $request = $this->createRequest('GET', '/test');
        
        $dependencies = $this->dispatcher->resolveMethodDependencies(
            $controller,
            'withRequest',
            [],
            $request
        );
        
        $this->assertCount(1, $dependencies);
        $this->assertInstanceOf(\Horizon\Contracts\Http\RequestInterface::class, $dependencies[0]);
    }
}