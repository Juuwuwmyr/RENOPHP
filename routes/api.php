<?php

/**
 * API Routes
 * 
 * Define your API routes here.
 * These routes are for RESTful APIs and typically return JSON.
 */

use Reno\Http\Response;
use Reno\Routing\Router;

$router = new Router();

// Prefix all routes with /api
$router->prefix('/api');

// Health check endpoint
$router->get('/health', function() {
    return Response::json([
        'status' => 'healthy',
        'timestamp' => time()
    ]);
});

// Example API resource routes
// $router->get('/users', [App\Controllers\Api\UserController::class, 'index']);
// $router->post('/users', [App\Controllers\Api\UserController::class, 'store']);
// $router->get('/users/{id}', [App\Controllers\Api\UserController::class, 'show']);
// $router->put('/users/{id}', [App\Controllers\Api\UserController::class, 'update']);
// $router->delete('/users/{id}', [App\Controllers\Api\UserController::class, 'destroy']);

return $router;
