<?php

declare(strict_types=1);

namespace Tests;

use Horizon\Foundation\Application;
use Horizon\Contracts\Http\RequestInterface;
use Horizon\Http\Request;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase
{
    /**
     * The application instance.
     */
    protected Application $app;

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->createApplication();
    }

    /**
     * Create the application.
     */
    protected function createApplication(): void
    {
        $this->app = new Application(
            dirname(__DIR__)
        );

        $this->app->singleton('request', function () {
            return Request::createFromGlobals();
        });
    }

    /**
     * Create a mock request.
     */
    protected function createRequest(
        string $method = 'GET',
        string $uri = '/',
        array $parameters = [],
        array $cookies = [],
        array $files = [],
        array $server = [],
        ?string $content = null
    ): RequestInterface {
        $server = array_merge([
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri,
            'SCRIPT_NAME' => '/index.php',
        ], $server);

        return new Request($parameters, $parameters, [], $cookies, $files, $server, $content);
    }

    /**
     * Assert that a response has the given status code.
     */
    protected function assertResponseStatus(int $expectedStatus, $response): void
    {
        $this->assertEquals(
            $expectedStatus,
            $response->getStatusCode(),
            "Expected status code {$expectedStatus}, got {$response->getStatusCode()}"
        );
    }

    /**
     * Assert that a response contains the given content.
     */
    protected function assertResponseContains(string $needle, $response): void
    {
        $this->assertStringContainsString(
            $needle,
            $response->getContent(),
            "Response does not contain expected content: {$needle}"
        );
    }

    /**
     * Assert that a route exists.
     */
    protected function assertRouteExists(string $method, string $uri): void
    {
        $router = $this->app->make('router');
        $request = $this->createRequest($method, $uri);

        try {
            $route = $router->getRoutes()->match($request);
            $this->assertNotNull($route, "No route found for {$method} {$uri}");
        } catch (\Exception $e) {
            $this->fail("Route {$method} {$uri} does not exist: " . $e->getMessage());
        }
    }

    /**
     * Assert that a named route exists.
     */
    protected function assertNamedRouteExists(string $name): void
    {
        $router = $this->app->make('router');
        $route = $router->getRoutes()->getByName($name);
        
        $this->assertNotNull($route, "Named route '{$name}' does not exist");
    }

    /**
     * Tear down the test environment.
     */
    protected function tearDown(): void
    {
        parent::tearDown();
    }
}