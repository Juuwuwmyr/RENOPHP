<?php

declare(strict_types=1);

namespace Tests\Routing;

use Horizon\Routing\Router;
use Horizon\Routing\UrlGenerator;
use Horizon\Routing\Exceptions\RouteNotFoundException;
use Tests\TestCase;

class UrlGeneratorTest extends TestCase
{
    protected Router $router;
    protected UrlGenerator $urlGenerator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->router = new Router($this->app);
        
        $request = $this->createRequest('GET', '/', [], [], [], [
            'HTTP_HOST' => 'localhost',
            'SERVER_NAME' => 'localhost',
            'REQUEST_SCHEME' => 'http',
            'SERVER_PORT' => '80',
        ]);
        
        $this->urlGenerator = new UrlGenerator($this->router->getRoutes(), $request);
    }

    /** @test */
    public function it_can_generate_urls_to_paths(): void
    {
        $url = $this->urlGenerator->to('/users');
        $this->assertEquals('http://localhost/users', $url);
    }

    /** @test */
    public function it_can_generate_urls_with_query_parameters(): void
    {
        $url = $this->urlGenerator->to('/users', ['page' => 2, 'limit' => 10]);
        $this->assertEquals('http://localhost/users?page=2&limit=10', $url);
    }

    /** @test */
    public function it_can_generate_secure_urls(): void
    {
        $url = $this->urlGenerator->secure('/login');
        $this->assertEquals('https://localhost/login', $url);
    }

    /** @test */
    public function it_can_generate_asset_urls(): void
    {
        $url = $this->urlGenerator->asset('css/app.css');
        $this->assertEquals('http://localhost/css/app.css', $url);
    }

    /** @test */
    public function it_can_generate_secure_asset_urls(): void
    {
        $url = $this->urlGenerator->secureAsset('js/app.js');
        $this->assertEquals('https://localhost/js/app.js', $url);
    }

    /** @test */
    public function it_validates_urls_correctly(): void
    {
        $this->assertTrue($this->urlGenerator->isValidUrl('https://example.com'));
        $this->assertTrue($this->urlGenerator->isValidUrl('http://example.com'));
        $this->assertTrue($this->urlGenerator->isValidUrl('mailto:test@example.com'));
        $this->assertTrue($this->urlGenerator->isValidUrl('tel:+1234567890'));
        $this->assertFalse($this->urlGenerator->isValidUrl('/relative/path'));
    }

    /** @test */
    public function it_can_generate_named_route_urls(): void
    {
        $this->router->get('/users/{id}', function ($id) {
            return "User {$id}";
        })->name('users.show');

        $url = $this->urlGenerator->route('users.show', ['id' => 123]);
        $this->assertEquals('http://localhost/users/123', $url);
    }

    /** @test */
    public function it_can_generate_named_route_urls_with_query_parameters(): void
    {
        $this->router->get('/users/{id}', function ($id) {
            return "User {$id}";
        })->name('users.show');

        $url = $this->urlGenerator->route('users.show', ['id' => 123, 'tab' => 'profile']);
        $this->assertEquals('http://localhost/users/123?tab=profile', $url);
    }

    /** @test */
    public function it_can_generate_named_route_urls_with_optional_parameters(): void
    {
        $this->router->get('/posts/{category?}', function ($category = null) {
            return "Posts in {$category}";
        })->name('posts.index');

        // With parameter
        $url1 = $this->urlGenerator->route('posts.index', ['category' => 'tech']);
        $this->assertEquals('http://localhost/posts/tech', $url1);

        // Without parameter
        $url2 = $this->urlGenerator->route('posts.index');
        $this->assertEquals('http://localhost/posts', $url2);
    }

    /** @test */
    public function it_throws_exception_for_missing_named_routes(): void
    {
        $this->expectException(RouteNotFoundException::class);
        $this->expectExceptionMessage('Route [nonexistent] not found.');

        $this->urlGenerator->route('nonexistent');
    }

    /** @test */
    public function it_throws_exception_for_missing_required_parameters(): void
    {
        $this->router->get('/users/{id}', function ($id) {
            return "User {$id}";
        })->name('users.show');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Route parameters not provided');

        $this->urlGenerator->route('users.show'); // Missing required 'id' parameter
    }

    /** @test */
    public function it_can_format_parameters_correctly(): void
    {
        $this->router->get('/users/{id}', function ($id) {
            return "User {$id}";
        })->name('users.show');

        // Test URL encoding
        $url = $this->urlGenerator->route('users.show', ['id' => 'john doe']);
        $this->assertEquals('http://localhost/users/john%20doe', $url);

        // Test special characters
        $url2 = $this->urlGenerator->route('users.show', ['id' => 'user@domain.com']);
        $this->assertEquals('http://localhost/users/user%40domain.com', $url2);
    }

    /** @test */
    public function it_can_handle_domain_routes(): void
    {
        $this->router->domain('api.localhost')->group(function (Router $router) {
            $router->get('/status', function () {
                return 'API Status';
            })->name('api.status');
        });

        $url = $this->urlGenerator->route('api.status');
        $this->assertEquals('http://api.localhost/status', $url);
    }

    /** @test */
    public function it_can_generate_relative_urls(): void
    {
        $this->router->get('/users/{id}', function ($id) {
            return "User {$id}";
        })->name('users.show');

        $url = $this->urlGenerator->route('users.show', ['id' => 123], false);
        $this->assertEquals('/users/123', $url);
    }

    /** @test */
    public function it_extracts_query_strings_correctly(): void
    {
        [$path, $query] = $this->invokeMethod($this->urlGenerator, 'extractQueryString', ['/path?foo=bar']);
        
        $this->assertEquals('/path', $path);
        $this->assertEquals('?foo=bar', $query);
    }

    /** @test */
    public function it_formats_root_urls_correctly(): void
    {
        $root = $this->urlGenerator->formatRoot('https://', 'example.com:8080');
        $this->assertEquals('https://example.com:8080', $root);
    }

    /**
     * Call protected/private method of a class.
     */
    protected function invokeMethod(object $object, string $methodName, array $parameters = []): mixed
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}