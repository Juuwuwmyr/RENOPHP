<?php

declare(strict_types=1);

/**
 * Routing System Example
 * 
 * Demonstrates the Horizon Framework's routing system with pattern matching,
 * parameter extraction, route groups, resource routes, middleware, and
 * comprehensive routing features.
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "Horizon Framework - Routing System Example\n";
echo "==========================================\n\n";

use Horizon\Routing\Router;
use Horizon\Routing\Route;
use Horizon\Http\Request;
use Horizon\Http\Response;
use Horizon\Routing\Exceptions\RouteNotFoundException;
use Horizon\Routing\Exceptions\MethodNotAllowedException;

try {
    echo "1. BASIC ROUTE REGISTRATION\n";
    echo "===========================\n\n";

    $router = new Router();

    // Basic routes
    $router->get('/', function (Request $request) {
        return new Response('Welcome to Horizon!');
    });

    $router->post('/users', function (Request $request) {
        return Response::json(['message' => 'User created', 'data' => $request->post()]);
    });

    $router->put('/users/{id}', function (Request $request, $id) {
        return Response::json(['message' => "User {$id} updated", 'data' => $request->post()]);
    });

    $router->delete('/users/{id}', function (Request $request, $id) {
        return Response::json(['message' => "User {$id} deleted"]);
    });

    echo "✅ Registered basic CRUD routes\n";
    echo "   GET /\n";
    echo "   POST /users\n";
    echo "   PUT /users/{id}\n";
    echo "   DELETE /users/{id}\n\n";

    echo "2. NAMED ROUTES\n";
    echo "===============\n\n";

    $router->get('/dashboard', function () {
        return new Response('Dashboard');
    })->name('dashboard');

    $router->get('/profile/{user}', function (Request $request, $user) {
        return new Response("Profile for user: {$user}");
    })->name('user.profile');

    echo "✅ Registered named routes:\n";
    echo "   dashboard -> /dashboard\n";
    echo "   user.profile -> /profile/{user}\n\n";

    // Generate URLs from named routes
    echo "🔗 URL Generation:\n";
    echo "   dashboard: " . $router->route('dashboard') . "\n";
    echo "   user.profile: " . $router->route('user.profile', ['user' => 'john']) . "\n\n";

    echo "3. ROUTE CONSTRAINTS\n";
    echo "====================\n\n";

    // Routes with parameter constraints
    $router->get('/posts/{id}', function (Request $request, $id) {
        return Response::json(['post_id' => $id, 'type' => 'numeric']);
    })->whereNumber('id')->name('posts.show');

    $router->get('/categories/{slug}', function (Request $request, $slug) {
        return Response::json(['category' => $slug, 'type' => 'slug']);
    })->whereSlug('slug')->name('categories.show');

    $router->get('/users/{uuid}', function (Request $request, $uuid) {
        return Response::json(['user_uuid' => $uuid, 'type' => 'uuid']);
    })->whereUuid('uuid')->name('users.show');

    $router->get('/tags/{tag}', function (Request $request, $tag) {
        return Response::json(['tag' => $tag, 'type' => 'alpha']);
    })->whereAlpha('tag')->name('tags.show');

    echo "✅ Registered constrained routes:\n";
    echo "   /posts/{id} - numeric only\n";
    echo "   /categories/{slug} - slug format\n";
    echo "   /users/{uuid} - UUID format\n";
    echo "   /tags/{tag} - alphabetic only\n\n";

    echo "4. OPTIONAL PARAMETERS\n";
    echo "======================\n\n";

    $router->get('/search/{query?}', function (Request $request, $query = null) {
        if ($query) {
            return Response::json(['searching_for' => $query]);
        }
        return Response::json(['message' => 'Empty search']);
    })->name('search');

    $router->get('/api/v{version?}/status', function (Request $request, $version = '1') {
        return Response::json(['api_version' => $version, 'status' => 'online']);
    })->whereNumber('version')->name('api.status');

    echo "✅ Registered routes with optional parameters:\n";
    echo "   /search/{query?}\n";
    echo "   /api/v{version?}/status\n\n";

    echo "5. ROUTE GROUPS\n";
    echo "===============\n\n";

    // API group with prefix and middleware
    $router->group(['prefix' => 'api/v1', 'name' => 'api.'], function (Router $router) {
        $router->get('/users', function () {
            return Response::json(['users' => ['John', 'Jane']]);
        })->name('users.index');

        $router->get('/users/{id}', function (Request $request, $id) {
            return Response::json(['user' => "User {$id}"]);
        })->whereNumber('id')->name('users.show');
    });

    // Admin group with middleware and namespace
    $router->group([
        'prefix' => 'admin',
        'middleware' => ['auth', 'admin'],
        'name' => 'admin.'
    ], function (Router $router) {
        $router->get('/dashboard', function () {
            return new Response('Admin Dashboard');
        })->name('dashboard');

        $router->get('/users', function () {
            return new Response('Admin Users');
        })->name('users');
    });

    echo "✅ Registered route groups:\n";
    echo "   API group: /api/v1/*\n";
    echo "   Admin group: /admin/* (with middleware)\n\n";

    echo "6. RESOURCE ROUTES\n";
    echo "==================\n\n";

    // Full resource
    $router->resource('articles', 'ArticleController');

    // Partial resource
    $router->resource('photos', 'PhotoController', [
        'only' => ['index', 'show', 'store']
    ]);

    echo "✅ Registered resource routes:\n";
    echo "   Full articles resource (7 routes)\n";
    echo "   Partial photos resource (3 routes)\n\n";

    echo "7. MULTIPLE HTTP METHODS\n";
    echo "========================\n\n";

    $router->match(['GET', 'POST'], '/contact', function (Request $request) {
        if ($request->isGet()) {
            return new Response('Contact Form');
        }
        return Response::json(['message' => 'Contact submitted']);
    })->name('contact');

    $router->any('/webhooks/{service}', function (Request $request, $service) {
        return Response::json([
            'service' => $service,
            'method' => $request->method(),
            'received' => true
        ]);
    })->name('webhooks');

    echo "✅ Registered multi-method routes:\n";
    echo "   /contact - GET|POST\n";
    echo "   /webhooks/{service} - ANY\n\n";

    echo "8. ROUTE MATCHING EXAMPLES\n";
    echo "==========================\n\n";

    // Test various requests
    $testRequests = [
        ['GET', '/'],
        ['GET', '/dashboard'],
        ['GET', '/posts/123'],
        ['GET', '/categories/web-development'],
        ['GET', '/users/550e8400-e29b-41d4-a716-446655440000'],
        ['GET', '/search'],
        ['GET', '/search/php-framework'],
        ['GET', '/api/v1/users'],
        ['GET', '/api/v2/status'],
        ['POST', '/contact'],
        ['PUT', '/users/456'],
    ];

    foreach ($testRequests as [$method, $uri]) {
        echo "🔍 Testing: {$method} {$uri}\n";
        
        try {
            $request = Request::create($uri, $method);
            $route = $router->matchRequest($request);
            
            echo "   ✅ Matched: " . implode('|', $route->getMethods()) . " " . $route->getUri();
            if ($route->getName()) {
                echo " [{$route->getName()}]";
            }
            echo "\n";
            
            if (!empty($route->getParameters())) {
                echo "   📝 Parameters: " . json_encode($route->getParameters()) . "\n";
            }
            
            if (!empty($route->getMiddleware())) {
                echo "   🛡️  Middleware: " . implode(', ', $route->getMiddleware()) . "\n";
            }
            
        } catch (RouteNotFoundException $e) {
            echo "   ❌ Not Found: {$e->getMessage()}\n";
        } catch (MethodNotAllowedException $e) {
            echo "   🚫 Method Not Allowed: {$e->getAllowHeader()}\n";
        }
        
        echo "\n";
    }

    echo "9. ERROR HANDLING\n";
    echo "=================\n\n";

    echo "🚨 Testing error scenarios:\n\n";

    // Test route not found
    try {
        $request = Request::create('/nonexistent', 'GET');
        $router->match($request);
    } catch (RouteNotFoundException $e) {
        echo "❌ Route Not Found:\n";
        echo "   Message: {$e->getMessage()}\n";
        echo "   Status: {$e->getStatusCode()}\n\n";
    }

    // Test method not allowed
    try {
        $request = Request::create('/users/123', 'PATCH'); // Only PUT is registered
        $router->match($request);
    } catch (MethodNotAllowedException $e) {
        echo "🚫 Method Not Allowed:\n";
        echo "   Message: {$e->getMessage()}\n";
        echo "   Allowed: {$e->getAllowHeader()}\n";
        echo "   Status: {$e->getStatusCode()}\n\n";
    }

    echo "10. ROUTER STATISTICS\n";
    echo "=====================\n\n";

    $stats = $router->getStats();
    echo "📊 Router Statistics:\n";
    echo "   Total Routes: {$stats['total_routes']}\n";
    echo "   Compiled Routes: {$stats['compiled_routes']}\n";
    echo "   Named Routes: {$stats['named_routes']}\n";
    echo "   Patterns: {$stats['patterns']}\n";
    echo "   Cache Enabled: " . ($stats['cache_enabled'] ? 'Yes' : 'No') . "\n\n";

    echo "📈 Routes by Method:\n";
    foreach ($stats['routes_by_method'] as $method => $count) {
        echo "   {$method}: {$count}\n";
    }
    echo "\n";

    echo "11. ROUTE LIST\n";
    echo "==============\n\n";

    $routeList = $router->getRouteList();
    echo "📋 All Registered Routes:\n\n";
    
    printf("%-10s %-30s %-20s %-30s\n", 'METHOD', 'URI', 'NAME', 'ACTION');
    echo str_repeat('-', 90) . "\n";
    
    foreach ($routeList as $route) {
        printf(
            "%-10s %-30s %-20s %-30s\n",
            $route['methods'],
            $route['uri'],
            $route['name'] ?: '-',
            substr($route['action'], 0, 28) . (strlen($route['action']) > 28 ? '...' : '')
        );
    }
    echo "\n";

    echo "12. URL GENERATION\n";
    echo "==================\n\n";

    echo "🔗 Named Route URL Generation:\n";
    
    $namedRoutes = [
        'dashboard' => [],
        'user.profile' => ['user' => 'alice'],
        'posts.show' => ['id' => '42'],
        'categories.show' => ['slug' => 'web-development'],
        'api.users.show' => ['id' => '123'],
        'search' => ['query' => 'horizon-framework'],
    ];

    foreach ($namedRoutes as $name => $params) {
        try {
            $url = $router->route($name, $params, false); // relative URLs
            echo "   {$name}: {$url}\n";
        } catch (InvalidArgumentException $e) {
            echo "   {$name}: ❌ {$e->getMessage()}\n";
        }
    }
    echo "\n";

    echo "13. ROUTE EXECUTION SIMULATION\n";
    echo "==============================\n\n";

    echo "🎯 Simulating route execution:\n\n";

    $executionTests = [
        ['GET', '/', []],
        ['GET', '/posts/123', []],
        ['POST', '/users', ['name' => 'John Doe', 'email' => 'john@example.com']],
        ['GET', '/search/php', []],
    ];

    foreach ($executionTests as [$method, $uri, $postData]) {
        echo "▶️  Executing: {$method} {$uri}\n";
        
        try {
            $request = Request::create($uri, $method, [], [], [], [], 
                $postData ? json_encode($postData) : null);
            
            if (!empty($postData)) {
                // Simulate POST data
                foreach ($postData as $key => $value) {
                    $_POST[$key] = $value;
                }
            }
            
            $route = $router->matchRequest($request);
            $response = $route->run($request);
            
            echo "   Status: " . $response->getStatusCode() . "\n";
            echo "   Content: " . substr($response->getContent(), 0, 100);
            if (strlen($response->getContent()) > 100) {
                echo "...";
            }
            echo "\n";
            
        } catch (Exception $e) {
            echo "   ❌ Error: {$e->getMessage()}\n";
        }
        
        echo "\n";
    }

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "========================================================\n";
echo "✅ ROUTING SYSTEM EXAMPLE COMPLETE!\n";
echo "========================================================\n\n";

echo "The routing system provides:\n\n";

echo "🔹 FLEXIBLE ROUTE REGISTRATION\n";
echo "   HTTP method helpers, multiple methods, any method\n\n";

echo "🔹 POWERFUL PARAMETER MATCHING\n";
echo "   Required/optional parameters with constraints\n\n";

echo "🔹 ROUTE ORGANIZATION\n";
echo "   Groups, prefixes, middleware, namespaces\n\n";

echo "🔹 RESOURCE ROUTES\n";
echo "   Full and partial REST resource generation\n\n";

echo "🔹 NAMED ROUTES & URL GENERATION\n";
echo "   Clean URL building with parameters\n\n";

echo "🔹 COMPREHENSIVE ERROR HANDLING\n";
echo "   404 Not Found and 405 Method Not Allowed\n\n";

echo "🔹 PERFORMANCE OPTIMIZATIONS\n";
echo "   Route compilation and caching support\n\n";

echo "Ready for high-performance web routing! 🚀\n";