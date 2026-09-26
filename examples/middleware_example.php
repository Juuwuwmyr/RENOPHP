<?php

declare(strict_types=1);

/**
 * Middleware Pipeline Example
 * 
 * Demonstrates the Horizon Framework's middleware system with pipeline processing,
 * global middleware, route middleware, middleware groups, and custom middleware.
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "Horizon Framework - Middleware Pipeline Example\n";
echo "===============================================\n\n";

use Horizon\Middleware\Pipeline;
use Horizon\Middleware\MiddlewareManager;
use Horizon\Middleware\MiddlewareInterface;
use Horizon\Middleware\CorsMiddleware;
use Horizon\Middleware\SecurityMiddleware;
use Horizon\Middleware\ThrottleMiddleware;
use Horizon\Http\Request;
use Horizon\Http\Response;
use Closure;

try {
    echo "1. BASIC MIDDLEWARE PIPELINE\n";
    echo "============================\n\n";

    // Create simple middleware functions
    $loggingMiddleware = function (Request $request, Closure $next) {
        echo "🔍 [Logging] Request: {$request->method()} {$request->path()}\n";
        $response = $next($request);
        echo "📝 [Logging] Response: {$response->getStatusCode()}\n";
        return $response;
    };

    $timingMiddleware = function (Request $request, Closure $next) {
        $start = microtime(true);
        echo "⏱️  [Timing] Request started\n";
        
        $response = $next($request);
        
        $duration = round((microtime(true) - $start) * 1000, 2);
        echo "⏱️  [Timing] Request completed in {$duration}ms\n";
        $response->header('X-Response-Time', $duration . 'ms');
        
        return $response;
    };

    $authMiddleware = function (Request $request, Closure $next) {
        echo "🔐 [Auth] Checking authentication\n";
        
        $token = $request->bearerToken();
        if (!$token) {
            echo "❌ [Auth] No token provided\n";
            return Response::json(['error' => 'Unauthorized'], 401);
        }
        
        echo "✅ [Auth] Token validated\n";
        return $next($request);
    };

    // Create a simple request
    $request = Request::create('/api/users', 'GET', [], [], [], [
        'HTTP_AUTHORIZATION' => 'Bearer valid-token-123'
    ]);

    echo "🚀 Testing basic pipeline:\n";
    
    $pipeline = new Pipeline();
    $response = $pipeline
        ->send($request)
        ->through([$timingMiddleware, $loggingMiddleware, $authMiddleware])
        ->then(function (Request $request) {
            echo "🎯 [Controller] Processing request\n";
            return Response::json(['users' => ['Alice', 'Bob', 'Charlie']]);
        });

    echo "\n📊 Final Response:\n";
    echo "   Status: {$response->getStatusCode()}\n";
    echo "   Headers: " . json_encode($response->getHeaders()) . "\n";
    echo "   Content: " . substr($response->getContent(), 0, 50) . "...\n\n";

    echo "2. CLASS-BASED MIDDLEWARE\n";
    echo "=========================\n\n";

    // Custom middleware class
    class ValidationMiddleware implements MiddlewareInterface
    {
        public function handle(Request $request, Closure $next): Response
        {
            echo "✅ [Validation] Validating request data\n";
            
            if ($request->isPost() && !$request->has('name')) {
                echo "❌ [Validation] Name field is required\n";
                return Response::json(['error' => 'Name field is required'], 422);
            }
            
            echo "✅ [Validation] Request data is valid\n";
            return $next($request);
        }
    }

    class CompressionMiddleware implements MiddlewareInterface
    {
        public function handle(Request $request, Closure $next): Response
        {
            echo "📦 [Compression] Processing request\n";
            
            $response = $next($request);
            
            if ($request->header('Accept-Encoding') && str_contains($request->header('Accept-Encoding'), 'gzip')) {
                echo "🗜️  [Compression] Applying gzip compression\n";
                $response->header('Content-Encoding', 'gzip');
            }
            
            return $response;
        }
    }

    echo "🚀 Testing class-based middleware:\n";

    $postRequest = Request::create('/api/users', 'POST', ['name' => 'John Doe', 'email' => 'john@example.com'], [], [], [
        'HTTP_ACCEPT_ENCODING' => 'gzip, deflate',
        'HTTP_AUTHORIZATION' => 'Bearer valid-token-456'
    ]);

    $classResponse = Pipeline::run(
        $postRequest,
        [new ValidationMiddleware(), new CompressionMiddleware()],
        function (Request $request) {
            echo "🎯 [Controller] Creating user: {$request->input('name')}\n";
            return Response::json(['message' => 'User created', 'user' => $request->post()], 201);
        }
    );

    echo "\n📊 Class-based Response:\n";
    echo "   Status: {$classResponse->getStatusCode()}\n";
    echo "   Content-Encoding: " . ($classResponse->getHeader('Content-Encoding') ?: 'none') . "\n\n";

    echo "3. MIDDLEWARE MANAGER\n";
    echo "=====================\n\n";

    $manager = new MiddlewareManager();

    // Register global middleware
    $manager->global(['timing', 'logging', 'security']);

    // Register route middleware
    $manager->routes([
        'auth' => 'AuthMiddleware',
        'throttle' => ThrottleMiddleware::class,
        'cors' => CorsMiddleware::class,
        'validation' => ValidationMiddleware::class,
    ]);

    // Register middleware groups
    $manager->group('api', ['cors', 'throttle', 'auth']);
    $manager->group('web', ['security', 'csrf']);

    // Register middleware aliases
    $manager->aliases([
        'timing' => 'TimingMiddleware',
        'logging' => 'LoggingMiddleware',
        'security' => SecurityMiddleware::class,
        'csrf' => 'CsrfMiddleware',
    ]);

    echo "✅ Middleware Manager Configuration:\n";
    $stats = $manager->getStats();
    foreach ($stats as $key => $value) {
        echo "   {$key}: {$value}\n";
    }
    echo "\n";

    echo "🔍 Middleware Resolution Examples:\n";
    $resolutionExamples = [
        ['auth'],
        ['api'],
        ['cors', 'validation'],
        ['web', 'auth'],
    ];

    foreach ($resolutionExamples as $middleware) {
        $resolved = $manager->resolve($middleware);
        echo "   " . implode(', ', $middleware) . " -> " . implode(', ', $resolved) . "\n";
    }
    echo "\n";

    echo "4. BUILT-IN MIDDLEWARE EXAMPLES\n";
    echo "================================\n\n";

    echo "🛡️  CORS Middleware:\n";
    $corsRequest = Request::create('/api/data', 'OPTIONS', [], [], [], [
        'HTTP_ORIGIN' => 'https://example.com',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'Content-Type, Authorization'
    ]);

    $corsMiddleware = CorsMiddleware::make([
        'origins' => ['https://example.com', 'https://app.example.com'],
        'methods' => ['GET', 'POST', 'PUT', 'DELETE'],
        'headers' => ['Content-Type', 'Authorization'],
        'credentials' => true,
    ]);

    $corsResponse = $corsMiddleware->handle($corsRequest, function ($request) {
        return new Response('OK');
    });

    echo "   Status: {$corsResponse->getStatusCode()}\n";
    echo "   CORS Headers:\n";
    $corsHeaders = ['Access-Control-Allow-Origin', 'Access-Control-Allow-Methods', 'Access-Control-Allow-Headers'];
    foreach ($corsHeaders as $header) {
        if ($corsResponse->hasHeader($header)) {
            echo "     {$header}: {$corsResponse->getHeader($header)}\n";
        }
    }
    echo "\n";

    echo "🔒 Security Middleware:\n";
    $securityRequest = Request::create('/dashboard', 'GET');
    $securityMiddleware = SecurityMiddleware::strict();

    $securityResponse = $securityMiddleware->handle($securityRequest, function ($request) {
        return new Response('<h1>Dashboard</h1>');
    });

    echo "   Security Headers:\n";
    $securityHeaders = ['X-Content-Type-Options', 'X-Frame-Options', 'X-XSS-Protection', 'Content-Security-Policy'];
    foreach ($securityHeaders as $header) {
        if ($securityResponse->hasHeader($header)) {
            echo "     {$header}: {$securityResponse->getHeader($header)}\n";
        }
    }
    echo "\n";

    echo "🚦 Rate Limiting Middleware:\n";
    $throttleMiddleware = ThrottleMiddleware::perMinute(5);

    // Simulate multiple requests
    for ($i = 1; $i <= 7; $i++) {
        $throttleRequest = Request::create('/api/endpoint', 'GET', [], [], [], [
            'REMOTE_ADDR' => '192.168.1.100'
        ]);

        $throttleResponse = $throttleMiddleware->handle($throttleRequest, function ($request) {
            return Response::json(['message' => 'Success']);
        });

        echo "   Request {$i}: Status {$throttleResponse->getStatusCode()}";
        if ($throttleResponse->hasHeader('X-RateLimit-Remaining')) {
            echo " (Remaining: {$throttleResponse->getHeader('X-RateLimit-Remaining')})";
        }
        if ($throttleResponse->getStatusCode() === 429) {
            echo " - Rate limited!";
        }
        echo "\n";

        if ($i === 7) break; // Stop before hitting more limits
    }
    echo "\n";

    echo "5. MIDDLEWARE PARAMETERS\n";
    echo "========================\n\n";

    // Middleware with parameters
    class RoleMiddleware implements MiddlewareInterface
    {
        protected array $requiredRoles;

        public function __construct(...$roles)
        {
            $this->requiredRoles = $roles;
        }

        public function handle(Request $request, Closure $next): Response
        {
            echo "👤 [Role] Checking roles: " . implode(', ', $this->requiredRoles) . "\n";
            
            $userRole = $request->header('X-User-Role') ?? 'guest';
            
            if (!in_array($userRole, $this->requiredRoles) && !in_array('*', $this->requiredRoles)) {
                echo "❌ [Role] Access denied. User role: {$userRole}\n";
                return Response::json(['error' => 'Forbidden'], 403);
            }
            
            echo "✅ [Role] Access granted for role: {$userRole}\n";
            return $next($request);
        }
    }

    echo "🚀 Testing parametrized middleware:\n";

    $adminRequest = Request::create('/admin/settings', 'GET', [], [], [], [
        'HTTP_X_USER_ROLE' => 'admin'
    ]);

    $roleResponse = Pipeline::run(
        $adminRequest,
        [new RoleMiddleware('admin', 'moderator')],
        function (Request $request) {
            echo "🎯 [Controller] Admin settings accessed\n";
            return Response::json(['settings' => ['maintenance_mode' => false]]);
        }
    );

    echo "\n📊 Role Middleware Response: {$roleResponse->getStatusCode()}\n\n";

    echo "6. MIDDLEWARE ERROR HANDLING\n";
    echo "============================\n\n";

    $errorHandlingMiddleware = function (Request $request, Closure $next) {
        echo "🛡️  [ErrorHandler] Wrapping request\n";
        
        try {
            return $next($request);
        } catch (\Exception $e) {
            echo "❌ [ErrorHandler] Caught exception: {$e->getMessage()}\n";
            return Response::json(['error' => 'Internal Server Error'], 500);
        }
    };

    $faultyMiddleware = function (Request $request, Closure $next) {
        echo "💥 [Faulty] About to throw exception\n";
        throw new \Exception('Something went wrong!');
    };

    echo "🚀 Testing error handling:\n";

    $errorRequest = Request::create('/faulty-endpoint', 'GET');
    
    $errorResponse = Pipeline::run(
        $errorRequest,
        [$errorHandlingMiddleware, $faultyMiddleware],
        function (Request $request) {
            return new Response('This should not be reached');
        }
    );

    echo "\n📊 Error Response: {$errorResponse->getStatusCode()}\n\n";

    echo "7. CONDITIONAL MIDDLEWARE\n";
    echo "=========================\n\n";

    $conditionalMiddleware = function (Request $request, Closure $next) {
        if ($request->path() === '/api/public') {
            echo "🔓 [Conditional] Public endpoint, skipping auth\n";
            return $next($request);
        }
        
        echo "🔐 [Conditional] Private endpoint, checking auth\n";
        $token = $request->bearerToken();
        
        if (!$token) {
            return Response::json(['error' => 'Token required'], 401);
        }
        
        return $next($request);
    };

    echo "🚀 Testing conditional middleware:\n";

    $publicRequest = Request::create('/api/public', 'GET');
    $privateRequest = Request::create('/api/private', 'GET');

    foreach ([$publicRequest, $privateRequest] as $testRequest) {
        echo "\n   Testing: {$testRequest->path()}\n";
        
        $conditionalResponse = Pipeline::run(
            $testRequest,
            [$conditionalMiddleware],
            function (Request $request) {
                return Response::json(['data' => 'Success']);
            }
        );
        
        echo "   Result: {$conditionalResponse->getStatusCode()}\n";
    }
    echo "\n";

    echo "8. MIDDLEWARE PERFORMANCE\n";
    echo "=========================\n\n";

    $performanceTest = function (int $middlewareCount) {
        $middleware = [];
        
        for ($i = 0; $i < $middlewareCount; $i++) {
            $middleware[] = function (Request $request, Closure $next) use ($i) {
                // Simulate some processing time
                usleep(100); // 0.1ms
                return $next($request);
            };
        }

        $testRequest = Request::create('/test', 'GET');
        
        $start = microtime(true);
        
        Pipeline::run(
            $testRequest,
            $middleware,
            function (Request $request) {
                return new Response('OK');
            }
        );
        
        return round((microtime(true) - $start) * 1000, 2);
    };

    echo "📊 Middleware Performance Test:\n";
    
    $testCases = [1, 5, 10, 20];
    foreach ($testCases as $count) {
        $time = $performanceTest($count);
        echo "   {$count} middleware: {$time}ms\n";
    }
    echo "\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "========================================================\n";
echo "✅ MIDDLEWARE PIPELINE EXAMPLE COMPLETE!\n";
echo "========================================================\n\n";

echo "The middleware system provides:\n\n";

echo "🔹 FLEXIBLE PIPELINE PROCESSING\n";
echo "   Request/response transformation through middleware chain\n\n";

echo "🔹 MULTIPLE MIDDLEWARE TYPES\n";
echo "   Closure-based, class-based, and parametrized middleware\n\n";

echo "🔹 COMPREHENSIVE MANAGEMENT\n";
echo "   Global, route, grouped, and aliased middleware\n\n";

echo "🔹 BUILT-IN SECURITY MIDDLEWARE\n";
echo "   CORS, security headers, and rate limiting\n\n";

echo "🔹 ERROR HANDLING & RECOVERY\n";
echo "   Exception handling and graceful degradation\n\n";

echo "🔹 PERFORMANCE OPTIMIZED\n";
echo "   Efficient pipeline execution and minimal overhead\n\n";

echo "Ready for secure, scalable middleware processing! 🚀\n";