<?php

declare(strict_types=1);

/**
 * Route Parameter Binding and Constraints Example
 * 
 * Demonstrates the Horizon Framework's advanced parameter binding system
 * with model binding, custom resolvers, constraint validation, and
 * dependency injection.
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Models/User.php';
require_once __DIR__ . '/Models/Post.php';

echo "Horizon Framework - Parameter Binding & Constraints Example\n";
echo "===========================================================\n\n";

use Horizon\Routing\Router;
use Horizon\Routing\Route;
use Horizon\Routing\RouteParameterBinder;
use Horizon\Routing\ConstraintValidator;
use Horizon\Routing\Exceptions\ModelNotFoundException;
use Horizon\Http\Request;
use Horizon\Http\Response;
use Examples\Models\User;
use Examples\Models\Post;

try {
    echo "1. BASIC MODEL BINDING\n";
    echo "======================\n\n";

    $binder = new RouteParameterBinder();
    $validator = new ConstraintValidator();

    // Register model bindings
    $binder->model('user', User::class);
    $binder->model('post', Post::class);

    echo "✅ Registered model bindings:\n";
    echo "   user -> " . User::class . "\n";
    echo "   post -> " . Post::class . "\n\n";

    // Test basic model binding
    $userRoute = new Route(['GET'], '/users/{user}', function (Request $request, User $user) {
        return Response::json($user->toArray());
    });
    $userRoute->setParameters(['user' => '1']);

    echo "🔍 Testing user model binding:\n";
    $resolvedParams = $binder->resolveParameters($userRoute, Request::create('/users/1'));
    echo "   Route: /users/{user}\n";
    echo "   Parameter: user = 1\n";
    echo "   Resolved: " . get_class($resolvedParams['user']) . " (ID: {$resolvedParams['user']->id})\n";
    echo "   User Name: {$resolvedParams['user']->name}\n\n";

    echo "2. CUSTOM BINDING RESOLVERS\n";
    echo "===========================\n\n";

    // Custom binding for post by slug
    $binder->bind('post_slug', function ($value, $route, $request) {
        echo "   🔍 Custom resolver: Looking for post with slug '{$value}'\n";
        $post = Post::findBySlug($value);
        
        if (!$post) {
            throw new ModelNotFoundException("No post found with slug: {$value}");
        }
        
        return $post;
    });

    echo "✅ Registered custom slug binding\n\n";

    $slugRoute = new Route(['GET'], '/posts/{post_slug}', function (Request $request, Post $post) {
        return Response::json($post->toArray());
    });
    $slugRoute->setParameters(['post_slug' => 'getting-started-horizon-framework']);

    echo "🔍 Testing custom slug binding:\n";
    $slugParams = $binder->resolveParameters($slugRoute, Request::create('/posts/getting-started-horizon-framework'));
    echo "   Route: /posts/{post_slug}\n";
    echo "   Parameter: post_slug = getting-started-horizon-framework\n";
    echo "   Resolved: " . get_class($slugParams['post_slug']) . "\n";
    echo "   Post Title: {$slugParams['post_slug']->title}\n\n";

    echo "3. IMPLICIT MODEL BINDING\n";
    echo "=========================\n\n";

    // Register implicit bindings
    $binder->implicitModel(User::class, 'id');
    $binder->implicitModel(Post::class, 'id');

    echo "✅ Registered implicit model bindings\n\n";

    $implicitRoute = new Route(['GET'], '/users/{user}/posts/{post}', function (Request $request, User $user, Post $post) {
        return Response::json([
            'user' => $user->toArray(),
            'post' => $post->toArray()
        ]);
    });
    $implicitRoute->setParameters(['user' => '1', 'post' => '2']);

    echo "🔍 Testing implicit model binding:\n";
    $implicitParams = $binder->resolveParameters($implicitRoute, Request::create('/users/1/posts/2'));
    echo "   Route: /users/{user}/posts/{post}\n";
    echo "   Parameters: user = 1, post = 2\n";
    echo "   Resolved User: {$implicitParams['user']->name}\n";
    echo "   Resolved Post: {$implicitParams['post']->title}\n\n";

    echo "4. PARAMETER TRANSFORMERS\n";
    echo "=========================\n\n";

    // Register parameter transformers
    $binder->transformer('id', function ($value, $route, $request) {
        echo "   🔄 Transforming ID: '{$value}' -> " . (int)$value . "\n";
        return (int) $value;
    });

    $binder->transformer('slug', function ($value, $route, $request) {
        echo "   🔄 Normalizing slug: '{$value}' -> " . strtolower(trim($value)) . "\n";
        return strtolower(trim($value));
    });

    echo "✅ Registered parameter transformers\n\n";

    $transformRoute = new Route(['GET'], '/api/posts/{id}', function (Request $request, $id) {
        return Response::json(['id' => $id, 'type' => gettype($id)]);
    });
    $transformRoute->setParameters(['id' => '123']);

    echo "🔍 Testing parameter transformers:\n";
    $transformParams = $binder->resolveParameters($transformRoute, Request::create('/api/posts/123'));
    echo "   Original: '123' (string)\n";
    echo "   Transformed: {$transformParams['id']} (" . gettype($transformParams['id']) . ")\n\n";

    echo "5. CONSTRAINT VALIDATION\n";
    echo "========================\n\n";

    echo "📋 Available constraint patterns:\n";
    $patterns = ConstraintValidator::getPatterns();
    foreach (array_slice($patterns, 0, 8) as $name => $pattern) {
        echo "   {$name}: /{$pattern}/\n";
    }
    echo "   ... and " . (count($patterns) - 8) . " more patterns\n\n";

    // Register custom validators
    $validator->validator('strong_password', function ($value, $parameters, $name, $route, $request) {
        if (strlen($value) < 8) {
            return 'Password must be at least 8 characters.';
        }
        
        if (!preg_match('/[A-Z]/', $value)) {
            return 'Password must contain at least one uppercase letter.';
        }
        
        if (!preg_match('/[0-9]/', $value)) {
            return 'Password must contain at least one number.';
        }
        
        return true;
    });

    echo "✅ Registered custom validators\n\n";

    // Test various constraints
    $constraintTests = [
        ['alpha', 'hello', true],
        ['alpha', 'hello123', false],
        ['numeric', '12345', true],
        ['numeric', 'abc123', false],
        ['uuid', '123e4567-e89b-12d3-a456-426614174000', true],
        ['uuid', 'invalid-uuid', false],
        ['email', 'user@example.com', true],
        ['email', 'invalid-email', false],
        ['min:5', 'hello world', true],
        ['min:5', 'hi', false],
        ['between:5,15', 'good length', true],
        ['between:5,15', 'x', false],
        ['in:red,green,blue', 'red', true],
        ['in:red,green,blue', 'yellow', false],
    ];

    echo "🧪 Testing constraint validation:\n";
    foreach ($constraintTests as [$constraint, $value, $expected]) {
        $result = $validator->validateConstraint('test', $value, $constraint);
        $status = ($result === true) === $expected ? '✅' : '❌';
        $resultText = $result === true ? 'PASS' : 'FAIL: ' . $result;
        echo "   {$status} {$constraint} with '{$value}': {$resultText}\n";
    }
    echo "\n";

    echo "6. ROUTE CONSTRAINT INTEGRATION\n";
    echo "===============================\n\n";

    $router = new Router();

    // Routes with constraints
    $router->get('/users/{id}', function (Request $request, $id) {
        return Response::json(['user_id' => $id]);
    })->whereNumber('id');

    $router->get('/posts/{slug}', function (Request $request, $slug) {
        return Response::json(['post_slug' => $slug]);
    })->whereSlug('slug');

    $router->get('/profile/{username}', function (Request $request, $username) {
        return Response::json(['username' => $username]);
    })->whereAlpha('username');

    $router->get('/api/v{version}/status', function (Request $request, $version) {
        return Response::json(['api_version' => $version]);
    })->where('version', '[1-9]');

    echo "✅ Created routes with constraints\n\n";

    // Test route matching with constraints
    $constraintRouteTests = [
        ['GET', '/users/123', true],
        ['GET', '/users/abc', false],
        ['GET', '/posts/my-awesome-post', true],
        ['GET', '/posts/Invalid_Slug!', false],
        ['GET', '/profile/johndoe', true],
        ['GET', '/profile/john123', false],
        ['GET', '/api/v1/status', true],
        ['GET', '/api/v0/status', false],
    ];

    echo "🔍 Testing route constraint matching:\n";
    foreach ($constraintRouteTests as [$method, $uri, $shouldMatch]) {
        try {
            $request = Request::create($uri, $method);
            $route = $router->matchRequest($request);
            $status = $shouldMatch ? '✅' : '❌';
            echo "   {$status} {$method} {$uri}: MATCHED\n";
        } catch (Exception $e) {
            $status = !$shouldMatch ? '✅' : '❌';
            echo "   {$status} {$method} {$uri}: {$e->getMessage()}\n";
        }
    }
    echo "\n";

    echo "7. DEPENDENCY INJECTION\n";
    echo "=======================\n\n";

    // Mock container resolver
    $containerResolver = function (string $class) {
        echo "   🏭 Container resolving: {$class}\n";
        
        if ($class === User::class) {
            return new User(['id' => 999, 'name' => 'Container User', 'email' => 'container@example.com']);
        }
        
        if ($class === 'SomeService') {
            return new class {
                public function getName() { return 'MockService'; }
            };
        }
        
        return null;
    };

    $binder->setContainerResolver($containerResolver);

    echo "✅ Configured container resolver\n\n";

    // Route with dependency injection
    $diRoute = new Route(['GET'], '/inject-test/{id}', function (Request $request, User $user, $id, $mockService) {
        return Response::json([
            'request_path' => $request->path(),
            'injected_user' => $user->toArray(),
            'route_param' => $id,
            'service_name' => $mockService->getName(),
        ]);
    });
    $diRoute->setParameters(['id' => '42']);

    echo "🔍 Testing dependency injection:\n";
    // This would normally be called by the framework
    echo "   Route: /inject-test/{id}\n";
    echo "   Simulating method parameter resolution...\n";
    echo "   ✅ Request object would be injected\n";
    echo "   ✅ User model would be resolved via container\n";
    echo "   ✅ Route parameters would be passed\n";
    echo "   ✅ Additional services would be injected\n\n";

    echo "8. ERROR HANDLING\n";
    echo "=================\n\n";

    echo "🚨 Testing model not found scenarios:\n\n";

    // Test model not found
    try {
        $notFoundRoute = new Route(['GET'], '/users/{user}', function (Request $request, User $user) {
            return Response::json($user->toArray());
        });
        $notFoundRoute->setParameters(['user' => '999']); // Non-existent user

        $binder->resolveParameters($notFoundRoute, Request::create('/users/999'));
        echo "   ❌ Should have thrown ModelNotFoundException\n";
    } catch (ModelNotFoundException $e) {
        echo "   ✅ ModelNotFoundException caught:\n";
        echo "      Model: {$e->getModel()}\n";
        echo "      Parameter: {$e->getParameterKey()} = {$e->getParameterValue()}\n";
        echo "      Message: {$e->getMessage()}\n";
    }
    echo "\n";

    // Test constraint validation failure
    echo "🚨 Testing constraint validation failures:\n\n";

    $failRoute = new Route(['GET'], '/test/{value}', function (Request $request, $value) {
        return Response::json(['value' => $value]);
    });
    $failRoute->where('value', 'numeric');
    $failRoute->setParameters(['value' => 'not-a-number']);

    $errors = $validator->validateRoute($failRoute, Request::create('/test/not-a-number'));
    
    if (!empty($errors)) {
        echo "   ✅ Validation errors found:\n";
        foreach ($errors as $param => $error) {
            echo "      {$param}: {$error}\n";
        }
    } else {
        echo "   ❌ Expected validation errors\n";
    }
    echo "\n";

    echo "9. PERFORMANCE TESTING\n";
    echo "======================\n\n";

    $performanceTest = function (int $iterations) use ($binder, $validator) {
        $route = new Route(['GET'], '/perf/{id}', function (Request $request, $id) {
            return Response::json(['id' => $id]);
        });
        $route->whereNumber('id');
        $route->setParameters(['id' => '123']);
        $request = Request::create('/perf/123');

        $start = microtime(true);
        
        for ($i = 0; $i < $iterations; $i++) {
            $binder->resolveParameters($route, $request);
            $validator->validateRoute($route, $request);
        }
        
        return round((microtime(true) - $start) * 1000, 2);
    };

    echo "⚡ Parameter binding performance:\n";
    $testCases = [100, 500, 1000];
    foreach ($testCases as $iterations) {
        $time = $performanceTest($iterations);
        $avgTime = round($time / $iterations, 4);
        echo "   {$iterations} iterations: {$time}ms (avg: {$avgTime}ms per operation)\n";
    }
    echo "\n";

    echo "10. BINDING STATISTICS\n";
    echo "======================\n\n";

    $binderStats = $binder->getStats();
    echo "📊 Parameter Binder Statistics:\n";
    foreach ($binderStats as $key => $value) {
        if (is_array($value)) {
            echo "   {$key}:\n";
            foreach ($value as $subKey => $subValue) {
                echo "     {$subKey}: {$subValue}\n";
            }
        } else {
            echo "   {$key}: " . ($value ? 'true' : ($value === false ? 'false' : $value)) . "\n";
        }
    }
    echo "\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "========================================================\n";
echo "✅ PARAMETER BINDING & CONSTRAINTS EXAMPLE COMPLETE!\n";
echo "========================================================\n\n";

echo "The parameter binding system provides:\n\n";

echo "🔹 FLEXIBLE MODEL BINDING\n";
echo "   Automatic model resolution with customizable logic\n\n";

echo "🔹 CUSTOM PARAMETER RESOLVERS\n";
echo "   Transform and validate parameters before injection\n\n";

echo "🔹 COMPREHENSIVE CONSTRAINTS\n";
echo "   Built-in patterns and custom validation rules\n\n";

echo "🔹 DEPENDENCY INJECTION\n";
echo "   Automatic resolution of method dependencies\n\n";

echo "🔹 PERFORMANCE OPTIMIZED\n";
echo "   Efficient parameter resolution and validation\n\n";

echo "🔹 DEVELOPER-FRIENDLY ERRORS\n";
echo "   Clear error messages for debugging\n\n";

echo "Ready for advanced parameter handling! 🚀\n";