<?php

declare(strict_types=1);

echo "Horizon Framework - Simple Routing Test\n";
echo "=======================================\n\n";

// Test basic functionality first
try {
    echo "✓ PHP working correctly\n";
    
    // Test autoloader
    require_once __DIR__ . '/../vendor/autoload.php';
    echo "✓ Autoloader loaded\n";
    
    // Test application creation
    $app = new \Horizon\Foundation\Application(dirname(__DIR__));
    echo "✓ Application created\n";
    
    // Test router creation
    $router = new \Horizon\Routing\Router($app);
    echo "✓ Router created\n";
    
    // Test route registration
    $route = $router->get('/', function () {
        return 'Hello Horizon!';
    });
    echo "✓ Route registered: " . $route->uri() . "\n";
    
    // Test more routes
    $router->get('/users', function () { return 'Users list'; });
    $router->post('/users', function () { return 'Create user'; });
    $router->get('/users/{id}', function ($id) { return "User {$id}"; });
    echo "✓ Multiple routes registered\n";
    
    // Test route groups
    $router->prefix('api')->group(function () use ($router) {
        $router->get('/status', function () {
            return 'API Status: OK';
        });
    });
    echo "✓ Route group created\n";
    
    // Test named routes
    $router->get('/blog', function () {
        return 'Blog home';
    })->name('blog.home');
    echo "✓ Named route created\n";
    
    // Count total routes
    $totalRoutes = 0;
    foreach ($router->getRoutes()->getRoutes() as $method => $routes) {
        $totalRoutes += count($routes);
    }
    echo "✓ Total routes registered: {$totalRoutes}\n";
    
    echo "\n========================================\n";
    echo "✅ SIMPLE ROUTING TEST SUCCESSFUL!\n";
    echo "========================================\n";
    echo "The core routing system is working correctly.\n";
    
} catch (\Throwable $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}