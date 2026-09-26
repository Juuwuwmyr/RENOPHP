<?php

declare(strict_types=1);

/**
 * Complete Blog API Example
 * 
 * A comprehensive blog API demonstrating all Horizon Framework features:
 * - RESTful API design with proper HTTP methods
 * - Authentication and authorization middleware
 * - Model binding and parameter validation
 * - File uploads and management
 * - Rate limiting and security headers
 * - Comprehensive error handling
 * - Performance monitoring and optimization
 * - Complete CRUD operations
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/Models/User.php';
require_once __DIR__ . '/Models/Post.php';

echo "Horizon Framework - Complete Blog API Example\n";
echo "=============================================\n\n";

use Horizon\Foundation\Application;
use Horizon\Http\Request;
use Horizon\Http\Response;
use Horizon\Middleware\MiddlewareInterface;
use Horizon\Middleware\CorsMiddleware;
use Horizon\Middleware\SecurityMiddleware;
use Horizon\Middleware\ThrottleMiddleware;
use Examples\Models\User;
use Examples\Models\Post;
use Closure;

try {
    echo "🚀 Initializing Blog API Application\n";
    echo "====================================\n\n";

    // Environment setup
    $_ENV['APP_ENV'] = 'development';
    $_ENV['APP_DEBUG'] = 'true';
    $_ENV['APP_NAME'] = 'Horizon Blog API';

    // Create application
    $app = Application::create(__DIR__ . '/..');

    echo "✅ Application initialized:\n";
    echo "   Environment: {$app->environment()}\n";
    echo "   Debug: " . ($app->isDebug() ? 'enabled' : 'disabled') . "\n\n";

    echo "🔐 Setting Up Authentication System\n";
    echo "===================================\n\n";

    // Simple session storage for demo
    $sessions = [];

    // Authentication middleware
    class AuthMiddleware implements MiddlewareInterface
    {
        private array $sessions;

        public function __construct(array &$sessions)
        {
            $this->sessions = &$sessions;
        }

        public function handle(Request $request, Closure $next): Response
        {
            $token = $request->bearerToken() ?? $request->header('X-API-Token');
            
            if (!$token) {
                return Response::json(['error' => 'Authentication required'], 401);
            }
            
            if (!isset($this->sessions[$token])) {
                return Response::json(['error' => 'Invalid token'], 401);
            }
            
            $user = $this->sessions[$token];
            $request->setAttribute('authenticated_user', $user);
            
            return $next($request);
        }
    }

    // Admin middleware
    class AdminMiddleware implements MiddlewareInterface
    {
        public function handle(Request $request, Closure $next): Response
        {
            $user = $request->getAttribute('authenticated_user');
            
            if (!$user || $user['role'] !== 'admin') {
                return Response::json(['error' => 'Admin access required'], 403);
            }
            
            return $next($request);
        }
    }

    // Request logging middleware
    class LoggingMiddleware implements MiddlewareInterface
    {
        public function handle(Request $request, Closure $next): Response
        {
            $start = microtime(true);
            echo "   📝 [" . date('H:i:s') . "] {$request->method()} {$request->path()}\n";
            
            $response = $next($request);
            
            $duration = round((microtime(true) - $start) * 1000, 2);
            echo "   ✅ [{$response->getStatusCode()}] Completed in {$duration}ms\n";
            
            return $response;
        }
    }

    echo "✅ Authentication system configured\n\n";

    echo "⚙️ Configuring Middleware\n";
    echo "=========================\n\n";

    // Register middleware
    $app->routeMiddleware([
        'auth' => new AuthMiddleware($sessions),
        'admin' => AdminMiddleware::class,
        'log' => LoggingMiddleware::class,
        'cors' => CorsMiddleware::permissive(),
        'security' => SecurityMiddleware::basic(),
        'throttle' => ThrottleMiddleware::perMinute(60),
    ]);
    // Middleware groups
    $app->middlewareGroups([
        'api' => ['log', 'cors', 'security', 'throttle'],
        'protected' => ['api', 'auth'],
        'admin' => ['protected', 'admin'],
    ]);

    echo "✅ Middleware configured:\n";
    echo "   - Authentication and authorization\n";
    echo "   - CORS and security headers\n";
    echo "   - Rate limiting (60 req/min)\n";
    echo "   - Request logging\n\n";

    echo "🔧 Setting Up Model Binding\n";
    echo "============================\n\n";

    // Configure model binding
    $binder = $app->getKernel()->getBinder();
    $binder->model('user', User::class);
    $binder->model('post', Post::class);

    // Custom transformer for IDs
    $binder->transformer('id', function ($value) {
        return (int) $value;
    });

    echo "✅ Model binding configured for User and Post models\n\n";

    echo "🌐 Registering API Routes\n";
    echo "=========================\n\n";

    // API Status endpoint (public)
    $app->get('/api/status', function () {
        return Response::json([
            'service' => 'Horizon Blog API',
            'status' => 'online',
            'version' => '1.0.0',
            'timestamp' => date('Y-m-d H:i:s'),
            'uptime' => time() - $_SERVER['REQUEST_TIME'],
        ]);
    })->name('api.status');

    // Health check (public)
    $app->get('/api/health', function () {
        return Response::json([
            'status' => 'healthy',
            'memory' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            'checks' => [
                'database' => 'ok',
                'cache' => 'ok',
                'storage' => 'ok',
            ]
        ]);
    })->middleware(['api'])->name('api.health');

    echo "✅ Public API endpoints registered\n\n";

    echo "🔐 Authentication Endpoints\n";
    echo "===========================\n\n";
    // Authentication endpoints
    $authGroup = $app->newGroup(['prefix' => 'auth', 'middleware' => ['api']]);

    // Login
    $authGroup->post('/login', function (Request $request) use (&$sessions) {
        $email = $request->input('email');
        $password = $request->input('password');
        
        if (!$email || !$password) {
            return Response::json(['error' => 'Email and password required'], 400);
        }
        
        // Simple demo authentication
        if ($email === 'admin@example.com' && $password === 'secret') {
            $token = 'token_' . uniqid();
            $user = ['id' => 1, 'name' => 'Admin User', 'email' => $email, 'role' => 'admin'];
            $sessions[$token] = $user;
            
            return Response::json([
                'message' => 'Login successful',
                'token' => $token,
                'user' => $user
            ]);
        } elseif ($email === 'user@example.com' && $password === 'secret') {
            $token = 'token_' . uniqid();
            $user = ['id' => 2, 'name' => 'Regular User', 'email' => $email, 'role' => 'user'];
            $sessions[$token] = $user;
            
            return Response::json([
                'message' => 'Login successful',
                'token' => $token,
                'user' => $user
            ]);
        }
        
        return Response::json(['error' => 'Invalid credentials'], 401);
    })->name('auth.login');

    // Logout
    $authGroup->post('/logout', function (Request $request) use (&$sessions) {
        $token = $request->bearerToken() ?? $request->header('X-API-Token');
        
        if ($token && isset($sessions[$token])) {
            unset($sessions[$token]);
        }
        
        return Response::json(['message' => 'Logged out successfully']);
    })->middleware(['auth'])->name('auth.logout');

    // Get profile
    $authGroup->get('/profile', function (Request $request) {
        $user = $request->getAttribute('authenticated_user');
        return Response::json(['user' => $user]);
    })->middleware(['auth'])->name('auth.profile');

    echo "✅ Authentication endpoints:\n";
    echo "   POST /auth/login    - User login\n";
    echo "   POST /auth/logout   - User logout\n";
    echo "   GET  /auth/profile  - Get user profile\n\n";
    echo "📝 Posts Management Endpoints\n";
    echo "=============================\n\n";

    // Posts resource with model binding
    $postsGroup = $app->newGroup(['prefix' => 'posts', 'middleware' => ['api']]);

    // List all posts (public)
    $postsGroup->get('/', function (Request $request) {
        $page = max(1, (int) $request->input('page', 1));
        $limit = min(50, max(1, (int) $request->input('limit', 10)));
        
        $posts = Post::all();
        $total = count($posts);
        $offset = ($page - 1) * $limit;
        $posts = array_slice($posts, $offset, $limit);
        
        return Response::json([
            'posts' => array_map(fn($post) => $post->toArray(), $posts),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => ceil($total / $limit)
            ]
        ]);
    })->name('posts.index');

    // Get single post (public)
    $postsGroup->get('/{post}', function (Request $request, Post $post) {
        return Response::json(['post' => $post->toArray()]);
    })->name('posts.show');

    // Create post (authenticated)
    $postsGroup->post('/', function (Request $request) {
        $user = $request->getAttribute('authenticated_user');
        
        // Validate input
        $title = $request->input('title');
        $content = $request->input('content');
        
        if (!$title || !$content) {
            return Response::json([
                'error' => 'Validation failed',
                'details' => [
                    'title' => $title ? null : 'Title is required',
                    'content' => $content ? null : 'Content is required'
                ]
            ], 422);
        }
        
        // Create post (simulation)
        $postId = count(Post::all()) + 1;
        $postData = [
            'id' => $postId,
            'title' => $title,
            'slug' => strtolower(str_replace(' ', '-', $title)),
            'content' => $content,
            'user_id' => $user['id'],
            'published' => $request->input('published', false),
            'created_at' => date('Y-m-d H:i:s'),
        ];
        
        return Response::json([
            'message' => 'Post created successfully',
            'post' => $postData
        ], 201);
    })->middleware(['auth'])->name('posts.store');
    // Update post (authenticated, owner or admin)
    $postsGroup->put('/{post}', function (Request $request, Post $post) {
        $user = $request->getAttribute('authenticated_user');
        
        // Authorization check
        if ($post->user_id !== $user['id'] && $user['role'] !== 'admin') {
            return Response::json(['error' => 'Forbidden'], 403);
        }
        
        // Update fields
        $updates = $request->only(['title', 'content', 'published']);
        $updatedPost = array_merge($post->toArray(), array_filter($updates));
        $updatedPost['updated_at'] = date('Y-m-d H:i:s');
        
        return Response::json([
            'message' => 'Post updated successfully',
            'post' => $updatedPost
        ]);
    })->middleware(['auth'])->name('posts.update');

    // Delete post (authenticated, owner or admin)
    $postsGroup->delete('/{post}', function (Request $request, Post $post) {
        $user = $request->getAttribute('authenticated_user');
        
        // Authorization check
        if ($post->user_id !== $user['id'] && $user['role'] !== 'admin') {
            return Response::json(['error' => 'Forbidden'], 403);
        }
        
        return Response::json([
            'message' => 'Post deleted successfully',
            'post_id' => $post->id
        ]);
    })->middleware(['auth'])->name('posts.destroy');

    // Upload image for post
    $postsGroup->post('/{post}/upload', function (Request $request, Post $post) {
        $user = $request->getAttribute('authenticated_user');
        
        // Authorization check
        if ($post->user_id !== $user['id'] && $user['role'] !== 'admin') {
            return Response::json(['error' => 'Forbidden'], 403);
        }
        
        if (!$request->hasFile('image')) {
            return Response::json(['error' => 'No image file provided'], 400);
        }
        
        $file = $request->file('image');
        
        if (!$file->isValid()) {
            return Response::json(['error' => 'Invalid file upload'], 400);
        }
        
        // Simulate file storage
        $filename = 'post_' . $post->id . '_' . uniqid() . '.jpg';
        $url = '/uploads/posts/' . $filename;
        
        return Response::json([
            'message' => 'Image uploaded successfully',
            'image' => [
                'filename' => $filename,
                'url' => $url,
                'size' => $file->getSize(),
                'type' => $file->getMimeType()
            ]
        ], 201);
    })->middleware(['auth'])->name('posts.upload');
    echo "✅ Posts endpoints:\n";
    echo "   GET    /posts           - List posts (public)\n";
    echo "   POST   /posts           - Create post (auth)\n";
    echo "   GET    /posts/{id}      - Get post (public)\n";
    echo "   PUT    /posts/{id}      - Update post (auth)\n";
    echo "   DELETE /posts/{id}      - Delete post (auth)\n";
    echo "   POST   /posts/{id}/upload - Upload image (auth)\n\n";

    echo "👥 User Management Endpoints\n";
    echo "============================\n\n";

    // Users endpoints
    $usersGroup = $app->newGroup(['prefix' => 'users', 'middleware' => ['api']]);

    // List users (admin only)
    $usersGroup->get('/', function (Request $request) {
        $users = User::all();
        return Response::json([
            'users' => array_map(function($user) {
                $userData = $user->toArray();
                unset($userData['password']); // Don't expose passwords
                return $userData;
            }, $users)
        ]);
    })->middleware(['admin'])->name('users.index');

    // Get user profile (public)
    $usersGroup->get('/{user}', function (Request $request, User $user) {
        $userData = $user->toArray();
        unset($userData['email']); // Don't expose email publicly
        return Response::json(['user' => $userData]);
    })->name('users.show');

    // Update user (authenticated, self or admin)
    $usersGroup->put('/{user}', function (Request $request, User $user) {
        $authUser = $request->getAttribute('authenticated_user');
        
        // Authorization check
        if ($user->id !== $authUser['id'] && $authUser['role'] !== 'admin') {
            return Response::json(['error' => 'Forbidden'], 403);
        }
        
        // Update allowed fields
        $allowedFields = ['name', 'email'];
        if ($authUser['role'] === 'admin') {
            $allowedFields[] = 'role';
        }
        
        $updates = $request->only($allowedFields);
        $updatedUser = array_merge($user->toArray(), array_filter($updates));
        $updatedUser['updated_at'] = date('Y-m-d H:i:s');
        
        return Response::json([
            'message' => 'User updated successfully',
            'user' => $updatedUser
        ]);
    })->middleware(['auth'])->name('users.update');

    echo "✅ User endpoints:\n";
    echo "   GET /users        - List users (admin)\n";
    echo "   GET /users/{id}   - Get user profile\n";
    echo "   PUT /users/{id}   - Update user (auth)\n\n";
    echo "⚡ Admin Panel Endpoints\n";
    echo "========================\n\n";

    // Admin endpoints
    $adminGroup = $app->newGroup(['prefix' => 'admin', 'middleware' => ['admin']]);

    // Dashboard statistics
    $adminGroup->get('/stats', function (Request $request) {
        return Response::json([
            'statistics' => [
                'total_users' => count(User::all()),
                'total_posts' => count(Post::all()),
                'published_posts' => count(array_filter(Post::all(), fn($p) => $p->published)),
                'draft_posts' => count(array_filter(Post::all(), fn($p) => !$p->published)),
                'active_sessions' => 2, // Simulated
            ],
            'recent_activity' => [
                ['action' => 'User registered', 'timestamp' => date('Y-m-d H:i:s', time() - 300)],
                ['action' => 'Post published', 'timestamp' => date('Y-m-d H:i:s', time() - 600)],
                ['action' => 'User login', 'timestamp' => date('Y-m-d H:i:s', time() - 900)],
            ]
        ]);
    })->name('admin.stats');

    // System logs
    $adminGroup->get('/logs', function (Request $request) {
        $level = $request->input('level', 'all');
        
        $logs = [
            ['level' => 'info', 'message' => 'User authenticated', 'timestamp' => date('Y-m-d H:i:s')],
            ['level' => 'warning', 'message' => 'Rate limit approached', 'timestamp' => date('Y-m-d H:i:s', time() - 120)],
            ['level' => 'error', 'message' => 'Database connection timeout', 'timestamp' => date('Y-m-d H:i:s', time() - 300)],
            ['level' => 'info', 'message' => 'Cache cleared', 'timestamp' => date('Y-m-d H:i:s', time() - 600)],
        ];
        
        if ($level !== 'all') {
            $logs = array_filter($logs, fn($log) => $log['level'] === $level);
        }
        
        return Response::json([
            'logs' => array_values($logs),
            'total' => count($logs),
            'filter' => $level
        ]);
    })->name('admin.logs');

    echo "✅ Admin endpoints:\n";
    echo "   GET /admin/stats  - System statistics\n";
    echo "   GET /admin/logs   - System logs\n\n";

    echo "🔧 Error Handling Configuration\n";
    echo "===============================\n\n";

    // Custom exception handlers
    $kernel = $app->getKernel();

    $kernel->registerExceptionHandler(\InvalidArgumentException::class, 
        function ($exception, $request) {
            return Response::json([
                'error' => 'Invalid Request',
                'message' => $exception->getMessage(),
                'type' => 'validation_error'
            ], 400);
        }
    );

    $kernel->registerExceptionHandler(\RuntimeException::class, 
        function ($exception, $request) {
            return Response::json([
                'error' => 'Internal Server Error',
                'message' => $request->getAttribute('app_debug') ? $exception->getMessage() : 'Something went wrong',
                'type' => 'runtime_error'
            ], 500);
        }
    );

    echo "✅ Exception handlers registered\n\n";
    echo "🧪 API Testing Scenarios\n";
    echo "=========================\n\n";

    // Test various API endpoints
    $testScenarios = [
        // Public endpoints
        ['GET', '/api/status', [], [], 'API status check'],
        ['GET', '/api/health', [], [], 'Health check'],
        ['GET', '/posts', [], [], 'List posts (public)'],
        ['GET', '/posts/1', [], [], 'Get specific post'],
        ['GET', '/users/1', [], [], 'Get user profile'],
        
        // Authentication
        ['POST', '/auth/login', ['email' => 'admin@example.com', 'password' => 'secret'], [], 'Admin login'],
        ['POST', '/auth/login', ['email' => 'user@example.com', 'password' => 'secret'], [], 'User login'],
        ['POST', '/auth/login', ['email' => 'wrong@example.com', 'password' => 'wrong'], [], 'Invalid login'],
        
        // Protected endpoints (will need token)
        ['GET', '/auth/profile', [], ['Authorization' => 'Bearer token_will_be_set'], 'Get profile (needs auth)'],
        ['POST', '/posts', ['title' => 'New Post', 'content' => 'Great content'], ['Authorization' => 'Bearer token_will_be_set'], 'Create post (needs auth)'],
        ['GET', '/admin/stats', [], ['Authorization' => 'Bearer admin_token'], 'Admin stats (needs admin)'],
    ];

    $authenticatedToken = null;
    $adminToken = null;
    
    echo "Running API test scenarios:\n\n";
    
    foreach ($testScenarios as $i => [$method, $uri, $data, $headers, $description]) {
        echo "📋 Test " . ($i + 1) . ": {$description}\n";
        echo "   {$method} {$uri}\n";
        
        try {
            // Set up headers
            $serverVars = [];
            foreach ($headers as $name => $value) {
                $serverVars['HTTP_' . strtoupper(str_replace('-', '_', $name))] = $value;
            }
            
            // Create request
            if (in_array($method, ['POST', 'PUT', 'PATCH']) && !empty($data)) {
                $content = json_encode($data);
                $serverVars['HTTP_CONTENT_TYPE'] = 'application/json';
                $request = Request::create($uri, $method, [], [], [], $serverVars, $content);
            } else {
                $request = Request::create($uri, $method, $data, [], [], $serverVars);
            }
            
            // Process request
            $response = $app->handle($request);
            
            echo "   Status: {$response->getStatusCode()}\n";
            
            // Parse response for tokens
            $responseData = json_decode($response->getContent(), true);
            if (isset($responseData['token'])) {
                if (isset($responseData['user']['role']) && $responseData['user']['role'] === 'admin') {
                    $adminToken = $responseData['token'];
                    echo "   🔑 Admin token captured: {$adminToken}\n";
                } else {
                    $authenticatedToken = $responseData['token'];
                    echo "   🔑 User token captured: {$authenticatedToken}\n";
                }
            }
            
            // Show response preview
            $content = $response->getContent();
            if (strlen($content) > 100) {
                $content = substr($content, 0, 100) . '...';
            }
            echo "   Response: {$content}\n";
            
        } catch (Exception $e) {
            echo "   ❌ Error: {$e->getMessage()}\n";
        }
        
        echo "\n";
    }
    echo "🔄 Testing Authenticated Endpoints\n";
    echo "===================================\n\n";

    if ($authenticatedToken) {
        echo "Testing with user token: {$authenticatedToken}\n\n";
        
        // Test authenticated endpoints
        $authTests = [
            ['GET', '/auth/profile'],
            ['POST', '/posts', ['title' => 'Authenticated Post', 'content' => 'This post was created by authenticated user']],
            ['PUT', '/users/2', ['name' => 'Updated Name']],
        ];
        
        foreach ($authTests as [$method, $uri, $data = []]) {
            echo "🔐 Testing authenticated: {$method} {$uri}\n";
            
            try {
                $serverVars = ['HTTP_AUTHORIZATION' => "Bearer {$authenticatedToken}"];
                
                if ($data) {
                    $content = json_encode($data);
                    $serverVars['HTTP_CONTENT_TYPE'] = 'application/json';
                    $request = Request::create($uri, $method, [], [], [], $serverVars, $content);
                } else {
                    $request = Request::create($uri, $method, [], [], [], $serverVars);
                }
                
                $response = $app->handle($request);
                echo "   ✅ Status: {$response->getStatusCode()}\n";
                
            } catch (Exception $e) {
                echo "   ❌ Error: {$e->getMessage()}\n";
            }
            
            echo "\n";
        }
    }

    if ($adminToken) {
        echo "Testing with admin token: {$adminToken}\n\n";
        
        // Test admin endpoints
        $adminTests = [
            ['GET', '/admin/stats'],
            ['GET', '/admin/logs'],
            ['GET', '/users'],
        ];
        
        foreach ($adminTests as [$method, $uri]) {
            echo "👑 Testing admin: {$method} {$uri}\n";
            
            try {
                $serverVars = ['HTTP_AUTHORIZATION' => "Bearer {$adminToken}"];
                $request = Request::create($uri, $method, [], [], [], $serverVars);
                $response = $app->handle($request);
                echo "   ✅ Status: {$response->getStatusCode()}\n";
                
            } catch (Exception $e) {
                echo "   ❌ Error: {$e->getMessage()}\n";
            }
            
            echo "\n";
        }
    }

    echo "📊 Performance and Statistics\n";
    echo "=============================\n\n";

    // Get kernel metrics
    $metrics = $kernel->getMetrics();
    if (!empty($metrics)) {
        echo "🎯 Request Processing Metrics:\n";
        echo "   Requests processed: " . count($metrics) . "\n";
        
        if (count($metrics) > 0) {
            $avgMemory = array_sum(array_column($metrics, 'memory_usage')) / count($metrics);
            echo "   Average memory usage: " . round($avgMemory / 1024 / 1024, 2) . " MB\n";
        }
        echo "\n";
    }

    // Application statistics
    $appStats = $app->getStats();
    echo "📈 Application Statistics:\n";
    echo "   Environment: {$appStats['environment']}\n";
    echo "   Debug mode: " . ($appStats['debug'] ? 'enabled' : 'disabled') . "\n";
    echo "   Routes registered: " . ($appStats['router_stats']['total_routes'] ?? 0) . "\n";
    echo "   Middleware groups: " . count($app->getKernel()->getMiddleware()->getGroups()) . "\n\n";
    echo "📋 API Documentation Summary\n";
    echo "=============================\n\n";

    echo "🔗 Available Endpoints:\n\n";

    $endpoints = [
        'Public Endpoints:',
        '  GET  /api/status                   - API status and version',
        '  GET  /api/health                   - Health check',
        '  GET  /posts                        - List all posts (paginated)',
        '  GET  /posts/{id}                   - Get specific post',
        '  GET  /users/{id}                   - Get user profile',
        '',
        'Authentication:',
        '  POST /auth/login                   - User login (returns token)',
        '  POST /auth/logout                  - User logout',
        '  GET  /auth/profile                 - Get authenticated user profile',
        '',
        'Posts Management (Authenticated):',
        '  POST   /posts                      - Create new post',
        '  PUT    /posts/{id}                 - Update post (owner/admin)',
        '  DELETE /posts/{id}                 - Delete post (owner/admin)',
        '  POST   /posts/{id}/upload          - Upload image for post',
        '',
        'User Management:',
        '  GET  /users                        - List all users (admin only)',
        '  PUT  /users/{id}                   - Update user (self/admin)',
        '',
        'Admin Panel (Admin Only):',
        '  GET  /admin/stats                  - System statistics',
        '  GET  /admin/logs                   - System logs',
    ];

    foreach ($endpoints as $endpoint) {
        echo "   {$endpoint}\n";
    }
    echo "\n";

    echo "🔐 Authentication:\n";
    echo "   - Include 'Authorization: Bearer {token}' header\n";
    echo "   - Or use 'X-API-Token: {token}' header\n";
    echo "   - Demo credentials:\n";
    echo "     Admin: admin@example.com / secret\n";
    echo "     User:  user@example.com / secret\n\n";

    echo "📨 Sample Requests:\n\n";

    $samples = [
        "# Get API status",
        "curl -X GET http://localhost:8000/api/status",
        "",
        "# Login as admin", 
        "curl -X POST http://localhost:8000/auth/login \\",
        "  -H 'Content-Type: application/json' \\",
        "  -d '{\"email\":\"admin@example.com\",\"password\":\"secret\"}'",
        "",
        "# Create a post (with token)",
        "curl -X POST http://localhost:8000/posts \\",
        "  -H 'Authorization: Bearer YOUR_TOKEN' \\",
        "  -H 'Content-Type: application/json' \\",
        "  -d '{\"title\":\"My Post\",\"content\":\"Great content!\"}'",
        "",
        "# Get posts with pagination",
        "curl -X GET 'http://localhost:8000/posts?page=1&limit=5'",
    ];

    foreach ($samples as $sample) {
        echo "   {$sample}\n";
    }
    echo "\n";
} catch (Exception $e) {
    echo "❌ FATAL ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    if ($app && $app->isDebug()) {
        echo "Trace: " . $e->getTraceAsString() . "\n";
    }
}

echo "========================================================\n";
echo "✅ COMPLETE BLOG API EXAMPLE FINISHED!\n";
echo "========================================================\n\n";

echo "This example demonstrated:\n\n";

echo "🔹 COMPLETE RESTful API DESIGN\n";
echo "   Full CRUD operations with proper HTTP methods\n\n";

echo "🔹 AUTHENTICATION & AUTHORIZATION\n";
echo "   Token-based auth with role-based access control\n\n";

echo "🔹 MODEL BINDING & VALIDATION\n";
echo "   Automatic model injection and input validation\n\n";

echo "🔹 MIDDLEWARE INTEGRATION\n";
echo "   Security, logging, CORS, and rate limiting\n\n";

echo "🔹 FILE UPLOAD HANDLING\n";
echo "   Secure file uploads with validation\n\n";

echo "🔹 ERROR HANDLING & LOGGING\n";
echo "   Comprehensive error responses and monitoring\n\n";

echo "🔹 PERFORMANCE MONITORING\n";
echo "   Request metrics and application statistics\n\n";

echo "🔹 PRODUCTION-READY FEATURES\n";
echo "   Security headers, rate limiting, and proper responses\n\n";

// If running as web server, return the application
if (php_sapi_name() !== 'cli') {
    return $app;
}

echo "To run as a web server:\n";
echo "  php -S localhost:8000 examples/complete_blog_api.php\n\n";
echo "Then test with curl or your API client! 🚀\n";