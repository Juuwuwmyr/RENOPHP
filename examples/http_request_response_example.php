<?php

declare(strict_types=1);

/**
 * HTTP Request and Response Example
 * 
 * Demonstrates the Horizon Framework's HTTP Request and Response classes
 * with comprehensive parameter handling, file uploads, headers, cookies,
 * and content negotiation.
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "Horizon Framework - HTTP Request and Response Example\n";
echo "====================================================\n\n";

use Horizon\Http\Request;
use Horizon\Http\Response;
use Horizon\Http\UploadedFile;
use Horizon\Http\Cookie;

try {
    echo "1. HTTP REQUEST HANDLING\n";
    echo "========================\n\n";

    echo "🌐 Creating Request from Globals:\n";
    
    // Simulate $_GET, $_POST, $_SERVER data
    $_GET = ['page' => '1', 'sort' => 'name'];
    $_POST = ['name' => 'John Doe', 'email' => 'john@example.com'];
    $_SERVER = [
        'REQUEST_METHOD' => 'POST',
        'REQUEST_URI' => '/users?page=1&sort=name',
        'HTTP_HOST' => 'example.com',
        'HTTP_USER_AGENT' => 'Mozilla/5.0 (Compatible Browser)',
        'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'HTTP_CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        'REMOTE_ADDR' => '192.168.1.100',
        'SERVER_PORT' => '80',
    ];
    $_COOKIE = ['session_id' => 'abc123', 'preferences' => 'theme=dark'];
    
    $request = Request::createFromGlobals();
    
    echo "  Method: " . $request->method() . "\n";
    echo "  Path: " . $request->path() . "\n";
    echo "  Full URL: " . $request->fullUrl() . "\n";
    echo "  IP Address: " . $request->ip() . "\n";
    echo "  User Agent: " . $request->userAgent() . "\n\n";

    echo "📝 Parameter Access:\n";
    echo "  Query 'page': " . $request->query('page') . "\n";
    echo "  Post 'name': " . $request->post('name') . "\n";
    echo "  Input 'email': " . $request->input('email') . "\n";
    echo "  Cookie 'session_id': " . $request->cookie('session_id') . "\n\n";

    echo "🔍 Parameter Validation:\n";
    echo "  Has 'name': " . ($request->has('name') ? 'Yes' : 'No') . "\n";
    echo "  Has 'phone': " . ($request->has('phone') ? 'Yes' : 'No') . "\n";
    echo "  Filled 'name': " . ($request->filled('name') ? 'Yes' : 'No') . "\n";
    echo "  Has any of ['name', 'email']: " . ($request->hasAny(['name', 'email']) ? 'Yes' : 'No') . "\n\n";

    echo "📋 Input Filtering:\n";
    $only = $request->only(['name', 'email']);
    echo "  Only name,email: " . json_encode($only) . "\n";
    
    $except = $request->except(['page']);
    echo "  Except page: " . json_encode($except) . "\n\n";

    echo "2. HTTP METHOD DETECTION\n";
    echo "========================\n\n";

    echo "🔧 Method Checks:\n";
    echo "  Is GET: " . ($request->isGet() ? 'Yes' : 'No') . "\n";
    echo "  Is POST: " . ($request->isPost() ? 'Yes' : 'No') . "\n";
    echo "  Is PUT: " . ($request->isPut() ? 'Yes' : 'No') . "\n";
    echo "  Is method 'POST': " . ($request->isMethod('POST') ? 'Yes' : 'No') . "\n\n";

    echo "📡 Request Type Detection:\n";
    echo "  Is JSON: " . ($request->isJson() ? 'Yes' : 'No') . "\n";
    echo "  Is XML: " . ($request->isXml() ? 'Yes' : 'No') . "\n";
    echo "  Expects JSON: " . ($request->expectsJson() ? 'Yes' : 'No') . "\n";
    echo "  Wants JSON: " . ($request->wantsJson() ? 'Yes' : 'No') . "\n\n";

    echo "3. JSON REQUEST HANDLING\n";
    echo "========================\n\n";

    // Create JSON request
    $jsonRequest = Request::create(
        '/api/users',
        'POST',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json'],
        json_encode(['name' => 'Jane Smith', 'role' => 'admin'])
    );

    echo "📦 JSON Request:\n";
    echo "  Content Type: " . $jsonRequest->header('Content-Type') . "\n";
    echo "  Is JSON: " . ($jsonRequest->isJson() ? 'Yes' : 'No') . "\n";
    echo "  JSON name: " . $jsonRequest->json('name') . "\n";
    echo "  JSON role: " . $jsonRequest->json('role') . "\n";
    echo "  All JSON: " . json_encode($jsonRequest->json()) . "\n\n";

    echo "4. FILE UPLOAD HANDLING\n";
    echo "=======================\n\n";

    // Simulate file upload
    $uploadedFile = new UploadedFile(
        __FILE__, // Use this file as example
        'test-file.php',
        'text/php',
        UPLOAD_ERR_OK,
        filesize(__FILE__)
    );

    echo "📎 File Upload Details:\n";
    echo "  Original Name: " . $uploadedFile->getClientOriginalName() . "\n";
    echo "  Extension: " . $uploadedFile->getClientOriginalExtension() . "\n";
    echo "  MIME Type: " . $uploadedFile->getMimeType() . "\n";
    echo "  Size: " . $uploadedFile->getSize() . " bytes\n";
    echo "  Is Valid: " . ($uploadedFile->isValid() ? 'Yes' : 'No') . "\n";
    echo "  Is Image: " . ($uploadedFile->isImage() ? 'Yes' : 'No') . "\n";
    echo "  Is Safe: " . ($uploadedFile->isSafe() ? 'Yes' : 'No') . "\n\n";

    echo "🔒 File Security Validation:\n";
    $allowedTypes = ['text/plain', 'text/php', 'application/pdf'];
    echo "  Valid MIME type: " . ($uploadedFile->validateMimeType($allowedTypes) ? 'Yes' : 'No') . "\n";
    
    $allowedExts = ['php', 'txt', 'pdf'];
    echo "  Valid extension: " . ($uploadedFile->validateExtension($allowedExts) ? 'Yes' : 'No') . "\n";
    
    echo "  Size under 1MB: " . ($uploadedFile->validateSize(1024 * 1024) ? 'Yes' : 'No') . "\n\n";

    echo "5. HEADER OPERATIONS\n";
    echo "====================\n\n";

    echo "📋 Request Headers:\n";
    echo "  Host: " . $request->header('Host') . "\n";
    echo "  User-Agent: " . $request->header('User-Agent') . "\n";
    echo "  Accept: " . $request->header('Accept') . "\n";
    echo "  Has Authorization: " . ($request->hasHeader('Authorization') ? 'Yes' : 'No') . "\n\n";

    // Create request with Bearer token
    $authRequest = Request::create('/api/profile', 'GET', [], [], [], [
        'HTTP_AUTHORIZATION' => 'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9'
    ]);

    echo "🔐 Bearer Token:\n";
    echo "  Authorization Header: " . $authRequest->header('Authorization') . "\n";
    echo "  Bearer Token: " . $authRequest->bearerToken() . "\n\n";

    echo "6. HTTP RESPONSE CREATION\n";
    echo "=========================\n\n";

    echo "📄 Basic Response:\n";
    $basicResponse = new Response('Hello, World!', 200);
    echo "  Content: " . $basicResponse->getContent() . "\n";
    echo "  Status: " . $basicResponse->getStatusCode() . "\n";
    echo "  Status Text: " . $basicResponse->getStatusText() . "\n";
    echo "  Is Successful: " . ($basicResponse->isSuccessful() ? 'Yes' : 'No') . "\n\n";

    echo "🔄 JSON Response:\n";
    $jsonResponse = Response::json([
        'message' => 'Success',
        'data' => ['id' => 1, 'name' => 'John Doe'],
        'meta' => ['total' => 1, 'page' => 1]
    ]);
    
    echo "  Content-Type: " . $jsonResponse->getHeader('Content-Type') . "\n";
    echo "  Content: " . substr($jsonResponse->getContent(), 0, 100) . "...\n\n";

    echo "🏠 HTML Response:\n";
    $htmlResponse = Response::view('users/index', ['users' => ['John', 'Jane']])
        ->asHtml();
    
    echo "  Content-Type: " . $htmlResponse->getHeader('Content-Type') . "\n";
    echo "  Content: " . substr($htmlResponse->getContent(), 0, 50) . "...\n\n";

    echo "7. RESPONSE REDIRECTS\n";
    echo "=====================\n\n";

    echo "🔀 Redirect Response:\n";
    $redirectResponse = Response::redirect('/dashboard', 302);
    echo "  Status: " . $redirectResponse->getStatusCode() . "\n";
    echo "  Location: " . $redirectResponse->getHeader('Location') . "\n";
    echo "  Is Redirection: " . ($redirectResponse->isRedirection() ? 'Yes' : 'No') . "\n\n";

    echo "8. RESPONSE HEADERS\n";
    echo "===================\n\n";

    echo "🛡️  Security Headers:\n";
    $secureResponse = (new Response('Secure Content'))
        ->withSecurityHeaders();
    
    echo "  X-Content-Type-Options: " . $secureResponse->getHeader('X-Content-Type-Options') . "\n";
    echo "  X-Frame-Options: " . $secureResponse->getHeader('X-Frame-Options') . "\n";
    echo "  X-XSS-Protection: " . $secureResponse->getHeader('X-XSS-Protection') . "\n\n";

    echo "🌐 CORS Headers:\n";
    $corsResponse = (new Response('API Response'))
        ->withCors(['https://example.com'], ['GET', 'POST'], ['Content-Type', 'Authorization']);
    
    echo "  Access-Control-Allow-Origin: " . $corsResponse->getHeader('Access-Control-Allow-Origin') . "\n";
    echo "  Access-Control-Allow-Methods: " . $corsResponse->getHeader('Access-Control-Allow-Methods') . "\n";
    echo "  Access-Control-Allow-Headers: " . $corsResponse->getHeader('Access-Control-Allow-Headers') . "\n\n";

    echo "9. COOKIE MANAGEMENT\n";
    echo "====================\n\n";

    echo "🍪 Response Cookies:\n";
    $cookieResponse = (new Response('Cookie Demo'))
        ->cookie('user_preference', 'dark_theme', time() + 3600)
        ->sessionCookie('temp_data', 'value123')
        ->persistentCookie('remember_me', 'true', 10080); // 1 week

    $cookies = $cookieResponse->getCookies();
    echo "  Set " . count($cookies) . " cookies:\n";
    
    foreach ($cookies as $cookie) {
        echo "    - {$cookie->getName()}: {$cookie->getValue()}\n";
        echo "      Expires: " . ($cookie->isSession() ? 'Session' : date('Y-m-d H:i:s', $cookie->getExpiresTime())) . "\n";
        echo "      Secure: " . ($cookie->isSecure() ? 'Yes' : 'No') . "\n";
        echo "      HttpOnly: " . ($cookie->isHttpOnly() ? 'Yes' : 'No') . "\n";
        echo "      SameSite: " . $cookie->getSameSite() . "\n";
    }
    echo "\n";

    echo "10. ADVANCED COOKIE FEATURES\n";
    echo "============================\n\n";

    echo "🔧 Cookie Manipulation:\n";
    $advancedCookie = Cookie::make('session', 'abc123', 60)
        ->withSecure(true)
        ->withHttpOnly(true)
        ->withSameSite('Strict')
        ->withPath('/admin')
        ->withDomain('.example.com');

    echo "  Cookie String: " . $advancedCookie->toHeaderString() . "\n";
    echo "  Is Session: " . ($advancedCookie->isSession() ? 'Yes' : 'No') . "\n";
    echo "  Is Persistent: " . ($advancedCookie->isPersistent() ? 'Yes' : 'No') . "\n";
    echo "  Is Expired: " . ($advancedCookie->isExpired() ? 'Yes' : 'No') . "\n\n";

    echo "🗑️  Cookie Deletion:\n";
    $deleteCookie = Cookie::forget('old_session');
    echo "  Forget Cookie: " . $deleteCookie->toHeaderString() . "\n";
    echo "  Is Cleared: " . ($deleteCookie->isCleared() ? 'Yes' : 'No') . "\n\n";

    echo "11. CONTENT NEGOTIATION\n";
    echo "=======================\n\n";

    // Create request with various Accept headers
    $apiRequest = Request::create('/api/data', 'GET', [], [], [], [
        'HTTP_ACCEPT' => 'application/json, application/xml;q=0.8, text/html;q=0.9'
    ]);

    echo "📡 Accept Header Parsing:\n";
    echo "  Accept Header: " . $apiRequest->header('Accept') . "\n";
    echo "  Acceptable Types: " . json_encode($apiRequest->getAcceptableContentTypes()) . "\n";
    echo "  Accepts JSON: " . ($apiRequest->accepts('application/json') ? 'Yes' : 'No') . "\n";
    echo "  Accepts XML: " . ($apiRequest->accepts('application/xml') ? 'Yes' : 'No') . "\n";
    echo "  Accepts HTML: " . ($apiRequest->accepts('text/html') ? 'Yes' : 'No') . "\n\n";

    echo "12. PATH MATCHING\n";
    echo "=================\n\n";

    $pathRequest = Request::create('/admin/users/123/edit', 'GET');
    echo "📍 Path Matching:\n";
    echo "  Path: " . $pathRequest->path() . "\n";
    echo "  Matches 'admin/*': " . ($pathRequest->is('admin/*') ? 'Yes' : 'No') . "\n";
    echo "  Matches 'api/*': " . ($pathRequest->is('api/*') ? 'Yes' : 'No') . "\n";
    echo "  Matches 'admin/users/*': " . ($pathRequest->is('admin/users/*') ? 'Yes' : 'No') . "\n\n";

    echo "13. REQUEST FACTORY METHODS\n";
    echo "===========================\n\n";

    echo "🏭 Custom Request Creation:\n";
    $customRequest = Request::create(
        'https://api.example.com/v1/users?active=true',
        'POST',
        ['name' => 'New User', 'email' => 'new@example.com'],
        ['session' => 'xyz789'],
        [],
        ['HTTP_AUTHORIZATION' => 'Bearer token123']
    );

    echo "  URL: " . $customRequest->fullUrl() . "\n";
    echo "  Method: " . $customRequest->method() . "\n";
    echo "  Is Secure: " . ($customRequest->secure() ? 'Yes' : 'No') . "\n";
    echo "  Query 'active': " . $customRequest->query('active') . "\n";
    echo "  Post 'name': " . $customRequest->post('name') . "\n\n";

    echo "14. RESPONSE STATUS HELPERS\n";
    echo "===========================\n\n";

    echo "📊 Various Response Statuses:\n";
    
    $responses = [
        'OK' => new Response('Success', 200),
        'Created' => new Response('Resource created', 201),
        'Not Found' => new Response('Page not found', 404),
        'Server Error' => new Response('Internal error', 500),
        'Forbidden' => new Response('Access denied', 403),
    ];

    foreach ($responses as $name => $response) {
        echo "  {$name} ({$response->getStatusCode()}):\n";
        echo "    Is Successful: " . ($response->isSuccessful() ? 'Yes' : 'No') . "\n";
        echo "    Is Client Error: " . ($response->isClientError() ? 'Yes' : 'No') . "\n";
        echo "    Is Server Error: " . ($response->isServerError() ? 'Yes' : 'No') . "\n";
    }
    echo "\n";

    echo "15. RESPONSE BUILDING CHAIN\n";
    echo "===========================\n\n";

    echo "⛓️  Fluent Response Building:\n";
    $chainedResponse = Response::json(['success' => true])
        ->setStatusCode(201)
        ->header('X-API-Version', '1.0')
        ->header('X-Rate-Limit', '1000')
        ->cookie('api_session', 'active', time() + 7200)
        ->withSecurityHeaders();

    echo "  Status: " . $chainedResponse->getStatusCode() . "\n";
    echo "  Content-Type: " . $chainedResponse->getHeader('Content-Type') . "\n";
    echo "  X-API-Version: " . $chainedResponse->getHeader('X-API-Version') . "\n";
    echo "  Cookies Set: " . count($chainedResponse->getCookies()) . "\n";
    echo "  Has Security Headers: " . ($chainedResponse->hasHeader('X-Content-Type-Options') ? 'Yes' : 'No') . "\n\n";

    echo "16. PRACTICAL USE CASES\n";
    echo "=======================\n\n";

    echo "🔄 API Endpoint Simulation:\n";
    
    // Simulate API endpoint handling
    function handleApiRequest(Request $request): Response
    {
        // Validate API key
        $apiKey = $request->bearerToken() ?: $request->header('X-API-Key');
        if (!$apiKey) {
            return Response::json(['error' => 'API key required'], 401);
        }

        // Handle different methods
        switch ($request->method()) {
            case 'GET':
                return Response::json([
                    'method' => 'GET',
                    'path' => $request->path(),
                    'query' => $request->query(),
                ]);

            case 'POST':
                $data = $request->isJson() ? $request->json() : $request->post();
                return Response::json([
                    'method' => 'POST',
                    'data' => $data,
                    'created' => true
                ], 201);

            default:
                return Response::json(['error' => 'Method not allowed'], 405);
        }
    }

    // Test API requests
    $apiGetRequest = Request::create('/api/users', 'GET', ['limit' => '10'], [], [], [
        'HTTP_AUTHORIZATION' => 'Bearer valid-token'
    ]);

    $apiPostRequest = Request::create('/api/users', 'POST', [], [], [], [
        'HTTP_AUTHORIZATION' => 'Bearer valid-token',
        'CONTENT_TYPE' => 'application/json'
    ], json_encode(['name' => 'John', 'email' => 'john@example.com']));

    echo "  GET Request Response:\n";
    $getResponse = handleApiRequest($apiGetRequest);
    echo "    Status: " . $getResponse->getStatusCode() . "\n";
    echo "    Content: " . substr($getResponse->getContent(), 0, 100) . "...\n\n";

    echo "  POST Request Response:\n";
    $postResponse = handleApiRequest($apiPostRequest);
    echo "    Status: " . $postResponse->getStatusCode() . "\n";
    echo "    Content: " . substr($postResponse->getContent(), 0, 100) . "...\n\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "========================================================\n";
echo "✅ HTTP REQUEST AND RESPONSE EXAMPLE COMPLETE!\n";
echo "========================================================\n\n";

echo "The HTTP layer provides:\n\n";

echo "🔹 COMPREHENSIVE REQUEST HANDLING\n";
echo "   Parameter access, validation, and filtering\n\n";

echo "🔹 SECURE FILE UPLOADS\n";
echo "   Validation, security checks, and easy storage\n\n";

echo "🔹 FLEXIBLE RESPONSE BUILDING\n";
echo "   JSON, HTML, redirects, and downloads\n\n";

echo "🔹 ADVANCED COOKIE MANAGEMENT\n";
echo "   Secure defaults and comprehensive options\n\n";

echo "🔹 CONTENT NEGOTIATION\n";
echo "   Accept header parsing and type detection\n\n";

echo "Ready for robust web application development! 🚀\n";