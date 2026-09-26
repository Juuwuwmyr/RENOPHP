<?php

/**
 * Web Routes
 * 
 * Define your web application routes here.
 * These routes are loaded by the application and are assigned 
 * the "web" middleware group automatically.
 */

use Reno\Http\Response;
use Reno\Routing\Router;

$router = new Router();

// Home page - single route handles root
$router->get('/', function() {
    return view('welcome', [
        'title' => 'Welcome to RENOPHP',
        'message' => 'Your modern PHP framework is ready!'
    ]);
});

// Documentation page
$router->get('/docs', function() {
    return view('docs', [
        'title' => 'RENOPHP Documentation',
        'framework' => 'RENOPHP',
        'version' => '1.0.0'
    ]);
});

// Example API routes
$router->get('/api/hello', function() {
    return Response::json([
        'message' => 'Hello from RENOPHP!',
        'version' => '1.0.0',
        'timestamp' => date('Y-m-d H:i:s'),
        'status' => 'success'
    ]);
});

// Example routes with controller
// Uncomment when you create the controllers
// $router->get('/users', [App\Controllers\UserController::class, 'index']);
// $router->post('/users', [App\Controllers\UserController::class, 'store']);
// $router->get('/users/{id}', [App\Controllers\UserController::class, 'show']);
// $router->put('/users/{id}', [App\Controllers\UserController::class, 'update']);
// $router->delete('/users/{id}', [App\Controllers\UserController::class, 'destroy']);

return $router;
