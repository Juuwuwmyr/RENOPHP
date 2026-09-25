<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Horizon\Foundation\Application;
use Horizon\Http\Request;
use Horizon\Routing\Router;

// Create application
$app = new Application(__DIR__ . '/..');

// Create router
$router = new Router($app);

// Example 1: Basic route group with prefix
$router->prefix('api/v1')->group(function (Router $router) {
    $router->get('users', function () {
        return 'GET /api/v1/users';
    });
    
    $router->post('users', function () {
        return 'POST /api/v1/users';
    });
    
    $router->get('users/{id}', function ($id) {
        return "GET /api/v1/users/{$id}";
    });
});

// Example 2: Nested route groups
$router->prefix('admin')->middleware('auth')->group(function (Router $router) {
    $router->prefix('api')->group(function (Router $router) {
        $router->get('dashboard', function () {
            return 'GET /admin/api/dashboard - with auth middleware';
        });
        
        $router->middleware('admin')->group(function (Router $router) {
            $router->get('users', function () {
                return 'GET /admin/api/users - with auth + admin middleware';
            });
        });
    });
});

// Example 3: Route group with multiple attributes
$router->group([
    'prefix' => 'api/v2',
    'middleware' => ['cors', 'api'],
    'namespace' => 'App\\Http\\Controllers\\Api\\V2',
], function (Router $router) {
    $router->get('products', 'ProductController@index');
    $router->post('products', 'ProductController@store');
});

// Example 4: Named route groups
$router->name('api.')->prefix('api')->group(function (Router $router) {
    $router->get('status', function () {
        return 'API Status OK';
    })->name('status'); // Will be named 'api.status'
});

// Display all registered routes
echo "Registered Routes:\n";
echo "==================\n\n";

foreach ($router->getRoutes()->getRoutes() as $route) {
    $methods = implode('|', $route->methods());
    $uri = $route->uri();
    $name = $route->getName();
    $middleware = implode(', ', $route->gatherMiddleware());
    
    echo "[$methods] $uri";
    if ($name) {
        echo " (name: $name)";
    }
    if ($middleware) {
        echo " [middleware: $middleware]";
    }
    echo "\n";
}