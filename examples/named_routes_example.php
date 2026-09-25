<?php

echo "Horizon Framework - Named Routes and URL Generation\n";
echo "==================================================\n\n";

echo "1. ROUTE DEFINITION WITH NAMES:\n";
echo "-------------------------------\n";
echo "// Basic named route\n";
echo "\$router->get('/users', 'UserController@index')->name('users.index');\n\n";

echo "// Route with parameters\n";
echo "\$router->get('/users/{id}', 'UserController@show')->name('users.show');\n\n";

echo "// Resource routes (auto-named)\n";
echo "\$router->resource('posts', 'PostController'); // creates posts.index, posts.show, etc.\n\n";

echo "2. ROUTE GROUPS WITH NAME PREFIXES:\n";
echo "-----------------------------------\n";
echo "// Group with name prefix\n";
echo "\$router->name('admin.')->prefix('admin')->group(function (\$router) {\n";
echo "    \$router->get('dashboard', 'DashboardController@index')->name('dashboard');\n";
echo "    // Creates route named 'admin.dashboard'\n";
echo "    \n";
echo "    \$router->resource('users', 'UserController');\n";
echo "    // Creates admin.users.index, admin.users.show, etc.\n";
echo "});\n\n";

echo "3. URL GENERATION EXAMPLES:\n";
echo "---------------------------\n";

// Simulate URL generation calls
$examples = [
    "route('users.index')" => "http://localhost/users",
    "route('users.show', ['id' => 123])" => "http://localhost/users/123",
    "route('users.show', ['id' => 123, 'tab' => 'profile'])" => "http://localhost/users/123?tab=profile",
    "route('admin.dashboard')" => "http://localhost/admin/dashboard",
    "url('/contact')" => "http://localhost/contact",
    "url('/search', ['q' => 'laravel'])" => "http://localhost/search?q=laravel",
    "asset('css/app.css')" => "http://localhost/css/app.css",
    "secure_url('/login')" => "https://localhost/login",
];

foreach ($examples as $call => $result) {
    echo "// $call\n";
    echo "// Result: $result\n\n";
}

echo "4. ADVANCED FEATURES:\n";
echo "--------------------\n";
echo "// Route with domain\n";
echo "\$router->domain('api.example.com')->group(function (\$router) {\n";
echo "    \$router->get('/', 'ApiController@index')->name('api.home');\n";
echo "});\n\n";

echo "// Generate URL: route('api.home') -> https://api.example.com/\n\n";

echo "// Route with constraints\n";
echo "\$router->get('/users/{id}', 'UserController@show')\n";
echo "        ->name('users.show')\n";
echo "        ->where('id', '[0-9]+');\n\n";

echo "// Optional parameters\n";
echo "\$router->get('/posts/{category?}', 'PostController@index')->name('posts.index');\n";
echo "// route('posts.index') -> /posts\n";
echo "// route('posts.index', ['category' => 'tech']) -> /posts/tech\n\n";

echo "5. HELPER FUNCTIONS AVAILABLE:\n";
echo "------------------------------\n";
$helpers = [
    'route($name, $parameters)' => 'Generate named route URL',
    'url($path, $parameters)' => 'Generate absolute URL', 
    'asset($path)' => 'Generate asset URL',
    'secure_url($path)' => 'Generate HTTPS URL',
    'secure_asset($path)' => 'Generate HTTPS asset URL',
    'back($fallback)' => 'Get previous URL from session'
];

foreach ($helpers as $function => $description) {
    echo "• $function - $description\n";
}

echo "\n6. ROUTE CACHING (Future Enhancement):\n";
echo "-------------------------------------\n";
echo "php horizon route:cache    # Cache routes for production\n";
echo "php horizon route:clear    # Clear route cache\n";
echo "php horizon route:list     # List all registered routes\n\n";

echo "✓ Named Routes and URL Generation Architecture Complete!\n";