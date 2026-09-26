# Horizon Framework - Quick Start Guide

Get up and running with the Horizon Framework's HTTP layer in minutes. This guide covers the essential steps to build your first web application.

## Installation

```bash
# Clone the framework (or install via Composer when available)
git clone https://github.com/horizon/framework.git
cd framework

# Install dependencies
composer install

# Set up environment
cp .env.example .env
```

## Hello World Application

Create your first Horizon application:

### 1. Basic Setup

```php
<?php
// public/index.php

require_once __DIR__ . '/../vendor/autoload.php';

use Horizon\Foundation\Application;
use Horizon\Http\Response;

// Create application
$app = Application::create(__DIR__ . '/..');

// Define your first route
$app->get('/', function () {
    return Response::json([
        'message' => 'Hello, Horizon Framework!',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
});

// Run the application
$app->run();
```

### 2. Add More Routes

```php
// Basic routes
$app->get('/about', function () {
    return new Response('About Horizon Framework');
});

$app->post('/contact', function ($request) {
    $name = $request->input('name');
    $email = $request->input('email');
    
    return Response::json([
        'message' => "Thank you, {$name}!",
        'email' => $email
    ]);
});

// Route with parameters
$app->get('/users/{id}', function ($request, $id) {
    return Response::json([
        'user_id' => $id,
        'name' => "User {$id}"
    ]);
});
```

## Working with Controllers

### 1. Create a Controller

```php
<?php
// app/Controllers/UserController.php

class UserController
{
    public function index($request)
    {
        return Response::json([
            'users' => [
                ['id' => 1, 'name' => 'John Doe'],
                ['id' => 2, 'name' => 'Jane Smith']
            ]
        ]);
    }
    
    public function show($request, $id)
    {
        return Response::json([
            'id' => $id,
            'name' => "User {$id}",
            'email' => "user{$id}@example.com"
        ]);
    }
    
    public function store($request)
    {
        $name = $request->input('name');
        $email = $request->input('email');
        
        // Validate input
        if (!$name || !$email) {
            return Response::json(['error' => 'Name and email required'], 400);
        }
        
        return Response::json([
            'message' => 'User created',
            'user' => ['name' => $name, 'email' => $email]
        ], 201);
    }
}
```

### 2. Register Controller Routes

```php
// public/index.php

$app->get('/users', 'UserController@index');
$app->get('/users/{id}', 'UserController@show');
$app->post('/users', 'UserController@store');
```

## Adding Middleware

### 1. Create Authentication Middleware

```php
<?php
// app/Middleware/AuthMiddleware.php

use Horizon\Middleware\MiddlewareInterface;
use Horizon\Http\Request;
use Horizon\Http\Response;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, \Closure $next): Response
    {
        $token = $request->bearerToken();
        
        if (!$token || $token !== 'secret-token') {
            return Response::json(['error' => 'Unauthorized'], 401);
        }
        
        return $next($request);
    }
}
```

### 2. Register and Use Middleware

```php
// Register middleware
$app->routeMiddleware([
    'auth' => AuthMiddleware::class
]);

// Apply to routes
$app->get('/profile', 'UserController@profile')->middleware('auth');

// Apply to route groups
$app->group(['middleware' => 'auth', 'prefix' => 'api'], function ($app) {
    $app->get('/users', 'UserController@index');
    $app->post('/users', 'UserController@store');
});
```

## Working with Route Groups

### 1. API Routes

```php
$app->group(['prefix' => 'api/v1', 'name' => 'api.'], function ($app) {
    
    // Public API routes
    $app->get('/status', function () {
        return Response::json(['status' => 'online']);
    })->name('status');
    
    // Protected API routes
    $app->group(['middleware' => 'auth'], function ($app) {
        $app->resource('users', 'UserController');
        $app->resource('posts', 'PostController');
    });
});
```

### 2. Admin Panel

```php
$app->group([
    'prefix' => 'admin',
    'middleware' => ['auth', 'admin'],
    'name' => 'admin.'
], function ($app) {
    
    $app->get('/dashboard', function () {
        return Response::json(['message' => 'Admin Dashboard']);
    })->name('dashboard');
    
    $app->resource('users', 'Admin\UserController');
});
```

## Resource Routes

### 1. Basic Resource

```php
// Generates 7 RESTful routes
$app->resource('posts', 'PostController');

/*
GET    /posts           posts.index
GET    /posts/create    posts.create  
POST   /posts           posts.store
GET    /posts/{id}      posts.show
GET    /posts/{id}/edit posts.edit
PUT    /posts/{id}      posts.update
DELETE /posts/{id}      posts.destroy
*/
```

### 2. API Resource (No Forms)

```php
// Generates 5 API routes (no create/edit forms)
$app->apiResource('posts', 'PostController');

/*
GET    /posts      posts.index
POST   /posts      posts.store  
GET    /posts/{id} posts.show
PUT    /posts/{id} posts.update
DELETE /posts/{id} posts.destroy
*/
```

### 3. Customized Resources

```php
$app->resource('posts', 'PostController')
    ->only(['index', 'show', 'store'])
    ->middleware(['auth'])
    ->where(['id' => '[0-9]+']);
```

## Error Handling

### 1. Custom Exception Handlers

```php
$app->getKernel()->registerExceptionHandler(
    \InvalidArgumentException::class,
    function ($exception, $request) {
        return Response::json([
            'error' => 'Invalid Request',
            'message' => $exception->getMessage()
        ], 400);
    }
);
```

### 2. 404 and 405 Handling

