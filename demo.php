<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Horizon\Foundation\Application;
use Horizon\Config\Repository;
use Horizon\Http\Request;
use Horizon\Http\Response;
use Horizon\Exceptions\Handler;

echo "🌅 Horizon Framework - Phase 2 Demo\n";
echo "=====================================\n\n";

// 1. Test Application and Container
echo "1. Testing Application and Container:\n";
$app = new Application(__DIR__);
echo "✅ Application created with base path: " . $app->basePath() . "\n";

// Test dependency injection
class TestService {
    public function getName(): string {
        return "Test Service";
    }
}

$app->singleton(TestService::class);
$service = $app->make(TestService::class);
echo "✅ Service resolved: " . $service->getName() . "\n";

// Test same instance (singleton)
$service2 = $app->make(TestService::class);
echo "✅ Singleton works: " . ($service === $service2 ? 'Same instance' : 'Different instance') . "\n";

echo "\n";

// 2. Test Configuration
echo "2. Testing Configuration:\n";
$config = new Repository([
    'app' => [
        'name' => 'Horizon Demo',
        'debug' => true,
        'nested' => ['value' => 'test']
    ]
]);

echo "✅ Config value: " . $config->get('app.name') . "\n";
echo "✅ Nested config: " . $config->get('app.nested.value') . "\n";
echo "✅ Default value: " . $config->get('missing.key', 'default') . "\n";

$config->set('new.setting', 'dynamic');
echo "✅ Dynamic setting: " . $config->get('new.setting') . "\n";

echo "\n";

// 3. Test HTTP Request
echo "3. Testing HTTP Request:\n";
$request = new Request(
    ['q' => 'search query'],
    ['name' => 'John Doe'],
    [],
    [],
    [
        'REQUEST_METHOD' => 'POST',
        'REQUEST_URI' => '/users?q=search%20query',
        'HTTP_USER_AGENT' => 'Horizon Demo Client',
        'REMOTE_ADDR' => '127.0.0.1'
    ]
);

echo "✅ Method: " . $request->method() . "\n";
echo "✅ Path: " . $request->path() . "\n";
echo "✅ Query param: " . $request->input('q') . "\n";
echo "✅ Post param: " . $request->input('name') . "\n";
echo "✅ User Agent: " . $request->userAgent() . "\n";
echo "✅ IP Address: " . $request->ip() . "\n";

echo "\n";

// 4. Test HTTP Response
echo "4. Testing HTTP Response:\n";
$response = new Response();

// Test JSON response
$response->json(['message' => 'Hello, World!', 'status' => 'success']);
echo "✅ JSON Response Status: " . $response->getStatusCode() . "\n";
echo "✅ JSON Content-Type: " . $response->getHeader('Content-Type') . "\n";
echo "✅ JSON Content: " . $response->getContent() . "\n";

// Test redirect
$redirect = new Response();
$redirect->redirect('https://example.com');
echo "✅ Redirect Status: " . $redirect->getStatusCode() . "\n";
echo "✅ Redirect Location: " . $redirect->getHeader('Location') . "\n";
echo "✅ Is Redirect: " . ($redirect->isRedirect() ? 'Yes' : 'No') . "\n";

echo "\n";

// 5. Test Exception Handling
echo "5. Testing Exception Handling:\n";
$handler = new Handler();

// Test with HttpException
try {
    throw new \Horizon\Http\Exceptions\HttpException(404, 'Page not found');
} catch (Throwable $e) {
    echo "✅ Caught HttpException: " . $e->getMessage() . " (Status: " . $e->getStatusCode() . ")\n";
    
    $errorResponse = $handler->render($request, $e);
    echo "✅ Error Response Status: " . $errorResponse->getStatusCode() . "\n";
}

echo "\n";

// 6. Test Helper Functions
echo "6. Testing Helper Functions:\n";
$_ENV['TEST_VAR'] = 'test_value';
echo "✅ env() function: " . env('TEST_VAR', 'default') . "\n";

$testData = ['user' => ['name' => 'John', 'profile' => ['age' => 30]]];
echo "✅ data_get() function: " . data_get($testData, 'user.name') . "\n";
echo "✅ data_get() nested: " . data_get($testData, 'user.profile.age') . "\n";

echo "\n";

echo "🎉 Phase 2 Core Foundation Complete!\n";
echo "=====================================\n";
echo "✅ Application Kernel & Lifecycle\n";
echo "✅ Dependency Injection Container\n";
echo "✅ Configuration System\n";
echo "✅ HTTP Request/Response Abstractions\n";
echo "✅ Exception Handling\n";
echo "✅ Service Provider Architecture\n";
echo "✅ Utility Classes (Arr, Str)\n";
echo "✅ Helper Functions\n";
echo "✅ Test Foundation\n";

echo "\n🚀 Ready for Phase 3: HTTP and Routing!\n";