<?php

// Simple autoloader for testing
spl_autoload_register(function ($class) {
    $prefix = 'Horizon\\';
    $base_dir = __DIR__ . '/../src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Mock classes for testing
class MockRequest 
{
    private $method;
    private $headers = [];
    private $query = [];
    private $input = [];
    
    public function __construct(string $method = 'GET')
    {
        $this->method = $method;
    }
    
    public function method(): string { return $this->method; }
    public function header(string $key): ?string { return $this->headers[$key] ?? null; }
    public function query(string $key = null, $default = null) { return $key ? ($this->query[$key] ?? $default) : $this->query; }
    public function input(string $key): ?string { return $this->input[$key] ?? null; }
    public function secure(): bool { return false; }
    public function ip(): string { return '127.0.0.1'; }
    public function server(string $key): string { return $key === 'SERVER_NAME' ? 'localhost' : ''; }
    public function user() { return null; }
    public function session() { return new MockSession(); }
    public function fullUrlIs(string $pattern): bool { return false; }
    public function is(string $pattern): bool { return false; }
    public function url(): string { return 'http://localhost/test'; }
    
    public function setHeader(string $key, string $value): self {
        $this->headers[$key] = $value;
        return $this;
    }
    
    public function setQuery(array $query): self {
        $this->query = $query;
        return $this;
    }
    
    public function setInput(array $input): self {
        $this->input = $input;
        return $this;
    }
}

class MockSession
{
    public function token(): string { return 'mock-csrf-token'; }
}

class MockResponse
{
    private $headers = [];
    private $content = '';
    private $status = 200;
    
    public function __construct(string $content = '', int $status = 200)
    {
        $this->content = $content;
        $this->status = $status;
    }
    
    public function header(string $key, string $value): self {
        $this->headers[$key] = $value;
        return $this;
    }
    
    public function withHeaders(array $headers): self {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }
    
    public function cookie(string $name, string $value, array $options = []): self {
        // Mock cookie setting
        return $this;
    }
    
    public function getHeaders(): array { return $this->headers; }
    public function getContent(): string { return $this->content; }
    public function getStatus(): int { return $this->status; }
}

function response($content = '', $status = 200) {
    return new MockResponse($content, $status);
}

function app($key = null) {
    if ($key === 'auth') {
        return new class {
            public function guard() { return new class { public function check() { return false; } }; }
        };
    }
    return null;
}

function config($key) {
    return [];
}

function storage_path($path) {
    return __DIR__ . '/../storage/' . $path;
}

echo "Testing Security Middleware Architecture\n";
echo "=======================================\n\n";

echo "1. Security Middleware Components:\n";
echo "   ------------------------------\n";

// Check if middleware files exist
$middlewareFiles = [
    'CORS Handler' => 'src/Http/Middleware/HandleCors.php',
    'CSRF Protection' => 'src/Http/Middleware/VerifyCsrfToken.php',
    'Rate Limiting' => 'src/Http/Middleware/ThrottleRequests.php',
    'Security Headers' => 'src/Http/Middleware/SecurityHeaders.php',
    'Authentication' => 'src/Http/Middleware/Authenticate.php',
    'Signature Validation' => 'src/Http/Middleware/ValidateSignature.php',
    'Maintenance Mode' => 'src/Http/Middleware/PreventRequestsDuringMaintenance.php',
];

foreach ($middlewareFiles as $name => $file) {
    $exists = file_exists(__DIR__ . '/../' . $file);
    echo "   " . ($exists ? '✓' : '✗') . " $name: " . ($exists ? 'Available' : 'Missing') . "\n";
}

echo "\n2. Security Configuration:\n";
echo "   -------------------------\n";

$configPath = __DIR__ . '/../config/security.php';
if (file_exists($configPath)) {
    $config = include $configPath;
    
    echo "   ✓ Security config loaded\n";
    echo "   • CORS settings: " . count($config['cors']) . " options\n";
    echo "   • Security headers: " . count($config['headers']) . " headers\n";
    echo "   • Rate limiting profiles: " . count($config['rate_limiting']) . " profiles\n";
    echo "   • CSP directives: " . count($config['csp']) . " directives\n";
} else {
    echo "   ✗ Security config file not found\n";
}

echo "\n3. Security Features Overview:\n";
echo "   ----------------------------\n";

$features = [
    'CORS Protection' => [
        'description' => 'Controls cross-origin resource sharing',
        'prevents' => 'Unauthorized cross-origin requests',
        'configurable' => 'Origins, methods, headers, credentials'
    ],
    'CSRF Protection' => [
        'description' => 'Prevents cross-site request forgery',
        'prevents' => 'Unauthorized state-changing operations',
        'configurable' => 'Token names, excluded routes, cookie settings'
    ],
    'Rate Limiting' => [
        'description' => 'Limits request frequency per user/IP',
        'prevents' => 'DoS attacks, API abuse, brute force',
        'configurable' => 'Max attempts, time windows, per-user limits'
    ],
    'Security Headers' => [
        'description' => 'Adds protective HTTP headers',
        'prevents' => 'XSS, clickjacking, MIME sniffing',
        'configurable' => 'CSP, HSTS, frame options, XSS protection'
    ],
    'Authentication' => [
        'description' => 'Verifies user identity',
        'prevents' => 'Unauthorized access to protected resources',
        'configurable' => 'Multiple guards, custom authentication'
    ],
    'Request Signing' => [
        'description' => 'Validates request integrity',
        'prevents' => 'Request tampering, replay attacks',
        'configurable' => 'Signature algorithms, ignored parameters'
    ]
];

foreach ($features as $name => $details) {
    echo "   $name:\n";
    echo "     • {$details['description']}\n";
    echo "     • Prevents: {$details['prevents']}\n";
    echo "     • Configurable: {$details['configurable']}\n\n";
}

echo "4. Implementation Examples:\n";
echo "   ------------------------\n";

echo "   // Apply CORS to API routes\n";
echo "   \$router->middleware('cors')->prefix('api')->group(function () {\n";
echo "       // API routes with CORS protection\n";
echo "   });\n\n";

echo "   // Rate limit authentication endpoints\n";
echo "   \$router->middleware('throttle:5,15')->group(function () {\n";
echo "       \$router->post('/login', 'AuthController@login');\n";
echo "       \$router->post('/register', 'AuthController@register');\n";
echo "   });\n\n";

echo "   // Protect admin routes with authentication + CSRF\n";
echo "   \$router->middleware(['auth', 'csrf'])->prefix('admin')->group(function () {\n";
echo "       // Admin routes\n";
echo "   });\n\n";

echo "5. Security Best Practices:\n";
echo "   -------------------------\n";

$practices = [
    'Always use HTTPS in production',
    'Implement proper CSRF protection for state-changing operations',
    'Set restrictive CORS policies (avoid wildcard in production)',
    'Use rate limiting to prevent abuse',
    'Add security headers for defense in depth',
    'Validate and sanitize all input',
    'Use secure session configuration',
    'Implement proper error handling (don\'t leak information)',
    'Regular security audits and dependency updates',
    'Monitor and log security events'
];

foreach ($practices as $index => $practice) {
    echo "   " . ($index + 1) . ". $practice\n";
}

echo "\n✓ Security Middleware Architecture Review Complete!\n";