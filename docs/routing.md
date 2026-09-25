# Routing Guide - Horizon Framework

## Table of Contents

1. [Introduction](#introduction)
2. [Basic Routing](#basic-routing)
3. [Route Parameters](#route-parameters)
4. [Route Groups](#route-groups)
5. [Named Routes](#named-routes)
6. [URL Generation](#url-generation)
7. [Route Model Binding](#route-model-binding)
8. [Middleware](#middleware)
9. [Controllers](#controllers)
10. [Route Caching](#route-caching)
11. [Security](#security)
12. [Advanced Features](#advanced-features)

## Introduction

The Horizon framework provides a powerful, flexible, and secure routing system that makes it easy to define web routes for your application. Routes are defined in your `routes/` files and map URIs to controllers or closures.

**Key Features:**
- Clean, expressive syntax
- Powerful parameter binding
- Flexible route groups
- Built-in security middleware
- Performance optimized with caching
- Comprehensive URL generation
- Automatic model binding

## Basic Routing

### Simple Routes

Define routes in your `routes/web.php` or `routes/api.php` files:

```php
use Horizon\Support\Facades\Route;

// Basic GET route
Route::get('/', function () {
    return 'Welcome to Horizon!';
});

// Different HTTP methods
Route::get('/users', 'UserController@index');
Route::post('/users', 'UserController@store');
Route::put('/users/{id}', 'UserController@update');
Route::patch('/users/{id}', 'UserController@patch');
Route::delete('/users/{id}', 'UserController@destroy');
Route::options('/users', 'UserController@options');
```

### Available Router Methods

```php
Route::get($uri, $callback);
Route::post($uri, $callback);
Route::put($uri, $callback);
Route::patch($uri, $callback);
Route::delete($uri, $callback);
Route::options($uri, $callback);
```

### Multiple HTTP Methods

```php
// Match multiple verbs
Route::match(['GET', 'POST'], '/form', function () {
    // Handle both GET and POST
});

// Match any HTTP verb
Route::any('/webhook', function () {
    // Handle all HTTP methods
});
```

### Redirect Routes

```php
Route::redirect('/old-url', '/new-url');
Route::redirect('/old-url', '/new-url', 301); // Permanent redirect
```

## Route Parameters

### Required Parameters

```php
Route::get('/users/{id}', function ($id) {
    return "User ID: {$id}";
});

Route::get('/posts/{id}/comments/{comment}', function ($id, $comment) {
    return "Post {$id}, Comment {$comment}";
});
```

### Optional Parameters

```php
Route::get('/users/{name?}', function ($name = 'Guest') {
    return "Hello, {$name}!";
});

// With default value in closure
Route::get('/posts/{id?}', function ($id = 1) {
    return "Post ID: {$id}";
});
```

### Parameter Constraints

```php
// Numeric constraint
Route::get('/users/{id}', function ($id) {
    return "User ID: {$id}";
})->where('id', '[0-9]+');

// Alphabetic constraint
Route::get('/users/{name}', function ($name) {
    return "User: {$name}";
})->where('name', '[a-zA-Z]+');

// Multiple constraints
Route::get('/users/{id}/posts/{slug}', function ($id, $slug) {
    return "User {$id}, Post: {$slug}";
})->where(['id' => '[0-9]+', 'slug' => '[a-z-]+']);

// Global constraints (in RouteServiceProvider)
Route::pattern('id', '[0-9]+');
Route::pattern('slug', '[a-z0-9-]+');
```

### Common Pattern Examples

```php
// UUID pattern
Route::get('/orders/{uuid}', function ($uuid) {
    // ...
})->where('uuid', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');

// Date pattern
Route::get('/reports/{date}', function ($date) {
    // ...
})->where('date', '\d{4}-\d{2}-\d{2}');

// Slug pattern
Route::get('/blog/{slug}', function ($slug) {
    // ...
})->where('slug', '[a-z0-9\-]+');
```

## Route Groups

Route groups allow you to share route attributes across multiple routes without needing to define those attributes on each individual route.

### Prefix Groups

```php
Route::prefix('admin')->group(function () {
    Route::get('/users', 'AdminController@users');     // /admin/users
    Route::get('/posts', 'AdminController@posts');     // /admin/posts
});

// Nested prefixes
Route::prefix('api')->group(function () {
    Route::prefix('v1')->group(function () {
        Route::get('/users', 'ApiController@users');   // /api/v1/users
    });
});
```

### Middleware Groups

```php
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', 'DashboardController@index');
    Route::get('/profile', 'ProfileController@show');
});

// Multiple middleware
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/settings', 'SettingsController@index');
});
```

### Namespace Groups

```php
Route::namespace('Admin')->group(function () {
    Route::get('/admin/users', 'UserController@index');  // Admin\UserController
});
```

### Named Route Groups

```php
Route::name('admin.')->group(function () {
    Route::get('/admin/users', 'AdminController@users')->name('users');  // admin.users
    Route::get('/admin/posts', 'AdminController@posts')->name('posts');  // admin.posts
});
```

### Domain Groups

```php
Route::domain('api.example.com')->group(function () {
    Route::get('/users', 'ApiController@users');
});

// Dynamic subdomain
Route::domain('{account}.example.com')->group(function () {
    Route::get('/dashboard', function ($account) {
        return "Dashboard for {$account}";
    });
});
```

### Combined Group Attributes

```php
Route::middleware('auth')
    ->prefix('admin')
    ->namespace('Admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/users', 'UserController@index')->name('users');
        Route::get('/posts', 'PostController@index')->name('posts');
    });
```

## Named Routes

Named routes allow you to conveniently generate URLs or redirects for specific routes.

### Defining Named Routes

```php
Route::get('/users/{id}', 'UserController@show')->name('users.show');
Route::post('/users', 'UserController@store')->name('users.store');

// Fluent syntax
Route::name('api.')->group(function () {
    Route::get('/users', 'UserController@index')->name('users.index');
});
```

### Generating URLs to Named Routes

```php
// Generate URL
$url = route('users.show', ['id' => 123]);  // /users/123

// In Blade templates
<a href="{{ route('users.show', $user->id) }}">View User</a>

// With query parameters
$url = route('users.index', ['sort' => 'name', 'order' => 'asc']);
// /users?sort=name&order=asc
```

## URL Generation

The Horizon framework provides several helpers for generating URLs within your application.

### Basic URL Generation

```php
// Current URL
$current = url()->current();

// Previous URL
$previous = url()->previous();

// Full URL
$full = url()->full();

// Generate URL with path
$url = url('/path/to/page');

// Secure URL (HTTPS)
$secure = secure_url('/path');
```

### Asset URLs

```php
// Generate asset URL
$css = asset('css/app.css');        // /css/app.css
$js = asset('js/app.js');           // /js/app.js

// Secure asset URL
$secure = secure_asset('css/app.css');  // https://example.com/css/app.css
```

### Helper Functions

```php
// Named route URL
$url = route('users.show', $user);

// Asset URL
$css = asset('css/style.css');

// Back to previous URL
return back();

// Redirect to named route
return redirect()->route('users.index');
```

## Route Model Binding

Route model binding provides a convenient way to automatically inject model instances into your routes.

### Implicit Binding

```php
// Route definition
Route::get('/users/{user}', function (User $user) {
    return $user->name;
});

// Horizon will automatically resolve User by ID
// /users/123 will find User with ID 123
```

### Customizing the Key

```php
// In your User model
public function getRouteKeyName()
{
    return 'slug';  // Use slug instead of id
}

// Now /users/john-doe will find User where slug = 'john-doe'
```

### Explicit Binding

```php
// In RouteServiceProvider boot method
public function boot()
{
    Route::model('user', User::class);
    
    // Custom resolution logic
    Route::bind('user', function ($value) {
        return User::where('slug', $value)->first() ?? abort(404);
    });
}
```

### Scoped Bindings

```php
// Nested resource binding
Route::get('/users/{user}/posts/{post}', function (User $user, Post $post) {
    // $post will be scoped to $user automatically
    return $post;
});

// Customize scoped binding
Route::get('/users/{user}/posts/{post:slug}', function (User $user, Post $post) {
    // Find post by slug, scoped to user
    return $post;
});
```

## Middleware

Middleware provides a convenient mechanism for filtering HTTP requests entering your application.

### Assigning Middleware to Routes

```php
// Single middleware
Route::get('/admin', 'AdminController@index')->middleware('auth');

// Multiple middleware
Route::get('/admin', 'AdminController@index')->middleware(['auth', 'admin']);

// Middleware with parameters
Route::get('/api/users', 'UserController@index')->middleware('throttle:60,1');
```

### Middleware Groups

```php
// Define middleware groups in HttpServiceProvider
protected $middlewareGroups = [
    'web' => [
        'csrf',
        'session',
        'auth.session',
    ],
    'api' => [
        'throttle:api',
        'auth:sanctum',
    ],
];

// Apply middleware group
Route::middleware('web')->group(function () {
    Route::get('/', 'HomeController@index');
});
```

### Built-in Middleware

The framework includes several built-in middleware:

```php
// CORS handling
Route::middleware('cors')->group(function () {
    // API routes with CORS support
});

// CSRF protection
Route::middleware('csrf')->group(function () {
    // Protected forms
});

// Rate limiting
Route::middleware('throttle:60,1')->group(function () {
    // 60 requests per minute
});

// Authentication
Route::middleware('auth')->group(function () {
    // Protected routes
});

// Security headers
Route::middleware('security.headers')->group(function () {
    // Routes with security headers
});
```

## Controllers

Controllers group related request handling logic into a single class.

### Basic Controllers

```php
<?php

namespace App\Http\Controllers;

use Horizon\Http\Controllers\Controller;
use Horizon\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        return 'User index';
    }
    
    public function show(int $id)
    {
        return "Show user {$id}";
    }
    
    public function store(Request $request)
    {
        // Create new user
        return redirect()->route('users.index');
    }
}
```

### Route to Controller

```php
// Controller method
Route::get('/users', [UserController::class, 'index']);

// String syntax
Route::get('/users', 'UserController@index');

// Resource controller
Route::resource('users', UserController::class);
```

### Controller Middleware

```php
class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('admin')->only('destroy');
        $this->middleware('throttle:5,1')->except('index');
    }
}
```

### Dependency Injection

```php
class UserController extends Controller
{
    public function show(Request $request, UserRepository $users, int $id)
    {
        // Horizon automatically injects dependencies
        $user = $users->find($id);
        
        if ($request->wantsJson()) {
            return response()->json($user);
        }
        
        return view('users.show', compact('user'));
    }
}
```

## Route Caching

For production applications, you should cache your routes to improve performance.

### Caching Routes

```php
# Cache routes for production
php horizon route:cache

# Clear route cache
php horizon route:clear

# List all routes
php horizon route:list
```

### Cache Considerations

- Route caching only works with controller-based routes
- Closures cannot be serialized, so avoid them when caching
- Always test after caching to ensure everything works

```php
// ❌ Won't work with route caching
Route::get('/bad', function () {
    return 'This breaks caching';
});

// ✅ Works with route caching
Route::get('/good', [HomeController::class, 'index']);
```

## Security

The routing system includes several security features by default.

### CSRF Protection

```php
// Automatically applied to web routes
Route::middleware('web')->group(function () {
    Route::post('/contact', 'ContactController@store');  // CSRF protected
});
```

### Rate Limiting

```php
// Limit API requests
Route::middleware('throttle:60,1')->group(function () {
    Route::get('/api/users', 'UserController@index');
});

// Per-user rate limiting
Route::middleware('throttle:rate_limit,1')->group(function () {
    // Uses rate_limit method on authenticated user
});
```

### Security Headers

```php
Route::middleware('security.headers')->group(function () {
    // Adds security headers like X-Frame-Options, X-Content-Type-Options, etc.
});
```

### CORS Configuration

```php
// Configure in config/security.php
'cors' => [
    'allowed_origins' => ['https://example.com'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE'],
    'allowed_headers' => ['Content-Type', 'Authorization'],
    'max_age' => 86400,
],
```

## Advanced Features

### Route Caching Performance

```php
// Before caching - routes parsed on every request
// After caching - routes loaded from serialized cache

// Production deployment
php horizon route:cache
```

### Custom Route Validators

```php
// Create custom route matching logic
class CustomValidator implements RouteValidatorInterface
{
    public function matches(Route $route, Request $request): bool
    {
        // Custom matching logic
        return true;
    }
}
```

### Route Events

```php
// Listen for route matching events
Event::listen('router.matched', function ($route, $request) {
    // Log route access, update metrics, etc.
});
```

### Performance Tips

1. **Use Route Caching**: Always cache routes in production
2. **Minimize Middleware**: Only apply necessary middleware
3. **Optimize Controllers**: Use dependency injection efficiently
4. **Parameter Constraints**: Use regex constraints to avoid unnecessary processing
5. **Group Related Routes**: Use route groups to reduce duplication

### Common Patterns

```php
// API versioning
Route::prefix('api/v1')->name('api.v1.')->group(function () {
    Route::apiResource('users', UserController::class);
});

// Admin panel
Route::middleware(['auth', 'admin'])
    ->prefix('admin')
    ->name('admin.')
    ->namespace('Admin')
    ->group(function () {
        Route::resource('users', UserController::class);
    });

// Multi-tenant routing
Route::domain('{tenant}.app.com')->group(function () {
    Route::get('/', function ($tenant) {
        return "Welcome to {$tenant}";
    });
});
```

## Best Practices

1. **Keep routes organized** - Use separate files for different purposes
2. **Use named routes** - Always name your routes for easier URL generation
3. **Group related routes** - Use route groups to reduce duplication
4. **Apply middleware strategically** - Use middleware groups and careful application
5. **Cache in production** - Always use route caching for production deployments
6. **Use controllers** - Prefer controllers over closures for maintainability
7. **Validate parameters** - Use route parameter constraints for security
8. **Document your routes** - Keep route documentation up to date

## Troubleshooting

### Common Issues

**Route Not Found (404)**
```php
// Check route definition
Route::get('/users/{id}', 'UserController@show');

// Verify controller exists and method is public
php horizon route:list  // List all routes
```

**Method Not Allowed (405)**
```php
// Check HTTP method matches
Route::post('/users', 'UserController@store');  // Only accepts POST
```

**Parameter Binding Issues**
```php
// Ensure parameter names match
Route::get('/users/{user_id}', function ($user_id) {  // ✅ Match
    //
});

Route::get('/users/{user_id}', function ($id) {      // ❌ No match
    //
});
```

**Middleware Not Applied**
```php
// Check middleware is registered in HttpServiceProvider
// Verify middleware group configuration
```

This completes the comprehensive routing guide for the Horizon framework. The system provides a powerful, secure, and performance-optimized routing solution that scales from simple applications to complex enterprise systems.