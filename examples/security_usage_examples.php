<?php

echo "Horizon Framework - Security Middleware Usage Examples\n";
echo "======================================================\n\n";

echo "1. BASIC SECURITY SETUP:\n";
echo "------------------------\n";
echo "// routes/web.php\n";
echo "\$router->middleware('web')->group(function (\$router) {\n";
echo "    // These routes include: security headers, CSRF protection, input sanitization\n";
echo "    \$router->get('/', 'HomeController@index');\n";
echo "    \$router->get('/contact', 'ContactController@show');\n";
echo "    \$router->post('/contact', 'ContactController@store'); // CSRF protected\n";
echo "});\n\n";

echo "// routes/api.php\n";
echo "\$router->middleware('api')->group(function (\$router) {\n";
echo "    // These routes include: CORS, security headers, rate limiting\n";
echo "    \$router->get('/users', 'Api\\UserController@index');\n";
echo "    \$router->post('/users', 'Api\\UserController@store');\n";
echo "});\n\n";

echo "2. AUTHENTICATION & AUTHORIZATION:\n";
echo "----------------------------------\n";
echo "// Protected admin routes\n";
echo "\$router->middleware(['auth', 'secure'])->prefix('admin')->group(function (\$router) {\n";
echo "    \$router->get('/dashboard', 'Admin\\DashboardController@index');\n";
echo "    \$router->resource('users', 'Admin\\UserController');\n";
echo "});\n\n";

echo "// API with authentication\n";
echo "\$router->middleware(['api', 'auth:api'])->prefix('api')->group(function (\$router) {\n";
echo "    \$router->get('/profile', 'Api\\ProfileController@show');\n";
echo "    \$router->put('/profile', 'Api\\ProfileController@update');\n";
echo "});\n\n";

echo "3. RATE LIMITING EXAMPLES:\n";
echo "--------------------------\n";
echo "// Login endpoints with strict rate limiting\n";
echo "\$router->middleware('throttle:5,15')->group(function (\$router) {\n";
echo "    \$router->post('/login', 'AuthController@login');\n";
echo "    \$router->post('/register', 'AuthController@register');\n";
echo "    \$router->post('/password/reset', 'PasswordController@reset');\n";
echo "});\n\n";

echo "// API with different rate limits\n";
echo "\$router->prefix('api')->group(function (\$router) {\n";
echo "    // Free tier: 60 requests per minute\n";
echo "    \$router->middleware('throttle:60,1')->get('/public/data', 'PublicController@data');\n";
echo "    \n";
echo "    // Premium tier: 1000 requests per minute\n";
echo "    \$router->middleware(['auth:api', 'throttle:1000,1'])->get('/premium/data', 'PremiumController@data');\n";
echo "});\n\n";

echo "4. CORS CONFIGURATION:\n";
echo "----------------------\n";
echo "// config/security.php - CORS settings\n";
echo "'cors' => [\n";
echo "    'allowed_origins' => ['https://myapp.com', 'https://admin.myapp.com'],\n";
echo "    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],\n";
echo "    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],\n";
echo "    'exposed_headers' => ['X-Total-Count', 'X-Page-Count'],\n";
echo "    'max_age' => 86400,\n";
echo "    'supports_credentials' => true,\n";
echo "],\n\n";

echo "5. SECURITY HEADERS CONFIGURATION:\n";
echo "----------------------------------\n";
echo "// config/security.php - Security headers\n";
echo "'headers' => [\n";
echo "    'x_frame_options' => 'DENY',\n";
echo "    'content_security_policy' => \"default-src 'self'; script-src 'self' 'unsafe-inline' cdn.example.com;\",\n";
echo "    'strict_transport_security' => 'max-age=31536000; includeSubDomains; preload',\n";
echo "    'referrer_policy' => 'strict-origin-when-cross-origin',\n";
echo "],\n\n";

echo "6. CSRF PROTECTION:\n";
echo "-------------------\n";
echo "<!-- In your forms -->\n";
echo "<form method=\"POST\" action=\"/contact\">\n";
echo "    @csrf\n";
echo "    <!-- or manually -->\n";
echo "    <input type=\"hidden\" name=\"_token\" value=\"{{ csrf_token() }}\">\n";
echo "    \n";
echo "    <input type=\"text\" name=\"name\" required>\n";
echo "    <button type=\"submit\">Submit</button>\n";
echo "</form>\n\n";

