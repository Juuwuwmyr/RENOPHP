# Horizon Framework - HTTP Layer Documentation

The Horizon Framework provides a comprehensive, high-performance HTTP layer designed around the philosophy of "Less Magic. More Understanding." This documentation covers all aspects of HTTP request/response handling, routing, middleware, and performance optimizations.

## Table of Contents

- [Core Components](#core-components)
- [HTTP Request & Response](#http-request--response)
- [Routing System](#routing-system)
- [Middleware Pipeline](#middleware-pipeline)
- [Parameter Binding](#parameter-binding)
- [Route Groups & Resources](#route-groups--resources)
- [HTTP Kernel & Application](#http-kernel--application)
- [Performance Optimizations](#performance-optimizations)
- [Security Features](#security-features)
- [Best Practices](#best-practices)
- [API Reference](#api-reference)

## Core Components

The HTTP layer consists of several interconnected components:

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Application   │───▶│   HTTP Kernel   │───▶│   Middleware    │
└─────────────────┘    └─────────────────┘    └─────────────────┘
                                │
                                ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│    Response     │◀───│     Router      │───▶│ Parameter Binder│
└─────────────────┘    └─────────────────┘    └─────────────────┘
                                │
                                ▼
                       ┌─────────────────┐
                       │ Route Collection│
                       └─────────────────┘
```

### Key Features

- **High Performance**: O(1) static route lookups, compiled route caching
- **Security First**: Built-in CSRF, XSS, and injection protection
- **Developer Friendly**: Clear error messages and debugging tools
- **Modular Design**: Use only what you need
- **Production Ready**: Comprehensive monitoring and optimization

## HTTP Request & Response

### Request Handling

The `Request` class provides a clean, secure interface to HTTP request data:

```php
use Horizon\Http\Request;

// Create from globals
$request = Request::createFromGlobals();

// Create manually
$request = Request::create('/api/users', 'POST', [
    'name' => 'John Doe',
    'email' => 'john@example.com'
]);

// Access request data
$method = $request->method();           // POST
$path = $request->path();             // api/users
$input = $request->input('name');     // John Doe
$all = $request->all();               // All input data

// File uploads
if ($request->hasFile('avatar')) {
    $file = $request->file('avatar');
    if ($file->isValid()) {
        $file->store('/uploads/avatars');
    }
}

// Headers and authentication
$userAgent = $request->userAgent();
$bearerToken = $request->bearerToken();
$accepts = $request->accepts(['application/json']);
```

### Response Building

The `Response` class supports fluent response building:

```php
use Horizon\Http\Response;

// Basic responses
return new Response('Hello World');
return new Response('Not Found', 404);

// JSON responses
return Response::json(['users' => $users]);
return Response::json(['error' => 'Not found'], 404);

// Fluent interface
return Response::make('Success')
    ->header('X-Custom-Header', 'value')
    ->cookie('session', 'abc123')
    ->status(201);

// File responses
return Response::file('/path/to/file.pdf');
return Response::download('/path/to/file.zip', 'download.zip');

// Redirects
return Response::redirect('/dashboard');
return Response::redirectRoute('users.index');
```

### Content Negotiation

Automatic content negotiation based on Accept headers:

```php
if ($request->expectsJson()) {
    return Response::json($data);
}

if ($request->accepts(['text/html'])) {
    return Response::view('users.index', $data);
}

// Multiple formats
$format = $request->format(); // json, html, xml, etc.
```

## Routing System

### Basic Routing

Register routes for different HTTP methods:

```php
use Horizon\Routing\Router;

$router = new Router();

// HTTP method routes
$router->get('/users', 'UserController@index');
$router->post('/users', 'UserController@store');
$router->put('/users/{id}', 'UserController@update');
$router->patch('/users/{id}', 'UserController@patch');
$router->delete('/users/{id}', 'UserController@destroy');

// Multiple methods
$router->match(['GET', 'POST'], '/contact', 'ContactController@handle');
$router->any('/webhooks/{service}', 'WebhookController@handle');
```

### Route Parameters

#### Basic Parameters

```php
$router->get('/users/{id}', function (Request $request, $id) {
    return Response::json(['user_id' => $id]);
});

$router->get('/posts/{id}/comments/{comment}', function ($id, $comment) {
    return Response::json(['post' => $id, 'comment' => $comment]);
});
```

#### Optional Parameters

```php
$router->get('/api/v{version?}', function ($version = '1') {
    return Response::json(['version' => $version]);
});
```

#### Parameter Constraints

```php
// Built-in constraints
$router->get('/users/{id}', 'UserController@show')
    ->whereNumber('id');

$router->get('/posts/{slug}', 'PostController@show')
    ->whereSlug('slug');

$router->get('/files/{path}', 'FileController@show')
    ->where('path', '.*');

// Multiple constraints
$router->get('/archive/{year}/{month}', 'ArchiveController@show')
    ->whereNumber('year')
    ->whereNumber('month');
```

### Named Routes

```php
// Register named routes
$router->get('/dashboard', 'DashboardController@index')
    ->name('dashboard');

$router->get('/users/{id}', 'UserController@show')
    ->name('users.show');

// Generate URLs
$url = $router->route('dashboard');                    // /dashboard
$url = $router->route('users.show', ['id' => 123]);   // /users/123
```

### Route Model Binding

Automatically inject model instances based on route parameters:

```php
use Horizon\Routing\RouteParameterBinder;

// Configure model binding
$binder = new RouteParameterBinder();
$binder->model('user', User::class);
$binder->model('post', Post::class);

// Routes automatically inject models
$router->get('/users/{user}', function (Request $request, User $user) {
    return Response::json($user->toArray());
});

$router->get('/users/{user}/posts/{post}', function (User $user, Post $post) {
    return Response::json([
        'user' => $user->toArray(),
        'post' => $post->toArray()
    ]);
});
```

#### Custom Binding Logic

```php
$binder->bind('post_slug', function ($value, $route, $request) {
    $post = Post::where('slug', $value)->first();
    
    if (!$post) {
        throw new ModelNotFoundException("Post not found: {$value}");
    }
    
    return $post;
});
```

## Middleware Pipeline

### Creating Middleware

Implement the `MiddlewareInterface`:

```php
use Horizon\Middleware\MiddlewareInterface;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->bearerToken()) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }
        
        return $next($request);
    }
}
```

### Registering Middleware

```php
use Horizon\Middleware\MiddlewareManager;

$middleware = new MiddlewareManager();

// Global middleware (runs on every request)
$middleware->global(['cors', 'security']);

// Route middleware
$middleware->routes([
    'auth' => AuthMiddleware::class,
    'throttle' => ThrottleMiddleware::class,
]);

// Middleware groups
$middleware->group('api', ['cors', 'throttle', 'auth']);
$middleware->group('web', ['security', 'csrf']);
```

### Applying Middleware to Routes

```php
// Single middleware
$router->get('/profile', 'ProfileController@show')
    ->middleware('auth');

// Multiple middleware
$router->post('/api/data', 'ApiController@store')
    ->middleware(['auth', 'throttle:60,1']);

// Middleware groups
$router->group(['middleware' => 'api'], function (Router $router) {
    $router->get('/users', 'UserController@index');
    $router->post('/users', 'UserController@store');
});
```

### Built-in Middleware

#### CORS Middleware

```php
use Horizon\Middleware\CorsMiddleware;

// Permissive (development)
$cors = CorsMiddleware::permissive();

// Restrictive (production)
$cors = CorsMiddleware::restrictive(['https://myapp.com']);

// Custom configuration
$cors = CorsMiddleware::make([
    'origins' => ['https://app.example.com'],
    'methods' => ['GET', 'POST', 'PUT', 'DELETE'],
    'headers' => ['Content-Type', 'Authorization'],
    'credentials' => true,
]);
```

#### Security Middleware

```php
use Horizon\Middleware\SecurityMiddleware;

// Basic security headers
$security = SecurityMiddleware::basic();

// Strict security (recommended for production)
$security = SecurityMiddleware::strict();

// Custom Content Security Policy
$security = SecurityMiddleware::withCsp(
    "default-src 'self'; script-src 'self' 'unsafe-inline'"
);
```

#### Rate Limiting Middleware

```php
use Horizon\Middleware\ThrottleMiddleware;

// 60 requests per minute
$throttle = ThrottleMiddleware::perMinute(60);

// 1000 requests per hour
$throttle = ThrottleMiddleware::perHour(1000);

// Custom key generator (per user)
$throttle = ThrottleMiddleware::forUser(100, 60);

// API rate limiting
$throttle = ThrottleMiddleware::forApi(1000, 60);
```

## Route Groups & Resources

### Route Groups

Organize routes with shared attributes:

```php
// Basic grouping
$router->group(['prefix' => 'api'], function (Router $router) {
    $router->get('/users', 'UserController@index');
    $router->get('/posts', 'PostController@index');
});

// Complex grouping
$router->group([
    'prefix' => 'admin',
    'middleware' => ['auth', 'admin'],
    'name' => 'admin.',
    'namespace' => 'Admin'
], function (Router $router) {
    $router->get('/dashboard', 'DashboardController@index')->name('dashboard');
    $router->resource('users', 'UserController');
});
```

### Enhanced Route Groups

Use the enhanced RouteGroup class for advanced features:

```php
// Fluent interface
$apiGroup = $router->newGroup(['prefix' => 'api'])
    ->middleware(['cors', 'throttle'])
    ->name('api.');

// Versioned APIs
$v1Group = $apiGroup->version('1', function (RouteGroup $group) {
    $group->get('/users', 'V1\UserController@index');
});

$v2Group = $apiGroup->version('2', function (RouteGroup $group) {
    $group->get('/users', 'V2\UserController@index');
});

// Conditional routes
$router->newGroup()
    ->env(['development', 'testing'], function (RouteGroup $group) {
        $group->get('/debug', 'DebugController@index');
    })
    ->when($featureFlag, function (RouteGroup $group) {
        $group->get('/beta-feature', 'BetaController@index');
    });
```

### Resource Routes

Generate RESTful routes automatically:

```php
// Full resource (7 routes)
$router->resource('posts', 'PostController');
/*
GET    /posts           posts.index
GET    /posts/create    posts.create
POST   /posts           posts.store
GET    /posts/{id}      posts.show
GET    /posts/{id}/edit posts.edit
PUT    /posts/{id}      posts.update
DELETE /posts/{id}      posts.destroy
*/

// API resource (no create/edit forms)
$router->apiResource('posts', 'PostController');
/*
GET    /posts      posts.index
POST   /posts      posts.store
GET    /posts/{id} posts.show
PUT    /posts/{id} posts.update
DELETE /posts/{id} posts.destroy
*/
```

### Resource Customization

```php
use Horizon\Routing\ResourceRouteRegistrar;

$router->resource('posts', 'PostController')
    ->only(['index', 'show', 'store'])
    ->middleware(['auth'])
    ->parameter('post')
    ->where(['post' => '[0-9]+'])
    ->names([
        'index' => 'posts.list',
        'show' => 'posts.detail'
    ]);
```

### Nested Resources

```php
$router->group(['prefix' => 'users/{user}'], function (Router $router) {
    $router->resource('posts', 'UserPostController')
        ->parameter('post');
    
    $router->resource('comments', 'UserCommentController')
        ->only(['index', 'store', 'destroy']);
});
```

## HTTP Kernel & Application

### Application Bootstrap

```php
use Horizon\Foundation\Application;

// Create application
$app = Application::create(__DIR__);

// Configure middleware
$app->middleware(['cors', 'security']);
$app->routeMiddleware([
    'auth' => AuthMiddleware::class,
    'throttle' => ThrottleMiddleware::class,
]);

// Register routes
$app->get('/', function () {
    return Response::json(['message' => 'Welcome to Horizon!']);
});

// Run application
$app->run();
```

### HTTP Kernel

The kernel orchestrates the entire request/response cycle:

```php
use Horizon\Http\Kernel;

$kernel = new Kernel($router, $middleware, $binder);

// Register exception handlers
$kernel->registerExceptionHandler(\InvalidArgumentException::class, 
    function ($exception, $request) {
        return Response::json(['error' => $exception->getMessage()], 400);
    }
);

// Register lifecycle hooks
$kernel->hook('request.start', function (Request $request) {
    logger()->info('Request started', ['url' => $request->fullUrl()]);
});

$kernel->hook('exception.thrown', function (Request $request, $exception) {
    logger()->error('Exception thrown', [
        'exception' => get_class($exception),
        'message' => $exception->getMessage(),
    ]);
});

// Process request
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$kernel->sendResponse($response);
```

## Performance Optimizations

### Route Caching

Enable route compilation caching for production:

```php
use Horizon\Routing\RouteCache;
use Horizon\Routing\FastRouter;

// Create route cache
$cache = new RouteCache('/path/to/cache/routes.cache', null, [
    'enabled' => true,
    'ttl' => 3600,
    'compression' => true,
]);

// Use FastRouter for high performance
$fastRouter = new FastRouter($routes, $cache, [
    'cache_enabled' => true,
    'performance_monitoring' => true,
    'precompile_routes' => true,
]);

// Warm up cache (e.g., during deployment)
$fastRouter->warmUpCache();
```

### Performance Monitoring

```php
// Get performance metrics
$metrics = $fastRouter->getMetrics();

echo "Cache hit rate: {$metrics['cache_hit_rate']}%\n";
echo "Average lookup time: {$metrics['avg_lookup_time_ms']}ms\n";
echo "Static routes: {$metrics['static_routes_count']}\n";
echo "Dynamic routes: {$metrics['dynamic_routes_count']}\n";

// Get detailed diagnostics
$diagnostics = $fastRouter->getDiagnostics();
print_r($diagnostics);
```

### Optimization Tips

1. **Use Static Routes**: Static routes have O(1) lookup time
2. **Enable Route Caching**: Pre-compile routes for production
3. **Optimize Route Order**: Place simpler routes first
4. **Use Appropriate Constraints**: Reduce regex complexity
5. **Monitor Performance**: Track metrics and optimize bottlenecks

## Security Features

### Built-in Security

The framework includes security features by default:

#### Input Validation and Sanitization

```php
// Request data is automatically escaped
$userInput = $request->input('comment'); // XSS-safe

// File upload validation
if ($request->hasFile('upload')) {
    $file = $request->file('upload');
    
    // Built-in security checks
    if ($file->isValid() && $file->isSecure()) {
        $file->store('/uploads');
    }
}
```

#### Security Headers

```php
// Automatic security headers via SecurityMiddleware
$response->header('X-Content-Type-Options', 'nosniff');
$response->header('X-Frame-Options', 'DENY');
$response->header('X-XSS-Protection', '1; mode=block');
$response->header('Strict-Transport-Security', 'max-age=31536000');
```

#### CSRF Protection

```php
// CSRF middleware for web routes
$router->group(['middleware' => 'csrf'], function (Router $router) {
    $router->post('/contact', 'ContactController@store');
    $router->put('/profile', 'ProfileController@update');
});
```

### Security Best Practices

1. **Always Validate Input**: Use constraints and validation
2. **Enable HTTPS**: Redirect HTTP to HTTPS in production
3. **Use Security Middleware**: Apply appropriate security headers
4. **Implement Rate Limiting**: Prevent abuse and DDoS attacks
5. **Sanitize File Uploads**: Validate file types and content
6. **Use Authentication**: Protect sensitive routes
7. **Log Security Events**: Monitor for suspicious activity

## Best Practices

### Route Organization

```php
// Group related routes
$router->group(['prefix' => 'api/v1', 'name' => 'api.v1.'], function (Router $router) {
    
    // Public routes
    $router->get('/status', 'StatusController@index')->name('status');
    
    // Authenticated routes
    $router->group(['middleware' => 'auth'], function (Router $router) {
        $router->resource('users', 'UserController');
        $router->resource('posts', 'PostController');
    });
    
    // Admin routes
    $router->group(['middleware' => ['auth', 'admin'], 'prefix' => 'admin'], 
        function (Router $router) {
            $router->get('/stats', 'AdminController@stats')->name('admin.stats');
        }
    );
});
```

### Error Handling

```php
// Register comprehensive error handlers
$kernel->registerExceptionHandlers([
    \Horizon\Routing\Exceptions\RouteNotFoundException::class => 
        function ($e, $request) {
            if ($request->expectsJson()) {
                return Response::json(['error' => 'Route not found'], 404);
            }
            return Response::view('errors.404', [], 404);
        },
    
    \Horizon\Routing\Exceptions\MethodNotAllowedException::class =>
        function ($e, $request) {
            $response = Response::json([
                'error' => 'Method not allowed',
                'allowed_methods' => $e->getAllowedMethods()
            ], 405);
            return $response->header('Allow', $e->getAllowHeader());
        },
]);
```

### Performance Optimization

```php
// Production configuration
$app->config('routing.cache', true);
$app->config('routing.cache_file', '/path/to/cache/routes.php');

// Enable performance monitoring
$router = new FastRouter($routes, $cache, [
    'cache_enabled' => true,
    'performance_monitoring' => true,
    'precompile_routes' => true,
]);

// Warm cache on deployment
if ($app->environment() === 'production') {
    $router->warmUpCache();
}
```

### Testing Routes

```php
use PHPUnit\Framework\TestCase;

class RouteTest extends TestCase
{
    public function testUserRoutes()
    {
        $request = Request::create('/users/123', 'GET');
        $response = $app->handle($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJson($response->getContent());
    }
    
    public function testAuthentication()
    {
        $request = Request::create('/api/profile', 'GET');
        $response = $app->handle($request);
        
        $this->assertEquals(401, $response->getStatusCode());
    }
}
```

## API Reference

### Request Class

```php
// HTTP Methods
$request->method(): string
$request->isGet(): bool
$request->isPost(): bool
$request->isPut(): bool
$request->isDelete(): bool

// URL and Path
$request->url(): string
$request->fullUrl(): string
$request->path(): string
$request->is(string ...$patterns): bool

// Input Data
$request->all(): array
$request->input(?string $key = null, mixed $default = null): mixed
$request->only(array|string $keys): array
$request->except(array|string $keys): array
$request->has(string|array $key): bool
$request->filled(string|array $key): bool

// Files
$request->file(string $key): ?UploadedFile
$request->hasFile(string $key): bool
$request->allFiles(): array

// Headers
$request->header(string $key, mixed $default = null): mixed
$request->bearerToken(): ?string
$request->userAgent(): ?string

// Content Negotiation
$request->accepts(string|array $contentTypes): bool
$request->expectsJson(): bool
$request->wantsJson(): bool
$request->isJson(): bool
```

### Response Class

```php
// Factory Methods
Response::make(string $content = '', int $status = 200): Response
Response::json(mixed $data = null, int $status = 200): Response
Response::redirect(string $to, int $status = 302): Response
Response::file(string $path): Response
Response::download(string $path, string $name = null): Response

// Fluent Interface
$response->status(int $code): Response
$response->header(string $key, string $value): Response
$response->cookie(string $name, string $value): Response
$response->withoutCookie(string $name): Response

// Content
$response->getContent(): string
$response->getStatusCode(): int
$response->getHeaders(): array
```

### Router Class

```php
// Route Registration
$router->get(string $uri, mixed $action): Route
$router->post(string $uri, mixed $action): Route
$router->put(string $uri, mixed $action): Route
$router->patch(string $uri, mixed $action): Route
$router->delete(string $uri, mixed $action): Route
$router->options(string $uri, mixed $action): Route
$router->any(string $uri, mixed $action): Route
$router->match(array $methods, string $uri, mixed $action): Route

// Route Groups
$router->group(array $attributes, Closure $callback): void
$router->newGroup(array $attributes = []): RouteGroup

// Resources
$router->resource(string $name, string $controller): ResourceRouteRegistrar
$router->apiResource(string $name, string $controller): ResourceRouteRegistrar

// Route Matching
$router->matchRequest(Request $request): Route

// URL Generation
$router->route(string $name, array $parameters = []): string
```

### Route Class

```php
// Configuration
$route->name(string $name): Route
$route->middleware(string|array $middleware): Route
$route->where(string|array $wheres): Route
$route->whereNumber(string $parameter): Route
$route->whereAlpha(string $parameter): Route
$route->whereSlug(string $parameter): Route
$route->whereUuid(string $parameter): Route

// Getters
$route->getUri(): string
$route->getMethods(): array
$route->getName(): ?string
$route->getAction(): mixed
$route->getMiddleware(): array
$route->getWheres(): array
```

---

This documentation provides comprehensive coverage of the Horizon Framework's HTTP layer. For more examples and advanced usage, see the `/examples` directory in the framework repository.

**Next Steps:**
- [Middleware Documentation](MIDDLEWARE.md)
- [Security Guide](SECURITY.md)
- [Performance Guide](PERFORMANCE.md)
- [Testing Guide](TESTING.md)