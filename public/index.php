<?php

/**
 * RENOPHP Framework - Entry Point
 * 
 * This is the front controller for all HTTP requests.
 * All requests are routed through this file.
 */

// Define base path
define('BASE_PATH', dirname(__DIR__));

// Register the Composer autoloader
require BASE_PATH . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->load();

// Create the application instance
$app = new Reno\Foundation\Application(BASE_PATH);

// Register service providers
// $app->register(new App\Providers\AppServiceProvider($app));

// Load web routes
$router = require BASE_PATH . '/routes/web.php';
$app->instance('router', $router);

// Handle the request
$request = Reno\Http\Request::capture();

try {
    $response = $router->dispatch($request);
    $response->send();
} catch (Exception $e) {
    // Handle exceptions
    if ($_ENV['APP_DEBUG'] ?? false) {
        echo "<h1>Error</h1>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p><strong>File:</strong> " . htmlspecialchars($e->getFile()) . "</p>";
        echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    } else {
        http_response_code(500);
        echo "<h1>500 - Internal Server Error</h1>";
        echo "<p>Something went wrong. Please try again later.</p>";
    }
}
