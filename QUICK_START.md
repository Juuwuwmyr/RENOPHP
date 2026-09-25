# 🚀 Horizon Framework - Quick Start Guide

## Is Horizon Easy to Learn?

**YES! Horizon is designed to be beginner-friendly while powerful for experts.**

### Why Horizon is Easy to Learn

#### 1. **Familiar PHP Patterns** 📚
```php
// Just normal PHP classes - no magic
class UserController
{
    public function show(int $id): Response
    {
        $user = User::find($id);
        return response()->json($user);
    }
}
```

#### 2. **Clear, Explicit Code** 🔍
```php
// No hidden magic - everything is clear
$app = new Application(__DIR__);
$config = new Repository(['app' => ['name' => 'My App']]);
$request = Request::capture();
```

#### 3. **Helpful Error Messages** 💡
```
❌ Instead of: "Call to undefined method"
✅ You get: "Unable to resolve [UserRepository] in class [UserController]. 
           Hint: Bind UserRepository in a service provider."
```

#### 4. **Progressive Complexity** 📈
```php
// Start simple
$app = new Application(__DIR__);

// Add features as you need them
$app->singleton(UserService::class);
$app->bind(UserRepositoryInterface::class, DatabaseUserRepository::class);
```

## 5-Minute Setup

### 1. Create Your First App
```php
<?php
// public/index.php
require '../vendor/autoload.php';

use Horizon\Foundation\Application;
use Horizon\Http\Request;
use Horizon\Http\Response;

// Create application
$app = new Application(__DIR__ . '/..');

// Simple route
$request = Request::capture();

if ($request->path() === '/') {
    $response = response()->json([
        'message' => 'Hello, Horizon!',
        'framework' => 'Horizon PHP Framework'
    ]);
} else {
    $response = response('Not Found', 404);
}

$response->send();
```

### 2. Add Configuration
```php
// config/app.php
return [
    'name' => 'My Horizon App',
    'debug' => env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
];
```

### 3. Create a Service
```php
// app/Services/UserService.php
class UserService
{
    public function welcome(string $name): array
    {
        return [
            'message' => "Welcome to Horizon, {$name}!",
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}
```

### 4. Use Dependency Injection
```php
// Register service
$app->singleton(UserService::class);

// Use in controller
class WelcomeController
{
    public function __construct(
        private UserService $userService
    ) {}
    
    public function show(Request $request): Response
    {
        $name = $request->input('name', 'Guest');
        $data = $this->userService->welcome($name);
        
        return response()->json($data);
    }
}
```

## Learning Path 📋

### **Beginner (Day 1-3)**
- ✅ Understand Application & Container
- ✅ Learn Request/Response basics
- ✅ Simple configuration
- ✅ Basic error handling

### **Intermediate (Week 1-2)**
- 🔄 Routing system (Phase 3)
- 🔄 Middleware pipeline
- 🔄 Database integration
- 🔄 Validation

### **Advanced (Month 1)**
- 🔄 Custom service providers
- 🔄 Event system
- 🔄 Queue system
- 🔄 Testing strategies

## Comparison with Other Frameworks

| Feature | Horizon | Laravel | Symfony |
|---------|---------|---------|---------|
| **Learning Curve** | 📗 Easy | 📙 Moderate | 📕 Steep |
| **Magic vs Explicit** | Explicit | Magic | Explicit |
| **Getting Started** | 5 minutes | 15 minutes | 30 minutes |
| **Error Messages** | Crystal clear | Good | Technical |
| **Documentation** | Step-by-step | Comprehensive | Reference-heavy |

## Why Developers Love Horizon

### 🎯 **"It Just Makes Sense"**
```php
// What you expect to work... just works
$user = $app->make(UserService::class);
$config = config('app.name');
$data = $request->input('email');
```

### 🔒 **"Secure by Default"**
```php
// XSS protection automatic
$name = $request->input('name'); // Already safe!

// CSRF protection built-in
// Security headers automatic
// Input validation clear
```

### 🚀 **"Fast to Develop"**
```php
// One file to get started
// No complex setup
// Minimal boilerplate
// Clear patterns
```

### 🧪 **"Easy to Test"**
```php
// Built-in test utilities
public function test_user_creation(): void
{
    $app = new Application();
    $service = $app->make(UserService::class);
    
    $result = $service->create(['name' => 'John']);
    
    $this->assertInstanceOf(User::class, $result);
}
```

## Common Questions

**Q: Is it like Laravel?**
A: Inspired by Laravel's elegance but simpler and more explicit.

**Q: Do I need to know design patterns?**
A: No! Horizon teaches you patterns naturally as you use it.

**Q: Can I use it in production?**
A: Absolutely! Built with security and performance in mind.

**Q: What if I get stuck?**
A: Clear error messages and comprehensive docs help you debug quickly.

## Next Steps

1. **Try the Demo**: `php demo.php`
2. **Read the Tests**: See how everything works
3. **Build Something**: Start with a simple API
4. **Join Community**: Share your experience

---

**🎉 Welcome to Horizon - where PHP development is a joy!**