The framework automatically handles:
- **404 Not Found**: When no route matches
- **405 Method Not Allowed**: When route exists but method doesn't match

## Model Binding

### 1. Configure Model Binding

```php
// Configure automatic model injection
$binder = $app->getKernel()->getBinder();
$binder->model('user', User::class);
$binder->model('post', Post::class);
```

### 2. Use in Routes

```php
// Automatically injects User model
$app->get('/users/{user}', function ($request, User $user) {
    return Response::json($user->toArray());
});

// Multiple models
$app->get('/users/{user}/posts/{post}', function ($request, User $user, Post $post) {
    return Response::json([
        'user' => $user->toArray(),
        'post' => $post->toArray()
    ]);
});
```

## File Uploads

### 1. Handle File Uploads

```php
$app->post('/upload', function ($request) {
    if (!$request->hasFile('document')) {
        return Response::json(['error' => 'No file uploaded'], 400);
    }
    
    $file = $request->file('document');
    
    if (!$file->isValid()) {
        return Response::json(['error' => 'Invalid file'], 400);
    }
    
    // Store the file
    $path = $file->store('/uploads');
    
    return Response::json([
        'message' => 'File uploaded successfully',
        'path' => $path,
        'size' => $file->getSize(),
        'name' => $file->getClientOriginalName()
    ]);
});
```

## Testing Your Application

### 1. Manual Testing

```php
// Test with curl
curl -X GET http://localhost:8000/api/users
curl -X POST http://localhost:8000/api/users -d '{"name":"John","email":"john@example.com"}' -H "Content-Type: application/json"
curl -X GET http://localhost:8000/api/users/1
```

### 2. Unit Testing

```php
<?php
// tests/RouteTest.php

use PHPUnit\Framework\TestCase;
use Horizon\Http\Request;

class RouteTest extends TestCase
{
    protected $app;
    
    public function setUp(): void
    {
        $this->app = Application::create(__DIR__ . '/..');
        // Register your routes here
    }
    
    public function testHomePage()
    {
        $request = Request::create('/', 'GET');
        $response = $this->app->handle($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Hello', $response->getContent());
    }
    
    public function testApiEndpoint()
    {
        $request = Request::create('/api/users', 'GET');
        $response = $this->app->handle($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJson($response->getContent());
    }
}
```

## Performance Optimization

### 1. Enable Route Caching (Production)

```php
use Horizon\Routing\RouteCache;
use Horizon\Routing\FastRouter;

if ($app->environment() === 'production') {
    // Enable route caching
    $cache = new RouteCache('/path/to/cache/routes.cache');
    $fastRouter = new FastRouter($app->getRouter()->getRoutes(), $cache);
    
    // Warm up cache
    $fastRouter->warmUpCache();
}
```

### 2. Configure Middleware

```php
// Optimize middleware for production
$app->middlewareGroups([
    'web' => ['security'],
    'api' => ['cors', 'throttle:1000,60']
]);

$app->middleware(['security']); // Global middleware
```

## Configuration

### 1. Environment Configuration

```env
# .env
APP_NAME="My Horizon App"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://myapp.com

# Route caching
ROUTE_CACHE=true
ROUTE_CACHE_FILE=/path/to/cache/routes.cache
```

### 2. Application Configuration

```php
// Configure the application
$app->config('app.name', 'My Application');
$app->config('app.debug', true);

// Load configuration from file
$app->loadConfig(__DIR__ . '/../config/app.php');
```

## Deployment

### 1. Basic Apache Configuration

```apache
# .htaccess
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/index.php [QSA,L]
</IfModule>
```

### 2. Nginx Configuration

```nginx
server {
    listen 80;
    server_name myapp.com;
    root /var/www/myapp/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

## Common Patterns

### 1. JSON API

```php
// app/Controllers/ApiController.php
class ApiController
{
    protected function jsonResponse($data, $status = 200)
    {
        return Response::json($data, $status);
    }
    
    protected function errorResponse($message, $status = 400)
    {
        return $this->jsonResponse(['error' => $message], $status);
    }
}

class UserApiController extends ApiController
{
    public function index($request)
    {
        return $this->jsonResponse(['users' => $this->getUsers()]);
    }
    
    public function store($request)
    {
        if (!$request->has(['name', 'email'])) {
            return $this->errorResponse('Name and email required');
        }
        
        $user = $this->createUser($request->only(['name', 'email']));
        return $this->jsonResponse(['user' => $user], 201);
    }
}
```

### 2. Web Application with Views

```php
// If you have a view system integrated
$app->get('/dashboard', function ($request) {
    return Response::view('dashboard', [
        'user' => $request->getAttribute('authenticated_user'),
        'stats' => $this->getDashboardStats()
    ]);
});
```

### 3. File Downloads

```php
$app->get('/download/{file}', function ($request, $file) {
    $path = "/uploads/{$file}";
    
    if (!file_exists($path)) {
        return Response::json(['error' => 'File not found'], 404);
    }
    
    return Response::download($path, $file);
});
```

## Next Steps

Now that you have a basic Horizon application running:

1. **Read the Full Documentation**: [HTTP Layer Documentation](HTTP_LAYER.md)
2. **Explore Examples**: Check the `/examples` directory
3. **Add Database Layer**: Integrate with the ORM and database components
4. **Implement Authentication**: Build a complete auth system
5. **Add Testing**: Write comprehensive tests for your application
6. **Deploy**: Set up production deployment

## Getting Help

- **Documentation**: `/docs` directory
- **Examples**: `/examples` directory  
- **Issues**: GitHub repository issues
- **Community**: Framework community forums

Happy coding with Horizon Framework! 🚀