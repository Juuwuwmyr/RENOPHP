<?php

declare(strict_types=1);

namespace Tests\Routing;

use Horizon\Routing\Router;
use Horizon\Routing\RouteCache;
use Horizon\Routing\CachedRouteCollection;
use Tests\TestCase;

class RouteCacheTest extends TestCase
{
    protected string $cacheFile;
    protected RouteCache $cache;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->cacheFile = sys_get_temp_dir() . '/test_routes_' . uniqid() . '.php';
        $this->cache = new RouteCache($this->cacheFile);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->cacheFile)) {
            unlink($this->cacheFile);
        }
        
        parent::tearDown();
    }

    /** @test */
    public function it_can_check_if_cache_exists(): void
    {
        $this->assertFalse($this->cache->exists());
        
        file_put_contents($this->cacheFile, '<?php return [];');
        
        $this->assertTrue($this->cache->exists());
    }

    /** @test */
    public function it_can_store_and_load_routes(): void
    {
        $router = new Router($this->app);
        
        // Register some routes
        $router->get('/users', function () { return 'users'; })->name('users.index');
        $router->post('/users', 'UserController@store')->name('users.store');
        $router->get('/users/{id}', function ($id) { return "user {$id}"; })
               ->name('users.show')
               ->where('id', '[0-9]+');

        // Store routes in cache
        $this->cache->store($router->getRoutes());
        
        $this->assertTrue($this->cache->exists());
        
        // Load routes from cache
        $cachedData = $this->cache->load();
        
        $this->assertIsArray($cachedData);
        $this->assertArrayHasKey('routes', $cachedData);
        $this->assertArrayHasKey('compiled', $cachedData);
        $this->assertArrayHasKey('names', $cachedData);
        $this->assertArrayHasKey('actions', $cachedData);
    }

    /** @test */
    public function it_can_create_cached_route_collection(): void
    {
        $router = new Router($this->app);
        
        $router->get('/users', function () { return 'users'; })->name('users.index');
        $router->get('/users/{id}', function ($id) { return "user {$id}"; })->name('users.show');

        $this->cache->store($router->getRoutes());
        $cachedData = $this->cache->load();
        
        $cachedCollection = new CachedRouteCollection($cachedData);
        
        $this->assertInstanceOf(CachedRouteCollection::class, $cachedCollection);
        $this->assertEquals(2, $cachedCollection->count());
    }

    /** @test */
    public function it_can_match_routes_from_cache(): void
    {
        $router = new Router($this->app);
        
        $router->get('/users/{id}', function ($id) { return "user {$id}"; })->name('users.show');
        $router->post('/users', function () { return 'create user'; });

        $this->cache->store($router->getRoutes());
        $cachedData = $this->cache->load();
        
        $cachedCollection = new CachedRouteCollection($cachedData);
        
        // Test GET request
        $request1 = $this->createRequest('GET', '/users/123');
        $route1 = $cachedCollection->match($request1);
        $this->assertEquals('/users/{id}', $route1->uri());
        
        // Test POST request
        $request2 = $this->createRequest('POST', '/users');
        $route2 = $cachedCollection->match($request2);
        $this->assertEquals('/users', $route2->uri());
    }

    /** @test */
    public function it_can_find_named_routes_from_cache(): void
    {
        $router = new Router($this->app);
        
        $router->get('/users', function () { return 'users'; })->name('users.index');
        $router->get('/posts', function () { return 'posts'; })->name('posts.index');

        $this->cache->store($router->getRoutes());
        $cachedData = $this->cache->load();
        
        $cachedCollection = new CachedRouteCollection($cachedData);
        
        $this->assertTrue($cachedCollection->hasNamedRoute('users.index'));
        $this->assertTrue($cachedCollection->hasNamedRoute('posts.index'));
        $this->assertFalse($cachedCollection->hasNamedRoute('nonexistent'));
        
        $userRoute = $cachedCollection->getByName('users.index');
        $this->assertNotNull($userRoute);
        $this->assertEquals('/users', $userRoute->uri());
    }

    /** @test */
    public function it_can_clear_cache(): void
    {
        // Create cache file
        file_put_contents($this->cacheFile, '<?php return [];');
        $this->assertTrue($this->cache->exists());
        
        // Clear cache
        $this->assertTrue($this->cache->clear());
        $this->assertFalse($this->cache->exists());
    }

    /** @test */
    public function it_can_get_cache_statistics(): void
    {
        $this->assertFalse($this->cache->getStats()['exists']);
        
        $router = new Router($this->app);
        $router->get('/test', function () { return 'test'; });
        
        $this->cache->store($router->getRoutes());
        
        $stats = $this->cache->getStats();
        
        $this->assertTrue($stats['exists']);
        $this->assertGreaterThan(0, $stats['size']);
        $this->assertIsString($stats['size_human']);
        $this->assertIsString($stats['created_at']);
        $this->assertIsString($stats['modified_at']);
        $this->assertEquals(1, $stats['route_count']);
    }

    /** @test */
    public function it_can_check_cache_freshness(): void
    {
        $routeFile = sys_get_temp_dir() . '/test_route_file.php';
        file_put_contents($routeFile, '<?php // route file');
        
        // Cache is not fresh when it doesn't exist
        $this->assertFalse($this->cache->isFresh([$routeFile]));
        
        // Create cache
        $router = new Router($this->app);
        $router->get('/test', function () { return 'test'; });
        $this->cache->store($router->getRoutes());
        
        // Cache should be fresh
        $this->assertTrue($this->cache->isFresh([$routeFile]));
        
        // Modify route file (make it newer)
        sleep(1); // Ensure different timestamp
        touch($routeFile);
        
        // Cache should no longer be fresh
        $this->assertFalse($this->cache->isFresh([$routeFile]));
        
        unlink($routeFile);
    }

    /** @test */
    public function it_handles_invalid_cache_file_gracefully(): void
    {
        // Create invalid cache file
        file_put_contents($this->cacheFile, 'invalid php');
        
        $this->expectException(\ParseError::class);
        
        $this->cache->load();
    }
}