<?php

declare(strict_types=1);

/**
 * HTTP Kernel and Application Lifecycle Example
 * 
 * Demonstrates the Horizon Framework's HTTP kernel and application lifecycle
 * with request processing, middleware execution, routing, parameter binding,
 * and comprehensive error handling.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Models/User.php';
require_once __DIR__ . '/Models/Post.php';

echo "Horizon Framework - HTTP Kernel & Application Lifecycle Example\n";
echo "================================================================\n\n";

use Horizon\Foundation\Application;
use Horizon\Http\Kernel;
use Horizon\Http\Request;
use Horizon\Http\Response;
use Horizon\Routing\Router;
use Horizon\Routing\RouteParameterBinder;
use Horizon\Routing\Exceptions\RouteNotFoundException;
use Horizon\Routing\Exceptions\ModelNotFoundException;
use Horizon\Middleware\MiddlewareManager;
use Horizon\Middleware\MiddlewareInterface;
use Examples\Models\User;
use Examples\Models\Post;
use Closure;

try {
    echo "1. APPLICATION BOOTSTRAP\n";
    echo "========================\n\n";

    // Set environment variables for demo
    $_ENV['APP_ENV'] = 'development';
    $_ENV['APP_DEBUG'] = 'true';
    $_ENV['APP_NAME'] = 'Horizon Demo App';
    $_ENV['APP_URL'] = 'http://localhost:8000';

    // Create application instance
    $app = Application::create(__DIR__ . '/..');

    echo "✅ Application created:\n";
    echo "   Name: {$_ENV['APP_NAME']}\n";
    echo "   Environment: {$app->environment()}\n";
    echo "   Debug: " . ($app->isDebug() ? 'enabled' : 'disabled') . "\n";
    echo "   Base Path: {$app->basePath()}\n\n";

    echo "2. MIDDLEWARE CONFIGURATION\n";
    echo "===========================\n\n";

    // Custom authentication middleware
    class AuthMiddleware implements MiddlewareInterface
    {
        public function handle(Request $request, Closure $next): Response
        {
            $token = $request->bearerToken() ?? $request->header('X-API-Key');
            
            if (!$token) {
                return Response::json(['error' => 'Authentication required'], 401);
            }
            
            if ($token !== 'valid-token-123') {
                return Response::json(['error' => 'Invalid token'], 401);
            }
            
            // Add user to request for downstream middleware/controllers
            $request->setAttribute('authenticated_user', ['id' => 1, 'name' => 'Test User']);
            
            return $next($request);
        }
    }

    // Request logging middleware
    class LoggingMiddleware implements MiddlewareInterface
    {
        public function handle(Request $request, Closure $next): Response
        {
            $start = microtime(true);
            echo "   📝 [LOG] {$request->method()} {$request->path()} - Started\n";
            
            $response = $next($request);
            
            $duration = round((microtime(true) - $start) * 1000, 2);
            echo "   📝 [LOG] {$request->method()} {$request->path()} - {$response->getStatusCode()} ({$duration}ms)\n";
            
            return $response;
        }
    }

    // Register middleware
    $app->routeMiddleware([
        'auth' => AuthMiddleware::class,
        'log' => LoggingMiddleware::class,
    ]);

    $app->middlewareGroups([
        'api' => ['log', 'cors', 'throttle'],
        'protected' => ['log', 'auth'],
    ]);

    echo "✅ Middleware registered:\n";
    echo "   Route middleware: auth, log\n";
    echo "   Groups: api, protected\n\n";

    echo "3. ROUTE REGISTRATION\n";
    echo "=====================\n\n";

    // Basic routes
    $app->get('/', function (Request $request) {
        return Response::json([
            'message' => 'Welcome to Horizon Framework!',
            'version' => '1.0.0',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    })->name('home');

    // Health check route
    $app->get('/health', function (Request $request) {
        return Response::json([
            'status' => 'healthy',
            'memory' => memory_get_usage(true),
            'uptime' => time() - $_SERVER['REQUEST_TIME'],
        ]);
    })->name('health');

    // API routes with middleware
    $apiGroup = $app->newGroup(['prefix' => 'api', 'middleware' => ['log']]);
    
    $apiGroup->get('/status', function (Request $request) {
        return Response::json(['api' => 'online', 'version' => '1.0']);
    })->name('api.status');

    // Protected routes requiring authentication
    $protectedGroup = $app->newGroup(['prefix' => 'api', 'middleware' => ['protected']]);
    
    $protectedGroup->get('/profile', function (Request $request) {
        $user = $request->getAttribute('authenticated_user');
        return Response::json(['profile' => $user]);
    })->name('api.profile');

    $protectedGroup->get('/secure-data', function (Request $request) {
        return Response::json([
            'data' => 'This is sensitive information',
            'user' => $request->getAttribute('authenticated_user')['name'],
        ]);
    })->name('api.secure');

    echo "✅ Routes registered:\n";
    echo "   GET / [home]\n";
    echo "   GET /health [health]\n";
    echo "   GET /api/status [api.status] (with logging)\n";
    echo "   GET /api/profile [api.profile] (protected)\n";
    echo "   GET /api/secure-data [api.secure] (protected)\n\n";

    echo "4. MODEL BINDING ROUTES\n";
    echo "=======================\n\n";

    // Configure model binding
    $binder = $app->getKernel()->getBinder();
    $binder->model('user', User::class);
    $binder->model('post', Post::class);

    // User routes with model binding
    $app->get('/users/{user}', function (Request $request, User $user) {
        return Response::json($user->toArray());
    })->name('users.show');

    $app->get('/posts/{post}', function (Request $request, Post $post) {
        return Response::json($post->toArray());
    })->name('posts.show');

    // Nested resource with model binding
    $app->get('/users/{user}/posts/{post}', function (Request $request, User $user, Post $post) {
        return Response::json([
            'user' => $user->toArray(),
            'post' => $post->toArray(),
            'relationship' => 'User owns this post',
        ]);
    })->name('users.posts.show');

    echo "✅ Model binding routes registered:\n";
    echo "   GET /users/{user} [users.show]\n";
    echo "   GET /posts/{post} [posts.show]\n";
    echo "   GET /users/{user}/posts/{post} [users.posts.show]\n\n";

    echo "5. ERROR HANDLING CONFIGURATION\n";
    echo "===============================\n\n";

    // Register custom exception handlers
    $kernel = $app->getKernel();

    $kernel->registerExceptionHandler(\InvalidArgumentException::class, function ($exception, $request) {
        echo "   🔧 Custom handler: InvalidArgumentException\n";
        return Response::json([
            'error' => 'Invalid Argument',
            'message' => $exception->getMessage(),
        ], 400);
    });

    $kernel->registerExceptionHandler(\RuntimeException::class, function ($exception, $request) {
        echo "   🔧 Custom handler: RuntimeException\n";
        return Response::json([
            'error' => 'Runtime Error',
            'message' => 'A runtime error occurred',
            'debug' => $exception->getMessage(),
        ], 500);
    });

    echo "✅ Exception handlers registered:\n";
    echo "   InvalidArgumentException -> 400 Bad Request\n";
    echo "   RuntimeException -> 500 Server Error\n\n";

    echo "6. LIFECYCLE HOOKS\n";
    echo "==================\n\n";

    // Register lifecycle hooks
    $kernel->hook('request.start', function (Request $request) {
        echo "   🚀 Hook: Request started for {$request->method()} {$request->path()}\n";
    });

    $kernel->hook('route.start', function (Request $request, $route) {
        echo "   🎯 Hook: Route execution started\n";
    });

    $kernel->hook('route.executed', function (Request $request, $route, $response) {
        echo "   ✅ Hook: Route executed successfully\n";
    });

    $kernel->hook('exception.thrown', function (Request $request, $exception) {
        echo "   ❌ Hook: Exception thrown - " . get_class($exception) . "\n";
    });

    $kernel->hook('request.handled', function (Request $request, Response $response) {
        echo "   🏁 Hook: Request completed with status {$response->getStatusCode()}\n";
    });

    echo "✅ Lifecycle hooks registered:\n";
    echo "   request.start, route.start, route.executed\n";
    echo "   exception.thrown, request.handled\n\n";

    echo "7. REQUEST PROCESSING SIMULATION\n";
    echo "================================\n\n";

    // Test various requests
    $testRequests = [
        ['GET', '/', [], []],
        ['GET', '/health', [], []],
        ['GET', '/api/status', [], []],
        ['GET', '/api/profile', [], ['Authorization' => 'Bearer valid-token-123']],
        ['GET', '/api/profile', [], []], // No auth - should fail
        ['GET', '/users/1', [], []],
        ['GET', '/users/999', [], []], // Non-existent user - should fail
        ['GET', '/posts/1', [], []],
        ['GET', '/users/1/posts/2', [], []],
        ['GET', '/nonexistent', [], []], // 404 error
        ['POST', '/api/status', [], []], // Method not allowed
    ];

    foreach ($testRequests as $i => [$method, $uri, $data, $headers]) {
        echo "📋 Test " . ($i + 1) . ": {$method} {$uri}\n";
        
        try {
            // Create request with headers
            $serverVars = [];
            foreach ($headers as $name => $value) {
                $serverVars['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
            }
            
            $request = Request::create($uri, $method, $data, [], [], $serverVars);
            
            // Process request through kernel
            $response = $app->handle($request);
            
            echo "   Status: {$response->getStatusCode()}\n";
            
            // Show response content (truncated)
            $content = $response->getContent();
            if (strlen($content) > 100) {
                $content = substr($content, 0, 100) . '...';
            }
            echo "   Response: {$content}\n";
            
            // Show important headers
            if ($response->hasHeader('Content-Type')) {
                echo "   Content-Type: " . $response->getHeader('Content-Type') . "\n";
            }
            
        } catch (Exception $e) {
            echo "   ❌ Unhandled Exception: " . get_class($e) . " - " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }

    echo "8. PERFORMANCE METRICS\n";
    echo "======================\n\n";

    // Get kernel metrics
    $metrics = $kernel->getMetrics();
    
    if (!empty($metrics)) {
        echo "📊 Performance Metrics:\n";
        
        $totalTime = 0;
        $totalMemory = 0;
        $statusCodes = [];
        
        foreach ($metrics as $metric) {
            $totalTime += $metric['duration'];
            $totalMemory += $metric['memory_usage'];
            $statusCodes[$metric['status']] = ($statusCodes[$metric['status']] ?? 0) + 1;
        }
        
        $avgTime = round(($totalTime / count($metrics)) * 1000, 2);
        $avgMemory = round($totalMemory / count($metrics) / 1024 / 1024, 2);
        
        echo "   Total Requests: " . count($metrics) . "\n";
        echo "   Average Response Time: {$avgTime}ms\n";
        echo "   Average Memory Usage: {$avgMemory}MB\n";
        echo "   Status Code Distribution:\n";
        
        foreach ($statusCodes as $status => $count) {
            echo "     {$status}: {$count} requests\n";
        }
        echo "\n";
    }

    echo "9. KERNEL STATISTICS\n";
    echo "====================\n\n";

    $kernelStats = $kernel->getStats();
    echo "📈 Kernel Statistics:\n";
    foreach ($kernelStats as $key => $value) {
        echo "   {$key}: {$value}\n";
    }
    echo "\n";

    echo "10. APPLICATION STATISTICS\n";
    echo "==========================\n\n";

    $appStats = $app->getStats();
    echo "📊 Application Statistics:\n";
    foreach ($appStats as $key => $value) {
        if (is_array($value)) {
            echo "   {$key}:\n";
            foreach ($value as $subKey => $subValue) {
                if (is_array($subValue)) {
                    echo "     {$subKey}: " . json_encode($subValue) . "\n";
                } else {
                    echo "     {$subKey}: {$subValue}\n";
                }
            }
        } else {
            echo "   {$key}: " . (is_bool($value) ? ($value ? 'true' : 'false') : $value) . "\n";
        }
    }
    echo "\n";

    echo "11. ERROR SCENARIOS\n";
    echo "===================\n\n";

    echo "🚨 Testing error handling scenarios:\n\n";

    // Test custom exception
    $app->get('/test-error', function (Request $request) {
        throw new \InvalidArgumentException('This is a test error');
    });

    // Test runtime error
    $app->get('/test-runtime', function (Request $request) {
        throw new \RuntimeException('Runtime error simulation');
    });

    $errorTests = [
        ['GET', '/test-error'],
        ['GET', '/test-runtime'],
    ];

    foreach ($errorTests as [$method, $uri]) {
        echo "   Testing: {$method} {$uri}\n";
        
        try {
            $request = Request::create($uri, $method);
            $response = $app->handle($request);
            
            echo "     Status: {$response->getStatusCode()}\n";
            echo "     Response: " . substr($response->getContent(), 0, 80) . "...\n";
            
        } catch (Exception $e) {
            echo "     Unhandled: " . get_class($e) . "\n";
        }
        
        echo "\n";
    }

    echo "12. CONFIGURATION DEMONSTRATION\n";
    echo "===============================\n\n";

    echo "🔧 Application Configuration:\n";
    echo "   App Name: " . $app->config('app.name') . "\n";
    echo "   Environment: " . $app->config('app.env') . "\n";
    echo "   Debug Mode: " . ($app->config('app.debug') ? 'enabled' : 'disabled') . "\n";
    echo "   URL: " . $app->config('app.url') . "\n\n";

    // Demonstrate configuration updates
    $app->config('app.custom_setting', 'Custom Value');
    echo "   Set custom setting: " . $app->config('app.custom_setting') . "\n\n";

} catch (Exception $e) {
    echo "❌ FATAL ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "========================================================\n";
echo "✅ HTTP KERNEL & APPLICATION LIFECYCLE EXAMPLE COMPLETE!\n";
echo "========================================================\n\n";

echo "The HTTP kernel and application system provides:\n\n";

echo "🔹 COMPREHENSIVE REQUEST PROCESSING\n";
echo "   Full HTTP lifecycle from request to response\n\n";

echo "🔹 MIDDLEWARE ORCHESTRATION\n";
echo "   Global, route, and group middleware execution\n\n";

echo "🔹 AUTOMATIC ROUTE RESOLUTION\n";
echo "   Pattern matching and parameter binding\n\n";

echo "🔹 MODEL BINDING INTEGRATION\n";
echo "   Automatic model resolution and injection\n\n";

echo "🔹 ROBUST ERROR HANDLING\n";
echo "   Custom exception handlers and development-friendly errors\n\n";

echo "🔹 LIFECYCLE HOOKS SYSTEM\n";
echo "   Extensible request/response processing hooks\n\n";

echo "🔹 PERFORMANCE MONITORING\n";
echo "   Built-in metrics and statistics collection\n\n";

echo "🔹 FLEXIBLE CONFIGURATION\n";
echo "   Environment-based configuration management\n\n";

echo "Ready for production-grade HTTP processing! 🚀\n";