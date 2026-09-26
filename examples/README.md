# Horizon Framework - Examples

This directory contains comprehensive examples demonstrating all features of the Horizon Framework's HTTP layer and routing system.

## Quick Navigation

### 📚 **Learning Path** (Recommended Order)
1. [Complete Application Example](#complete-application-example) - Start here!
2. [HTTP Request & Response](#http-request--response)
3. [Basic Routing](#basic-routing) 
4. [Advanced Routing](#advanced-routing)
5. [Middleware System](#middleware-system)
6. [Parameter Binding](#parameter-binding)
7. [HTTP Kernel](#http-kernel--lifecycle)
8. [Performance Optimizations](#performance-optimizations)

### 🎯 **By Feature**

#### HTTP Request & Response
- **[http_request_response_example.php](http_request_response_example.php)**
  - Request parameter handling (GET, POST, JSON)
  - File uploads with security validation
  - Response building and content negotiation
  - Header and cookie management
  - Content types and format detection

#### Basic Routing
- **[routing_example.php](routing_example.php)**
  - Route registration for all HTTP methods
  - Named routes and URL generation
  - Route parameters and constraints
  - Route groups and prefixes
  - Resource routes and REST APIs
  - Error handling (404, 405)

#### Advanced Routing
- **[advanced_routing_example.php](advanced_routing_example.php)**
  - Enhanced route groups with nesting
  - Versioned APIs and conditional routing
  - Custom resource routes with member/collection actions
  - Nested resources and complex patterns
  - Route organization strategies

#### Middleware System
- **[middleware_example.php](middleware_example.php)**
  - Middleware pipeline processing
  - Global, route, and group middleware
  - Built-in security middleware (CORS, Security, Rate Limiting)
  - Custom middleware creation
  - Error handling and conditional middleware

#### Parameter Binding
- **[parameter_binding_example.php](parameter_binding_example.php)**
  - Model binding and dependency injection
  - Custom parameter resolvers
  - Constraint validation and transformation
  - Route parameter binding with models
  - Advanced binding scenarios

#### HTTP Kernel & Lifecycle
- **[http_kernel_example.php](http_kernel_example.php)**
  - Complete request/response lifecycle
  - Application bootstrap and configuration
  - Middleware orchestration
  - Exception handling and custom handlers
  - Lifecycle hooks and performance monitoring

#### Performance Optimizations
- **[routing_performance_example.php](routing_performance_example.php)**
  - Route compilation and caching
  - FastRouter with O(1) static lookups
  - Performance benchmarking
  - Cache management and statistics
  - Production optimization strategies

### 🏗️ **Architecture Examples**

#### Models (for binding examples)
- **[Models/User.php](Models/User.php)** - Sample User model
- **[Models/Post.php](Models/Post.php)** - Sample Post model

## Complete Application Example

### 🚀 **[complete_blog_api.php](complete_blog_api.php)**

A complete blog API demonstrating all framework features:

**Features Covered:**
- ✅ RESTful API design
- ✅ Authentication middleware
- ✅ Model binding and validation
- ✅ File uploads and management
- ✅ Rate limiting and security
- ✅ Error handling and logging
- ✅ Performance optimization
- ✅ Testing examples

**API Endpoints:**
```
Authentication:
POST /auth/login
POST /auth/logout
GET  /auth/profile

Posts:
GET    /posts           - List posts
POST   /posts           - Create post
GET    /posts/{id}      - Get post
PUT    /posts/{id}      - Update post
DELETE /posts/{id}      - Delete post
POST   /posts/{id}/upload - Upload image

Users:
GET    /users           - List users (admin)
GET    /users/{id}      - Get user
PUT    /users/{id}      - Update user

Admin:
GET    /admin/stats     - Statistics
GET    /admin/logs      - System logs
```

## Running Examples

### Prerequisites

```bash
# Ensure you have PHP 8.1+
php --version

# Install dependencies (if using Composer)
composer install
```

### Basic Usage

Each example is self-contained and can be run directly:

```bash
# Run individual examples
php examples/http_request_response_example.php
php examples/routing_example.php
php examples/middleware_example.php

# Run the complete application example
php examples/complete_blog_api.php
```

### Testing Examples

Many examples include built-in test scenarios:

```bash
# HTTP Request/Response features
php examples/http_request_response_example.php

# Routing with various patterns
php examples/routing_example.php

# Middleware pipeline processing
php examples/middleware_example.php

# Performance benchmarking
php examples/routing_performance_example.php
```

### Web Server Testing

For examples that simulate HTTP servers:

```bash
# Start PHP built-in server
php -S localhost:8000 examples/complete_blog_api.php

# Test with curl
curl http://localhost:8000/posts
curl -X POST http://localhost:8000/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"secret"}'
```

## Understanding the Examples

### 📖 **Code Structure**

Each example follows this structure:

```php
<?php
/**
 * Example Title
 * 
 * Description of what this example demonstrates
 */

// 1. Setup and imports
require_once __DIR__ . '/../vendor/autoload.php';

// 2. Feature demonstration with clear sections
echo "1. BASIC FEATURE\n";
echo "================\n\n";

// 3. Practical examples with output
$result = demonstrateFeature();
echo "✅ Feature demonstrated\n\n";

// 4. Advanced usage
echo "2. ADVANCED FEATURE\n";
echo "===================\n\n";

// 5. Summary and next steps
echo "Ready for production! 🚀\n";
```

### 🎓 **Learning Tips**

1. **Start Simple**: Begin with `http_request_response_example.php`
2. **Read Comments**: Examples include detailed explanations
3. **Experiment**: Modify examples to understand behavior
4. **Check Output**: Each example shows expected results
5. **Follow Progression**: Examples build upon each other

### 🔧 **Customization**

Examples are designed to be easily modified:

```php
// Change this in any routing example
$routes = [
    ['GET', '/custom-endpoint', function() { return 'Hello!'; }]
];

// Modify middleware in middleware examples  
$customMiddleware = function($request, $next) {
    // Your custom logic here
    return $next($request);
};

// Adjust performance test parameters
$testRouteCount = 1000; // Increase for stress testing
```

## Example Categories

### 🟢 **Beginner Examples**
- HTTP Request/Response basics
- Simple routing patterns
- Basic middleware usage

### 🟡 **Intermediate Examples**
- Advanced routing with groups
- Custom middleware creation
- Model binding and validation

### 🔴 **Advanced Examples**
- Performance optimization
- Complex application architecture
- Production deployment patterns

## Integration Examples

### With Popular Tools

```php
// PSR-7 Integration
use Psr\Http\Message\ServerRequestInterface;

// Monolog Integration
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Twig Integration (if available)
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
```

### Testing Integration

```php
// PHPUnit Integration
use PHPUnit\Framework\TestCase;

class ApiTest extends TestCase
{
    public function testApiEndpoint()
    {
        $app = require __DIR__ . '/complete_blog_api.php';
        $request = Request::create('/posts', 'GET');
        $response = $app->handle($request);
        
        $this->assertEquals(200, $response->getStatusCode());
    }
}
```

## Production Examples

### Configuration Management

```php
// Environment-based configuration
$config = [
    'development' => [
        'debug' => true,
        'cache' => false,
    ],
    'production' => [
        'debug' => false,
        'cache' => true,
        'cache_ttl' => 3600,
    ]
];
```

### Deployment Scripts

```bash
#!/bin/bash
# deployment/deploy.sh

# Clear route cache
php artisan route:cache-clear

# Warm up cache
php artisan route:cache

# Optimize autoloader
composer dump-autoload --optimize

# Set permissions
chmod -R 755 storage/
```

## Troubleshooting

### Common Issues

1. **Class Not Found**
   ```bash
   # Ensure autoloader is included
   require_once __DIR__ . '/../vendor/autoload.php';
   ```

2. **Route Not Found**
   ```php
   // Check route registration
   $router->get('/test', function() { return 'Test'; });
   ```

3. **Middleware Not Executing**
   ```php
   // Verify middleware registration
   $app->routeMiddleware(['auth' => AuthMiddleware::class]);
   ```

### Getting Help

- Check the full [HTTP Layer Documentation](../docs/HTTP_LAYER.md)
- Review the [Quick Start Guide](../docs/QUICK_START.md)
- Examine error messages carefully
- Enable debug mode for detailed error information

## Contributing Examples

### Adding New Examples

1. Follow the established structure
2. Include comprehensive comments
3. Demonstrate both basic and advanced usage
4. Add clear output formatting
5. Update this README

### Example Template

```php
<?php
declare(strict_types=1);

/**
 * [Feature Name] Example
 * 
 * Demonstrates [specific feature] with [brief description].
 * 
 * Features covered:
 * - Feature 1
 * - Feature 2
 * - Feature 3
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "Horizon Framework - [Feature Name] Example\n";
echo str_repeat("=", 50) . "\n\n";

try {
    // Your example code here
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "✅ EXAMPLE COMPLETE!\n";
echo str_repeat("=", 50) . "\n";
```

---

**Next Steps:**
1. Start with [complete_blog_api.php](complete_blog_api.php) for a full application example
2. Explore specific features with individual examples
3. Read the [HTTP Layer Documentation](../docs/HTTP_LAYER.md) for detailed API reference
4. Check out [Quick Start Guide](../docs/QUICK_START.md) for step-by-step instructions

Happy coding! 🚀