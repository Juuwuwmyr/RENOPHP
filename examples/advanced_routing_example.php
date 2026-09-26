<?php

declare(strict_types=1);

/**
 * Advanced Route Groups and Resource Routing Example
 * 
 * Demonstrates the Horizon Framework's enhanced routing capabilities
 * with nested groups, custom resources, API resources, and advanced
 * routing patterns.
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "Horizon Framework - Advanced Route Groups & Resources Example\n";
echo "=============================================================\n\n";

use Horizon\Routing\Router;
use Horizon\Routing\RouteGroup;
use Horizon\Routing\ResourceRouteRegistrar;
use Horizon\Http\Request;
use Horizon\Http\Response;

try {
    echo "1. ENHANCED ROUTE GROUPS\n";
    echo "========================\n\n";

    $router = new Router();

    // Create a base API group
    $apiGroup = $router->newGroup([
        'prefix' => 'api',
        'middleware' => ['cors', 'throttle'],
        'name' => 'api.'
    ]);

    echo "✅ Created base API group with prefix 'api'\n\n";

    // Add routes to the API group
    $apiGroup->get('/status', function () {
        return Response::json(['status' => 'online', 'version' => '1.0']);
    })->name('status');

    $apiGroup->get('/health', function () {
        return Response::json(['health' => 'ok', 'timestamp' => time()]);
    })->name('health');

    echo "📋 Added basic API routes:\n";
    echo "   GET /api/status [api.status]\n";
    echo "   GET /api/health [api.health]\n\n";

    echo "2. NESTED ROUTE GROUPS\n";
    echo "======================\n\n";

    // Create versioned API groups
    $v1Group = $apiGroup->version('1', function (RouteGroup $group) {
        echo "   📝 Registering v1 API routes...\n";
        
        $group->get('/users', function () {
            return Response::json(['users' => ['John', 'Jane'], 'version' => 'v1']);
        })->name('users.index');

        $group->get('/posts', function () {
            return Response::json(['posts' => ['Post 1', 'Post 2'], 'version' => 'v1']);
        })->name('posts.index');
    });

    $v2Group = $apiGroup->version('2', function (RouteGroup $group) {
        echo "   📝 Registering v2 API routes...\n";
        
        $group->get('/users', function () {
            return Response::json(['users' => [
                ['id' => 1, 'name' => 'John Doe'],
                ['id' => 2, 'name' => 'Jane Smith']
            ], 'version' => 'v2']);
        })->name('users.index');

        $group->get('/posts', function () {
            return Response::json(['posts' => [
                ['id' => 1, 'title' => 'Advanced Routing'],
                ['id' => 2, 'title' => 'Framework Design']
            ], 'version' => 'v2']);
        })->name('posts.index');
    });

    echo "\n✅ Created versioned API groups:\n";
    echo "   V1: /api/v1/* [api.v1.*]\n";
    echo "   V2: /api/v2/* [api.v2.*]\n\n";

    echo "3. SPECIALIZED GROUPS\n";
    echo "=====================\n\n";

    // Admin group with authentication
    $adminGroup = $router->newGroup()
        ->admin(function (RouteGroup $group) {
            echo "   📝 Registering admin routes...\n";
            
            $group->get('/dashboard', function () {
                return new Response('Admin Dashboard');
            })->name('dashboard');

            $group->get('/users', function () {
                return new Response('User Management');
            })->name('users.manage');

            $group->get('/settings', function () {
                return new Response('System Settings');
            })->name('settings');
        });

    echo "\n✅ Created admin group with authentication:\n";
    echo "   GET /admin/dashboard [admin.dashboard]\n";
    echo "   GET /admin/users [admin.users.manage]\n";
    echo "   GET /admin/settings [admin.settings]\n\n";

    // Web group with CSRF protection
    $webGroup = $router->newGroup()
        ->web(function (RouteGroup $group) {
            echo "   📝 Registering web routes...\n";
            
            $group->get('/', function () {
                return new Response('Welcome Home');
            })->name('home');

            $group->get('/about', function () {
                return new Response('About Us');
            })->name('about');

            $group->get('/contact', function () {
                return new Response('Contact Form');
            })->name('contact.show');

            $group->post('/contact', function () {
                return Response::json(['message' => 'Message sent']);
            })->name('contact.send');
        });

    echo "\n✅ Created web group with CSRF protection:\n";
    echo "   GET / [home]\n";
    echo "   GET /about [about]\n";
    echo "   GET /contact [contact.show]\n";
    echo "   POST /contact [contact.send]\n\n";

    echo "4. CONDITIONAL ROUTING\n";
    echo "======================\n\n";

    // Environment-specific routes
    $router->newGroup()
        ->env(['development', 'testing'], function (RouteGroup $group) {
            echo "   📝 Registering development routes...\n";
            
            $group->get('/debug', function () {
                return Response::json(['debug' => true, 'env' => 'development']);
            });

            $group->get('/test-data', function () {
                return Response::json(['test' => 'data', 'generated' => true]);
            });
        });

    // Feature flag routing
    $router->newGroup()
        ->when(true, function (RouteGroup $group) { // Simulate feature flag
            echo "   📝 Registering feature routes...\n";
            
            $group->get('/beta-feature', function () {
                return Response::json(['feature' => 'beta', 'enabled' => true]);
            });
        });

    echo "\n✅ Added conditional routes based on environment and features\n\n";

    echo "5. BASIC RESOURCE ROUTING\n";
    echo "=========================\n\n";

    // Full resource
    $userResource = $router->resourceAdvanced('users', 'UserController');

    echo "✅ Registered full user resource:\n";
    $userRoutes = $userResource->getRoutes();
    foreach ($userRoutes as $action => $config) {
        echo "   {$config['method']} {$config['uri']} [{$config['name']}] -> {$config['controller']}\n";
    }
    echo "\n";

    // API resource (no create/edit forms)
    $postResource = $router->apiResource('posts', 'PostController');

    echo "✅ Registered API post resource:\n";
    $postRoutes = $postResource->getRoutes();
    foreach ($postRoutes as $action => $config) {
        echo "   {$config['method']} {$config['uri']} [{$config['name']}] -> {$config['controller']}\n";
    }
    echo "\n";

    echo "6. CUSTOMIZED RESOURCES\n";
    echo "=======================\n\n";

    // Customized resource with constraints and middleware
    $productGroup = $v1Group->group(['prefix' => 'products'], function (RouteGroup $group) {
        echo "   📝 Creating customized product resource...\n";
        
        $productResource = $group->resource('', 'ProductController')
            ->only(['index', 'show', 'store', 'update', 'destroy'])
            ->parameter('product')
            ->where(['product' => '[0-9]+'])
            ->middleware(['auth', 'api'])
            ->names([
                'index' => 'products.list',
                'show' => 'products.detail'
            ]);

        // Add custom member actions
        $productResource->member('activate', ['POST'])
                       ->member('deactivate', ['POST'])
                       ->member('stats', ['GET']);

        // Add custom collection actions
        $productResource->collection('featured', ['GET'])
                       ->collection('search', ['POST'], 'searchProducts');
    });

    echo "\n✅ Created customized product resource with:\n";
    echo "   - Custom parameter name: 'product'\n";
    echo "   - Numeric constraint on product ID\n";
    echo "   - Authentication middleware\n";
    echo "   - Custom route names\n";
    echo "   - Member actions: activate, deactivate, stats\n";
    echo "   - Collection actions: featured, search\n\n";

    echo "7. NESTED RESOURCES\n";
    echo "===================\n\n";

    // Nested resources for user posts
    $userGroup = $v1Group->group(['prefix' => 'users/{user}'], function (RouteGroup $group) {
        echo "   📝 Creating nested user resources...\n";
        
        // User posts
        $group->resource('posts', 'UserPostController')
              ->parameter('post')
              ->middleware(['auth']);

        // User comments
        $group->resource('comments', 'UserCommentController')
              ->only(['index', 'store', 'destroy'])
              ->parameter('comment');

        // User settings (singleton resource)
        $group->get('/settings', 'UserSettingsController@show')->name('settings.show');
        $group->put('/settings', 'UserSettingsController@update')->name('settings.update');
    });

    echo "\n✅ Created nested resources:\n";
    echo "   - User posts: /api/v1/users/{user}/posts\n";
    echo "   - User comments: /api/v1/users/{user}/comments\n";
    echo "   - User settings: /api/v1/users/{user}/settings\n\n";

    echo "8. MULTIPLE RESOURCES\n";
    echo "=====================\n\n";

    // Register multiple resources at once
    $v2Group->resources([
        'categories' => 'CategoryController',
        'tags' => ['TagController', ['only' => ['index', 'show']]],
        'files' => ['FileController', ['middleware' => ['auth', 'upload']]],
    ]);

    echo "✅ Registered multiple resources in v2 API:\n";
    echo "   - categories (full resource)\n";
    echo "   - tags (index and show only)\n";
    echo "   - files (with auth and upload middleware)\n\n";

    echo "9. ROUTE TESTING\n";
    echo "================\n\n";

    // Test various routes
    $testRoutes = [
        ['GET', '/api/status'],
        ['GET', '/api/v1/users'],
        ['GET', '/api/v2/posts'],
        ['GET', '/admin/dashboard'],
        ['GET', '/'],
        ['POST', '/contact'],
        ['GET', '/users'],
        ['POST', '/users'],
        ['GET', '/users/123'],
        ['PUT', '/users/123'],
        ['DELETE', '/users/123'],
        ['GET', '/posts'],
        ['POST', '/posts'],
        ['GET', '/api/v1/users/456/posts'],
        ['POST', '/api/v1/users/456/posts'],
    ];

    echo "🔍 Testing registered routes:\n\n";

    foreach ($testRoutes as [$method, $uri]) {
        try {
            $request = Request::create($uri, $method);
            $route = $router->matchRequest($request);
            
            echo "   ✅ {$method} {$uri}\n";
            echo "      Route: " . implode('|', $route->getMethods()) . " " . $route->getUri();
            if ($route->getName()) {
                echo " [{$route->getName()}]";
            }
            echo "\n";
            
            if (!empty($route->getParameters())) {
                echo "      Parameters: " . json_encode($route->getParameters()) . "\n";
            }
            
            if (!empty($route->getMiddleware())) {
                echo "      Middleware: " . implode(', ', $route->getMiddleware()) . "\n";
            }
            
        } catch (Exception $e) {
            echo "   ❌ {$method} {$uri}: " . substr($e->getMessage(), 0, 50) . "\n";
        }
        
        echo "\n";
    }

    echo "10. GROUP STATISTICS\n";
    echo "====================\n\n";

    echo "📊 Route Group Statistics:\n\n";

    $groups = [
        'API Group' => $apiGroup,
        'V1 Group' => $v1Group,
        'V2 Group' => $v2Group,
    ];

    foreach ($groups as $name => $group) {
        $stats = $group->getStats();
        echo "   {$name}:\n";
        echo "      Direct routes: {$stats['routes']}\n";
        echo "      Total routes: {$stats['total_routes']}\n";
        echo "      Child groups: {$stats['children']}\n";
        echo "      Depth: {$stats['depth']}\n";
        echo "      Prefix: /" . ($stats['attributes']['prefix'] ?? '') . "\n";
        echo "\n";
    }

    echo "11. ROUTER OVERVIEW\n";
    echo "===================\n\n";

    $routerStats = $router->getStats();
    echo "📈 Overall Router Statistics:\n";
    echo "   Total Routes: {$routerStats['total_routes']}\n";
    echo "   Compiled Routes: {$routerStats['compiled_routes']}\n";
    echo "   Named Routes: {$routerStats['named_routes']}\n";
    echo "   Patterns: {$routerStats['patterns']}\n\n";

    echo "📋 Routes by Method:\n";
    foreach ($routerStats['routes_by_method'] as $method => $count) {
        echo "   {$method}: {$count}\n";
    }
    echo "\n";

    echo "12. ROUTE LIST SAMPLE\n";
    echo "=====================\n\n";

    $routeList = array_slice($router->getRouteList(), 0, 10);
    echo "📋 First 10 Registered Routes:\n\n";
    
    printf("%-8s %-35s %-25s %-20s\n", 'METHOD', 'URI', 'NAME', 'ACTION');
    echo str_repeat('-', 90) . "\n";
    
    foreach ($routeList as $route) {
        printf(
            "%-8s %-35s %-25s %-20s\n",
            substr($route['methods'], 0, 7),
            substr($route['uri'], 0, 34),
            substr($route['name'] ?: '-', 0, 24),
            substr($route['action'], 0, 19)
        );
    }
    
    $totalRoutes = count($router->getRouteList());
    echo "\n... and " . ($totalRoutes - 10) . " more routes\n\n";

    echo "13. PERFORMANCE INSIGHTS\n";
    echo "========================\n\n";

    $performanceTest = function () use ($router) {
        $testRoutes = [
            '/api/status',
            '/api/v1/users',
            '/api/v2/posts',
            '/users/123',
            '/posts',
        ];

        $start = microtime(true);
        
        foreach ($testRoutes as $uri) {
            try {
                $request = Request::create($uri, 'GET');
                $router->matchRequest($request);
            } catch (Exception $e) {
                // Ignore routing errors for performance test
            }
        }
        
        return round((microtime(true) - $start) * 1000, 2);
    };

    echo "⚡ Route Matching Performance:\n";
    $iterations = [1, 10, 50, 100];
    foreach ($iterations as $count) {
        $totalTime = 0;
        for ($i = 0; $i < $count; $i++) {
            $totalTime += $performanceTest();
        }
        $avgTime = round($totalTime / $count, 2);
        echo "   {$count} iterations: avg {$avgTime}ms per batch (5 routes)\n";
    }
    echo "\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "========================================================\n";
echo "✅ ADVANCED ROUTING EXAMPLE COMPLETE!\n";
echo "========================================================\n\n";

echo "The advanced routing system provides:\n\n";

echo "🔹 HIERARCHICAL ROUTE GROUPS\n";
echo "   Nested groups with attribute inheritance and merging\n\n";

echo "🔹 SPECIALIZED GROUP TYPES\n";
echo "   API, web, admin, and versioned groups with presets\n\n";

echo "🔹 CONDITIONAL ROUTING\n";
echo "   Environment-based and feature-flag routing\n\n";

echo "🔹 FLEXIBLE RESOURCE ROUTING\n";
echo "   Full, API, and customized resources with member/collection actions\n\n";

echo "🔹 NESTED RESOURCES\n";
echo "   Parent-child resource relationships with clean URLs\n\n";

echo "🔹 ADVANCED CUSTOMIZATION\n";
echo "   Parameter names, constraints, middleware, and custom actions\n\n";

echo "🔹 PERFORMANCE OPTIMIZED\n";
echo "   Efficient route compilation and matching algorithms\n\n";

echo "Ready for enterprise-scale routing! 🚀\n";