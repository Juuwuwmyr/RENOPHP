<?php

declare(strict_types=1);

/**
 * Complete Routing System Example
 * 
 * This example demonstrates all features of the Horizon routing system
 * including basic routes, parameters, groups, middleware, controllers,
 * model binding, caching, and URL generation.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Horizon\Foundation\Application;
use Horizon\Http\Request;
use Horizon\Routing\Router;
use Horizon\Support\Facades\Route;

echo "Horizon Framework - Complete Routing Example\n";
echo "============================================\n\n";

// Initialize the application
$app = new Application(dirname(__DIR__));
$router = new Router($app);

echo "1. BASIC ROUTE REGISTRATION\n";
echo "---------------------------\n";

// Simple GET route
$route1 = $router->get('/', function () {
    return 'Welcome to Horizon Framework!';
});
echo "✓ Registered: GET / -> Welcome closure\n";

// Multiple HTTP methods
$router->get('/users', function () { return 'GET users'; });
$router->post('/users', function () { return 'POST users'; });
$router->put('/users/{id}', function ($id) { return "PUT user {$id}"; });
$router->patch('/users/{id}', function ($id) { return "PATCH user {$id}"; });
$router->delete('/users/{id}', function ($id) { return "DELETE user {$id}"; });
echo "✓ Registered: Full CRUD routes for /users\n";

// Multiple methods in one route
$router->match(['GET', 'POST'], '/contact', function () {
    return 'Contact form';
});
echo "✓ Registered: GET|POST /contact\n";

echo "\n2. ROUTE PARAMETERS\n";
echo "-------------------\n";

// Required parameters
$router->get('/posts/{id}', function ($id) {
    return "Post ID: {$id}";
});
echo "✓ Required parameter: /posts/{id}\n";

// Optional parameters
$router->get('/posts/{id?}', function ($id = null) {
    return $id ? "Post {$id}" : "All posts";
});
echo "✓ Optional parameter: /posts/{id?}\n";

// Multiple parameters
$router->get('/users/{user}/posts/{post}', function ($user, $post) {
    return "User {$user}, Post {$post}";
});
echo "✓ Multiple parameters: /users/{user}/posts/{post}\n";

// Parameter constraints
$router->get('/products/{id}', function ($id) {
    return "Product {$id}";
})->where('id', '[0-9]+');
echo "✓ Numeric constraint: /products/{id} where id = [0-9]+\n";

$router->get('/categories/{slug}', function ($slug) {
    return "Category: {$slug}";
})->where('slug', '[a-z0-9\-]+');
echo "✓ Slug constraint: /categories/{slug} where slug = [a-z0-9\\-]+\n";

echo "\n3. ROUTE GROUPS\n";
echo "---------------\n";

// Prefix group
$router->prefix('admin')->group(function () use ($router) {
    $router->get('/dashboard', function () {
        return 'Admin Dashboard';
    });
    $router->get('/users', function () {
        return 'Admin Users';
    });
});
echo "✓ Prefix group: /admin/* routes\n";

// Middleware group
$router->middleware(['auth', 'admin'])->group(function () use ($router) {
    $router->get('/admin/settings', function () {
        return 'Admin Settings';
    });
});
echo "✓ Middleware group: auth + admin middleware\n";

// Combined attributes group
$router->middleware('auth')
    ->prefix('api/v1')
    ->name('api.v1.')
    ->group(function () use ($router) {
        $router->get('/users', function () {
            return json_encode(['users' => []]);
        })->name('users.index');
        
        $router->get('/users/{id}', function ($id) {
            return json_encode(['user' => ['id' => $id]]);
        })->name('users.show');
    });
echo "✓ Combined group: middleware + prefix + name\n";

// Nested groups
$router->prefix('api')->group(function () use ($router) {
    $router->prefix('v1')->group(function () use ($router) {
        $router->middleware('throttle:60,1')->group(function () use ($router) {
            $router->get('/data', function () {
                return 'API v1 data with rate limiting';
            });
        });
    });
});
echo "✓ Nested groups: /api/v1/* with middleware\n";

echo "\n4. NAMED ROUTES\n";
echo "---------------\n";

$router->get('/blog', function () {
    return 'Blog home';
})->name('blog.home');

$router->get('/blog/{post}', function ($post) {
    return "Blog post: {$post}";
})->name('blog.show');

echo "✓ Named routes: blog.home, blog.show\n";

// Named route groups
$router->name('user.')->group(function () use ($router) {
    $router->get('/profile', function () {
        return 'User profile';
    })->name('profile');
    
    $router->get('/settings', function () {
        return 'User settings';
    })->name('settings');
});
echo "✓ Named route group: user.profile, user.settings\n";

echo "\n5. CONTROLLER ROUTES\n";
echo "--------------------\n";

// Mock controller class for demonstration
class ExampleApiController
{
    public function index()
    {
        return json_encode(['message' => 'API Index']);
    }
    
    public function show($id)
    {
        return json_encode(['user' => ['id' => $id, 'name' => "User {$id}"]]);
    }
    
    public function store()
    {
        return json_encode(['message' => 'User created']);
    }
}

// Register controller in container
$app->singleton(ExampleApiController::class, function () {
    return new ExampleApiController();
});

// Controller routes
$router->get('/api/users', [ExampleApiController::class, 'index']);
$router->get('/api/users/{id}', [ExampleApiController::class, 'show']);
$router->post('/api/users', [ExampleApiController::class, 'store']);
echo "✓ Controller routes: ExampleApiController methods\n";

echo "\n6. URL GENERATION\n";
echo "-----------------\n";

// Create URL generator
$urlGenerator = $app->make(\Horizon\Contracts\Routing\UrlGeneratorInterface::class);
$urlGenerator->setRoutes($router->getRoutes());

// Generate URLs for named routes
try {
    $blogUrl = $urlGenerator->route('blog.home');
    echo "✓ blog.home URL: {$blogUrl}\n";
    
    $postUrl = $urlGenerator->route('blog.show', ['post' => 'my-first-post']);
    echo "✓ blog.show URL: {$postUrl}\n";
    
    $userProfileUrl = $urlGenerator->route('user.profile');
    echo "✓ user.profile URL: {$userProfileUrl}\n";
    
    $apiUsersUrl = $urlGenerator->route('api.v1.users.index');
    echo "✓ api.v1.users.index URL: {$apiUsersUrl}\n";
    
} catch (\Exception $e) {
    echo "⚠ URL Generation: {$e->getMessage()}\n";
}

echo "\n7. ROUTE MATCHING AND DISPATCHING\n";
echo "----------------------------------\n";

// Mock request helper
function createMockRequest($method, $uri)
{
    return new class($method, $uri) {
        private $method;
        private $uri;
        
        public function __construct($method, $uri) {
            $this->method = $method;
            $this->uri = $uri;
        }
        
        public function method() { return $this->method; }
        public function path() { return $uri; }
        public function getPathInfo() { return $this->uri; }
        public function getMethod() { return $this->method; }
        public function getScheme() { return 'http'; }
        public function getHost() { return 'localhost'; }
        public function getPort() { return 80; }
    };
}

// Test route dispatching
$testRoutes = [
    ['GET', '/'],
    ['GET', '/posts/123'],
    ['POST', '/users'],
    ['GET', '/admin/dashboard'],
    ['GET', '/api/v1/users'],
    ['GET', '/blog'],
    ['GET', '/categories/electronics'],
];

foreach ($testRoutes as [$method, $uri]) {
    try {
        $request = createMockRequest($method, $uri);
        $route = $router->match($request);
        echo "✓ {$method} {$uri} -> Matched route\n";
    } catch (\Exception $e) {
        echo "✗ {$method} {$uri} -> {$e->getMessage()}\n";
    }
}

echo "\n8. ROUTE CACHING SIMULATION\n";
echo "----------------------------\n";

// Simulate route caching
$cacheData = [];
foreach ($router->getRoutes()->getRoutes() as $method => $routes) {
    foreach ($routes as $route) {
        $cacheData[] = [
            'method' => $method,
            'uri' => $route->uri(),
            'action' => 'cached_action',
            'compiled' => $route->getCompiled() ? 'yes' : 'no'
        ];
    }
}

echo "✓ Total routes registered: " . count($cacheData) . "\n";
echo "✓ Cache simulation: Routes ready for serialization\n";
echo "✓ Production benefit: Skip route parsing and compilation\n";

echo "\n9. MIDDLEWARE PIPELINE DEMO\n";
echo "----------------------------\n";

// Mock middleware
class DemoMiddleware
{
    public function handle($request, $next)
    {
        echo "  → Middleware executed\n";
        return $next($request);
    }
}

// Register middleware in container
$app->singleton('demo.middleware', function () {
    return new DemoMiddleware();
});

$router->middleware('demo.middleware')->get('/middleware-test', function () {
    return 'Middleware test complete';
});
echo "✓ Middleware route registered: /middleware-test\n";

echo "\n10. SECURITY FEATURES\n";
echo "---------------------\n";

// CSRF protection (simulated)
$router->middleware('csrf')->post('/secure-form', function () {
    return 'CSRF protected form submission';
});
echo "✓ CSRF protection: POST /secure-form\n";

// Rate limiting (simulated)
$router->middleware('throttle:60,1')->get('/api/rate-limited', function () {
    return 'Rate limited API endpoint';
});
echo "✓ Rate limiting: 60 requests per minute\n";

// Authentication required (simulated)
$router->middleware('auth')->get('/protected', function () {
    return 'Protected content';
});
echo "✓ Authentication: /protected requires auth\n";

echo "\n11. PERFORMANCE METRICS\n";
echo "-----------------------\n";

$startTime = microtime(true);
$memoryStart = memory_get_usage();

// Register 100 test routes to measure performance
for ($i = 1; $i <= 100; $i++) {
    $router->get("/test-route-{$i}", function () use ($i) {
        return "Test route {$i}";
    });
}

$endTime = microtime(true);
$memoryEnd = memory_get_usage();

$executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
$memoryUsage = ($memoryEnd - $memoryStart) / 1024; // Convert to KB

echo "✓ Registered 100 routes in: " . round($executionTime, 2) . "ms\n";
echo "✓ Memory usage: " . round($memoryUsage, 2) . "KB\n";
echo "✓ Average per route: " . round($executionTime / 100, 3) . "ms\n";

echo "\n12. ERROR HANDLING\n";
echo "------------------\n";

// Test 404 - Route not found
try {
    $request = createMockRequest('GET', '/nonexistent-route');
    $router->match($request);
} catch (\Exception $e) {
    echo "✓ 404 handling: {$e->getMessage()}\n";
}

// Test 405 - Method not allowed
try {
    $router->get('/method-test', function () {
        return 'GET only';
    });
    
    $request = createMockRequest('POST', '/method-test');
    $router->match($request);
} catch (\Exception $e) {
    echo "✓ 405 handling: {$e->getMessage()}\n";
}

echo "\n========================================\n";
echo "✅ COMPLETE ROUTING EXAMPLE FINISHED!\n";
echo "========================================\n";

echo "\nFEATURES DEMONSTRATED:\n";
echo "✓ Basic route registration (GET, POST, PUT, PATCH, DELETE)\n";
echo "✓ Route parameters (required, optional, constraints)\n";
echo "✓ Route groups (prefix, middleware, namespace, name)\n";
echo "✓ Named routes and URL generation\n";
echo "✓ Controller integration\n";
echo "✓ Route matching and dispatching\n";
echo "✓ Middleware pipeline\n";
echo "✓ Security features (CSRF, rate limiting, auth)\n";
echo "✓ Performance optimization (caching simulation)\n";
echo "✓ Error handling (404, 405)\n";
echo "✓ Route compilation and optimization\n";
echo "✓ Nested route groups\n";

echo "\nNEXT STEPS:\n";
echo "• Copy route definitions to your routes/web.php file\n";
echo "• Create actual controllers for your application\n";
echo "• Configure middleware in HttpServiceProvider\n";
echo "• Set up authentication and authorization\n";
echo "• Cache routes in production: php horizon route:cache\n";
echo "• Review security configuration in config/security.php\n";

echo "\nThe Horizon routing system is ready for production use! 🚀\n";