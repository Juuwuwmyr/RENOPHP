# RENOPHP

<p align="center">
  <strong>A Modern PHP Web Framework</strong><br>
  <em>Less Magic. More Understanding.</em>
</p>

<p align="center">
  <a href="#features">Features</a> •
  <a href="#installation">Installation</a> •
  <a href="#quick-start">Quick Start</a> •
  <a href="#documentation">Documentation</a> •
  <a href="#philosophy">Philosophy</a>
</p>

---

## About RENOPHP

**RENOPHP** is a modern PHP web framework built for developers who value simplicity, security, and understanding over hidden magic. Created by **Moreno Jumyr**, it combines the best ideas from Laravel, Symfony, and Rails while maintaining a clear, debuggable architecture.

Whether you're building a simple API or a complex web application, RENOPHP provides the tools you need without unnecessary complexity.

> **"Less Magic. More Understanding."**

---

## Features

### 🚀 **Core Features**

- **Powerful Routing** - Clean, expressive routing with parameter binding and middleware support
- **Dependency Injection** - Automatic dependency resolution with a lightweight container
- **Eloquent-Style ORM** - Beautiful, fluent database interactions with relationship support
- **Query Builder** - Secure, chainable database queries with protection against SQL injection
- **Database Migrations** - Version control for your database schema
- **Request/Response** - Rich HTTP abstractions for handling requests and responses
- **Middleware Pipeline** - Clean request filtering with built-in CSRF and CORS protection
- **Blade-Like Templates** - Elegant templating engine with automatic XSS protection
- **Validation System** - 30+ built-in validation rules with custom validator support
- **Authentication** - Secure session-based authentication with remember me functionality
- **Authorization** - Gates and Policies for fine-grained access control
- **Password Hashing** - Bcrypt and Argon2 support with automatic rehashing
- **Session Management** - Secure session handling with multiple driver support
- **CLI Console** - Beautiful command-line interface with color output and progress bars

### 🔒 **Security First**

- CSRF protection enabled by default
- XSS protection in templates
- SQL injection prevention through prepared statements
- Secure password hashing (Bcrypt/Argon2)
- HTTP-only, secure session cookies
- Mass assignment protection in models
- Input validation and sanitization

### 🎯 **Developer Experience**

- Clear error messages that actually help
- Minimal boilerplate code
- Explicit over implicit behavior
- Easy to debug and understand
- Comprehensive documentation
- Working code examples included
- No unnecessary magic
- Production-ready from day one

---

## Requirements

- **PHP 8.0+** (PHP 8.1+ recommended)
- **Composer** for dependency management
- **PDO Extension** with MySQL, PostgreSQL, or SQLite driver
- **OpenSSL Extension** for secure operations
- **Mbstring Extension** for string handling

---

## Installation

### Option 1: Clone the Repository

```bash
git clone https://github.com/yourusername/renophp.git myapp
cd myapp
composer install
```

### Option 2: Use Composer (Coming Soon)

```bash
composer create-project renophp/renophp myapp
cd myapp
```

### Configure Environment

```bash
# Copy the example environment file
cp .env.example .env

# Edit .env with your database credentials
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=your_database
# DB_USERNAME=your_username
# DB_PASSWORD=your_password
```

### Run Development Server

```bash
php -S localhost:8000 -t public
```

Visit `http://localhost:8000` in your browser!

---

## Quick Start

### 1. Define Routes

**routes/web.php**
```php
<?php

use Reno\Routing\Router;

$router = new Router();

$router->get('/', function() {
    return view('welcome');
});

$router->get('/users', [UserController::class, 'index']);
$router->post('/users', [UserController::class, 'store']);
$router->get('/users/{id}', [UserController::class, 'show']);

return $router;
```

### 2. Create a Controller

**app/Controllers/UserController.php**
```php
<?php

namespace App\Controllers;

use App\Models\User;
use Reno\Http\Request;
use Reno\Http\Response;

class UserController
{
    public function index()
    {
        $users = User::all();
        return Response::json($users);
    }

    public function show($id)
    {
        $user = User::findOrFail($id);
        return Response::json($user);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8'
        ]);

        $user = User::create($validated);
        return Response::json($user, 201);
    }
}
```

