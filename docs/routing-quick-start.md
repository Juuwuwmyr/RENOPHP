# Quick Start - Routing

This guide will get you up and running with Horizon's routing system in 5 minutes.

## 1. Basic Routes

Create your first route in `routes/web.php`:

```php
<?php

use Horizon\Support\Facades\Route;

// Simple closure route
Route::get('/', function () {
    return 'Hello Horizon!';
});

// Route with parameter
Route::get('/hello/{name}', function ($name) {
    return "Hello, {$name}!";
});
```

## 2. Controller Routes

Create a controller:

```bash
php horizon make:controller WelcomeController
```

```php
<?php

namespace App\Http\Controllers;

use Horizon\Http\Controllers\Controller;

class WelcomeController extends Controller
{
    public function index()
    {
        return 'Welcome to Horizon Framework!';
    }
    
    public function show($id)
    {
        return "Welcome user {$id}";
    }
}
```

Add controller routes:

```php
Route::get('/welcome', [WelcomeController::class, 'index']);
Route::get('/welcome/{id}', [WelcomeController::class, 'show']);
```

## 3. Route Groups

Group related routes together:

```php
// Admin routes with middleware and prefix
Route::middleware('auth')->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminController::class, 'dashboard']);
    Route::get('/users', [AdminController::class, 'users']);
});

// API routes with rate limiting
Route::prefix('api/v1')->middleware('throttle:60,1')->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
});
```

## 4. Named Routes & URL Generation

```php
// Define named routes
Route::get('/users/{id}', [UserController::class, 'show'])->name('users.show');
Route::post('/users', [UserController::class, 'store'])->name('users.store');

// Generate URLs
$url = route('users.show', ['id' => 123]);  // /users/123
$createUrl = route('users.store');          // /users
```

## 5. Route Model Binding

```php
// Automatic model injection
Route::get('/users/{user}', function (User $user) {
    return $user->name;  // Automatically loads User by ID
});

// Custom binding key
Route::get('/users/{user:slug}', function (User $user) {
    return $user->name;  // Loads User by slug field
});
```

## 6. Middleware

```php
// Apply middleware to routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/profile', [ProfileController::class, 'show']);
});

// Multiple middleware
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/settings', [SettingsController::class, 'index']);
});
```

## 7. Resource Routes

```php
// RESTful resource routes
Route::resource('posts', PostController::class);

// Generates these routes:
// GET    /posts              -> index
// GET    /posts/create       -> create  
// POST   /posts              -> store
// GET    /posts/{post}       -> show
// GET    /posts/{post}/edit  -> edit
// PUT    /posts/{post}       -> update
// DELETE /posts/{post}       -> destroy
```

## 8. API Routes

For APIs, use `routes/api.php`:

```php
<?php

use Horizon\Support\Facades\Route;

// API routes (no CSRF, but with rate limiting)
Route::middleware('throttle:60,1')->group(function () {
    
    // Public API
    Route::get('/status', [ApiController::class, 'status']);
    
    // Protected API
    Route::middleware('auth:api')->group(function () {
        Route::apiResource('users', UserApiController::class);
        Route::apiResource('posts', PostApiController::class);
    });
});
```

## 9. Performance - Route Caching

For production, cache your routes:

```bash
# Cache routes
php horizon route:cache

# Clear cache  
php horizon route:clear

# List all routes
php horizon route:list
```

## 10. Common Patterns

### Blog Routes
```php
Route::name('blog.')->prefix('blog')->group(function () {
    Route::get('/', [BlogController::class, 'index'])->name('index');
    Route::get('/{post:slug}', [BlogController::class, 'show'])->name('show');
});
```

### Admin Panel
```php
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::resource('users', AdminUserController::class);
    Route::resource('posts', AdminPostController::class);
});
```

### API with Versioning
```php
Route::prefix('api')->name('api.')->group(function () {
    Route::prefix('v1')->name('v1.')->group(function () {
        Route::apiResource('users', 'Api\V1\UserController');
    });
    
    Route::prefix('v2')->name('v2.')->group(function () {
        Route::apiResource('users', 'Api\V2\UserController');
    });
});
```

## Next Steps

- Read the full [Routing Documentation](routing.md)
- Learn about [Controllers](controllers.md)
- Explore [Middleware](middleware.md)
- Check out [Security Features](security.md)

## Quick Reference

| Method | Purpose |
|--------|---------|
| `Route::get()` | Handle GET requests |
| `Route::post()` | Handle POST requests |
| `Route::put()` | Handle PUT requests |
| `Route::patch()` | Handle PATCH requests |
| `Route::delete()` | Handle DELETE requests |
| `Route::resource()` | RESTful resource routes |
| `Route::group()` | Group routes with shared attributes |
| `Route::middleware()` | Apply middleware to routes |
| `Route::name()` | Name routes for URL generation |
| `route()` | Generate URL for named route |

You're now ready to build amazing web applications with Horizon's routing system! 🚀