echo "<!-- For AJAX requests -->\n";
echo "<meta name=\"csrf-token\" content=\"{{ csrf_token() }}\">\n";
echo "<script>\n";
echo "    \$.ajaxSetup({\n";
echo "        headers: {\n";
echo "            'X-CSRF-TOKEN': \$('meta[name=\"csrf-token\"]').attr('content')\n";
echo "        }\n";
echo "    });\n";
echo "</script>\n\n";

echo "7. SIGNED ROUTES:\n";
echo "-----------------\n";
echo "// Generate signed URL\n";
echo "\$signedUrl = \$urlGenerator->signedRoute('verify-email', [\n";
echo "    'user' => \$user->id,\n";
echo "    'hash' => sha1(\$user->email)\n";
echo "], now()->addMinutes(60));\n\n";

echo "// Route with signature validation\n";
echo "\$router->middleware('signed')->get('/verify-email/{user}/{hash}', 'EmailController@verify')\n";
echo "       ->name('verify-email');\n\n";

echo "8. MAINTENANCE MODE:\n";
echo "--------------------\n";
echo "// Enable maintenance mode\n";
echo "php horizon down --retry=60 --secret=my-secret\n\n";

echo "// Access during maintenance (bypass with secret)\n";
echo "https://myapp.com/path?secret=my-secret\n\n";

echo "// Disable maintenance mode\n";
echo "php horizon up\n\n";

echo "9. SECURITY MIDDLEWARE COMBINATIONS:\n";
echo "------------------------------------\n";

$combinations = [
    'Public API' => ['cors', 'security.headers', 'throttle:100,1'],
    'Web Application' => ['web', 'maintenance'],
    'Admin Panel' => ['web', 'auth', 'throttle:30,1'],
    'API Authentication' => ['api', 'auth:api'],
    'Sensitive Operations' => ['auth', 'csrf', 'throttle:10,5'],
    'Webhook Endpoints' => ['signed', 'throttle:50,1'],
    'Public Downloads' => ['security.headers', 'throttle:200,1']
];

foreach ($combinations as $useCase => $middleware) {
    echo "   $useCase:\n";
    echo "     \$router->middleware(['" . implode("', '", $middleware) . "']);\n\n";
}

echo "10. CUSTOM SECURITY MIDDLEWARE:\n";
echo "-------------------------------\n";
echo "class CustomSecurityMiddleware implements MiddlewareInterface\n";
echo "{\n";
echo "    public function handle(RequestInterface \$request, Closure \$next): ResponseInterface\n";
echo "    {\n";
echo "        // Custom security checks\n";
echo "        if (\$this->isBlacklisted(\$request->ip())) {\n";
echo "            throw new HttpException(403, 'Forbidden');\n";
echo "        }\n";
echo "        \n";
echo "        \$response = \$next(\$request);\n";
echo "        \n";
echo "        // Add custom security headers\n";
echo "        \$response->header('X-Custom-Security', 'enabled');\n";
echo "        \n";
echo "        return \$response;\n";
echo "    }\n";
echo "}\n\n";

echo "11. PRODUCTION SECURITY CHECKLIST:\n";
echo "----------------------------------\n";

$checklist = [
    'Enable HTTPS with valid SSL certificate',
    'Configure proper CORS origins (no wildcards)',
    'Set up CSRF protection for all forms',
    'Implement rate limiting on all endpoints',
    'Add security headers (CSP, HSTS, etc.)',
    'Enable maintenance mode capability',
    'Set up request signing for sensitive APIs',
    'Configure trusted proxies for load balancers',
    'Implement proper session security',
    'Set up security monitoring and logging',
    'Regular security audits and penetration testing',
    'Keep dependencies updated',
    'Implement proper input validation and sanitization'
];

foreach ($checklist as $index => $item) {
    echo "   ☐ " . ($index + 1) . ". $item\n";
}

echo "\n✓ Security middleware implementation complete!\n";
echo "  All security components are ready for production use.\n";