### 3. Create a Model

**app/Models/User.php**
```php
<?php

namespace App\Models;

use Reno\Database\Eloquent\Model;
use Reno\Auth\Contracts\Authenticatable;
use Reno\Auth\Authenticatable as AuthenticatableTrait;

class User extends Model implements Authenticatable
{
    use AuthenticatableTrait;

    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password', 'remember_token'];

    public function posts()
    {
        return $this->hasMany(Post::class);
    }
}
```

### 4. Create a Migration

```bash
php console make:migration create_users_table
```

**database/migrations/2024_01_01_000000_create_users_table.php**
```php
<?php

use Reno\Database\Migrations\Migration;
use Reno\Database\Schema\Blueprint;
use Reno\Database\Schema\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
```

Run migrations:
```bash
php console migrate
```

### 5. Create a View

**resources/views/users/index.blade.php**
```html
@extends('layouts.app')

@section('content')
    <h1>Users</h1>
    
    @if($users->isEmpty())
        <p>No users found.</p>
    @else
        <ul>
            @foreach($users as $user)
                <li>{{ $user->name }} - {{ $user->email }}</li>
            @endforeach
        </ul>
    @endif
@endsection
```

---

## Architecture

### Directory Structure

```
renophp/
├── app/
│   ├── Controllers/      # HTTP controllers
│   ├── Models/           # Eloquent models
│   ├── Middleware/       # Custom middleware
│   └── Providers/        # Service providers
├── config/
│   ├── app.php           # Application config
│   ├── database.php      # Database config
│   └── session.php       # Session config
├── database/
│   ├── migrations/       # Database migrations
│   └── seeders/          # Database seeders
├── public/
│   └── index.php         # Application entry point
├── resources/
│   └── views/            # View templates
├── routes/
│   ├── web.php           # Web routes
│   └── api.php           # API routes
├── src/                  # Framework core
├── storage/
│   ├── cache/            # Application cache
│   ├── logs/             # Application logs
│   └── sessions/         # Session files
├── tests/                # Test files
└── vendor/               # Composer dependencies
```

### Core Components

#### Container & Dependency Injection
```php
// Automatic dependency resolution
$app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);

// Singleton services
$app->singleton(Cache::class, function($app) {
    return new FileCache($app['config']['cache.path']);
});
```

#### Database Query Builder
```php
$users = DB::table('users')
    ->where('status', 'active')
    ->where('age', '>', 18)
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();
```

#### Eloquent ORM
```php
// Relationships
$user = User::with('posts')->find(1);

// Scopes
User::where('active', true)->recent()->paginate(15);

// Events
$user->save(); // Fires creating, created, updating, updated events
```

#### Validation
```php
$validator = validator($data, [
    'email' => 'required|email|unique:users',
    'password' => 'required|min:8|confirmed',
    'age' => 'required|integer|min:18|max:120',
    'website' => 'nullable|url'
]);

if ($validator->fails()) {
    return Response::json($validator->errors(), 422);
}
```

#### Authentication
```php
// Login
if (auth()->attempt(['email' => $email, 'password' => $password])) {
    return redirect('/dashboard');
}

// Current user
$user = auth()->user();

// Logout
auth()->logout();
```

#### Authorization
```php
// Gates
Gate::define('update-post', function ($user, $post) {
    return $user->id === $post->user_id;
});

// Policies
class PostPolicy
{
    public function update(User $user, Post $post)
    {
        return $user->id === $post->user_id;
    }
}

// Check authorization
$user->can('update', $post);
```

---

## Philosophy

RENOPHP is built on core principles that guide every design decision:

### 1. **Simple by Default**
Start with minimal complexity. Add complexity only when needed.

### 2. **Explicit Over Hidden Magic**
Code should be easy to trace and debug. Avoid excessive abstraction.

### 3. **Secure by Default**
Security features are enabled out of the box, not opt-in.

### 4. **Modular Architecture**
Use only the components you need. Small projects stay small.

### 5. **Developer Experience First**
Clear error messages, helpful documentation, and intuitive APIs.

### 6. **Performance Matters**
Fast by design, with optimization opportunities clearly marked.

### 7. **Testability**
Every component can be tested in isolation.

### 8. **Backward Compatibility**
Respect your time investment. Upgrades should be smooth.

---

## Documentation

### Comprehensive Guides

- **[Quick Start Guide](QUICK_START.md)** - Get up and running in 5 minutes
- **[HTTP Layer Documentation](docs/HTTP_LAYER.md)** - Routing, requests, responses
- **[ORM API Reference](docs/ORM_API_REFERENCE.md)** - Complete Eloquent guide
- **[Validation Guide](docs/VALIDATION_GUIDE.md)** - All validation rules and usage
- **[View Guide](docs/VIEW_GUIDE.md)** - Template syntax and components

### Examples

Check the `examples/` directory for working code samples:

- Basic routing examples
- Advanced routing with middleware
- Controller examples
- Model relationships
- Authentication flows
- Authorization policies
- View components and layouts

---

## CLI Commands

RENOPHP includes a powerful command-line interface:

```bash
# Run development server
php console serve

# Database migrations
php console migrate
php console migrate:rollback
php console migrate:fresh

# Generate code
php console make:controller UserController
php console make:model Post
php console make:migration create_posts_table

# View routes
php console route:list

# Clear cache
php console cache:clear
php console config:clear
```

---

## Testing

RENOPHP is built with testing in mind:

```php
<?php

use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function test_user_can_be_created()
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'secret123'
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com'
        ]);
    }
}
```

Run tests:
```bash
./vendor/bin/phpunit
```

---

## Security

### Reporting Vulnerabilities

If you discover a security vulnerability, please email security@renophp.com. All security vulnerabilities will be promptly addressed.

### Security Features

- CSRF token validation on all state-changing requests
- XSS protection through automatic output escaping
- SQL injection prevention via prepared statements
- Secure password hashing with Bcrypt/Argon2
- Session security (HTTP-only, secure, SameSite cookies)
- Mass assignment protection
- Rate limiting support

---

## Contributing

We welcome contributions! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

### Development Setup

```bash
git clone https://github.com/yourusername/renophp.git
cd renophp
composer install
cp .env.example .env
php console migrate
./vendor/bin/phpunit
```

---

## Roadmap

### Current Version: 1.0.0

### Planned Features

- **Queue System** - Background job processing
- **Event System** - Application event broadcasting
- **Email System** - Send emails with multiple drivers
- **File Storage** - S3, local, and cloud storage abstraction
- **API Rate Limiting** - Built-in rate limiting middleware
- **WebSocket Support** - Real-time features
- **Package Management** - First-party package ecosystem

---

## Credits

**RENOPHP** is created and maintained by **Moreno Jumyr**.

### Inspiration

This framework draws inspiration from:
- **Laravel** - Developer experience and elegant syntax
- **Symfony** - Component architecture and best practices
- **Ruby on Rails** - Convention over configuration
- **Django** - Security-first approach

### License

RENOPHP is open-source software licensed under the [MIT license](LICENSE).

---

## Community

- **GitHub**: [github.com/yourusername/renophp](https://github.com/yourusername/renophp)
- **Issues**: [github.com/yourusername/renophp/issues](https://github.com/yourusername/renophp/issues)
- **Discussions**: [github.com/yourusername/renophp/discussions](https://github.com/yourusername/renophp/discussions)

---

## Support

If RENOPHP helps you build better applications, please consider:

- ⭐ **Star the repository** on GitHub
- 📢 **Share** with other developers
- 🐛 **Report bugs** to help us improve
- 💡 **Suggest features** you'd like to see
- 📖 **Contribute** to documentation

---

<p align="center">
  <strong>Built with ❤️ by developers, for developers.</strong><br>
  <em>Less Magic. More Understanding.</em>
</p>
