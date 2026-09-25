<?php

echo "Horizon Framework - Controller Resolution and Method Injection\n";
echo "============================================================\n\n";

echo "1. CONTROLLER STRUCTURE:\n";
echo "------------------------\n";
echo "<?php\n\n";
echo "namespace App\\Http\\Controllers;\n\n";
echo "use Horizon\\Http\\Controllers\\Controller;\n";
echo "use Horizon\\Contracts\\Http\\RequestInterface;\n\n";
echo "class UserController extends Controller\n";
echo "{\n";
echo "    public function __construct(UserRepository \$userRepository)\n";
echo "    {\n";
echo "        \$this->userRepository = \$userRepository;\n";
echo "        \n";
echo "        // Register middleware\n";
echo "        \$this->middleware('auth')->except('index', 'show');\n";
echo "        \$this->middleware('throttle:30,1')->only('store', 'update');\n";
echo "    }\n\n";
echo "    public function index(RequestInterface \$request)\n";
echo "    {\n";
echo "        // Method injection: \$request is automatically injected\n";
echo "        \$page = \$request->query('page', 1);\n";
echo "        \$users = \$this->userRepository->paginate(\$page);\n";
echo "        \n";
echo "        return \$this->json(\$users);\n";
echo "    }\n\n";
echo "    public function show(\$id, UserService \$userService)\n";
echo "    {\n";
echo "        // Method injection: \$userService is resolved from container\n";
echo "        // Route parameter: \$id comes from {id} in route\n";
echo "        \$user = \$userService->findOrFail(\$id);\n";
echo "        \n";
echo "        return \$this->json(\$user);\n";
echo "    }\n\n";
echo "    public function store(CreateUserRequest \$request, UserService \$userService)\n";
echo "    {\n";
echo "        // Form Request injection with validation\n";
echo "        \$userData = \$request->validated();\n";
echo "        \$user = \$userService->create(\$userData);\n";
echo "        \n";
echo "        return \$this->success(\$user, 'User created successfully', 201);\n";
echo "    }\n";
echo "}\n\n";

echo "2. ROUTE DEFINITION:\n";
echo "--------------------\n";
echo "// Simple controller method\n";
echo "\$router->get('/users', 'UserController@index');\n\n";
echo "// With route parameters\n";
echo "\$router->get('/users/{id}', 'UserController@show')->whereNumber('id');\n\n";
echo "// Resource routes (automatic controller resolution)\n";
echo "\$router->resource('users', 'UserController');\n\n";
echo "// API resource (excludes create/edit forms)\n";
echo "\$router->apiResource('posts', 'PostController');\n\n";
echo "// Nested resources\n";
echo "\$router->resource('users.posts', 'UserPostController');\n\n";

echo "3. DEPENDENCY INJECTION EXAMPLES:\n";
echo "---------------------------------\n";

$injectionExamples = [
    'RequestInterface $request' => 'Current HTTP request object',
    'UserRepository $repository' => 'Service resolved from container',
    'AuthManager $auth' => 'Authentication manager',
    'ValidatorFactory $validator' => 'Validation factory',
    'CacheManager $cache' => 'Cache manager',
    'LoggerInterface $logger' => 'PSR-3 logger interface',
];

foreach ($injectionExamples as $injection => $description) {
    echo "   public function method($injection)\n";
    echo "   {\n";
    echo "       // $description\n";
    echo "   }\n\n";
}

echo "4. MIDDLEWARE ON CONTROLLERS:\n";
echo "-----------------------------\n";
echo "class AdminController extends Controller\n";
echo "{\n";
echo "    public function __construct()\n";
echo "    {\n";
echo "        // Apply to all methods\n";
echo "        \$this->middleware('auth');\n";
echo "        \n";
echo "        // Apply to specific methods\n";
echo "        \$this->middleware('admin')->only('destroy', 'forceDelete');\n";
echo "        \n";
echo "        // Apply except certain methods\n";
echo "        \$this->middleware('throttle:100,1')->except('index');\n";
echo "        \n";
echo "        // Multiple middleware\n";
echo "        \$this->middleware(['auth', 'verified', 'admin']);\n";
echo "    }\n";
echo "}\n\n";

echo "5. RESPONSE HELPERS:\n";
echo "--------------------\n";

$responseHelpers = [
    '$this->json($data)' => 'JSON response',
    '$this->success($data, $message)' => 'Success JSON response', 
    '$this->error($message, $status)' => 'Error JSON response',
    '$this->view($template, $data)' => 'HTML view response',
    '$this->redirect($url)' => 'Redirect response',
    '$this->redirectToRoute($name, $params)' => 'Redirect to named route',
    '$this->download($file)' => 'File download response',
    '$this->stream($callback)' => 'Streamed response'
];

foreach ($responseHelpers as $method => $description) {
    echo "   $method;  // $description\n";
}

echo "\n";

echo "6. FORM REQUEST VALIDATION:\n";
echo "---------------------------\n";
echo "class CreateUserRequest extends FormRequest\n";
echo "{\n";
echo "    public function authorize(): bool\n";
echo "    {\n";
echo "        return \$this->user()->can('create-user');\n";
echo "    }\n\n";
echo "    public function rules(): array\n";
echo "    {\n";
echo "        return [\n";
echo "            'name' => 'required|string|max:255',\n";
echo "            'email' => 'required|email|unique:users,email',\n";
echo "            'password' => 'required|string|min:8|confirmed',\n";
echo "        ];\n";
echo "    }\n";
echo "}\n\n";

echo "7. CONTROLLER RESOLUTION PROCESS:\n";
echo "---------------------------------\n";
echo "Step 1: Router matches route to controller action\n";
echo "Step 2: ControllerDispatcher resolves controller class from container\n";
echo "Step 3: Method dependencies analyzed via reflection\n";
echo "Step 4: Route parameters mapped to method parameters\n";
echo "Step 5: Type-hinted dependencies resolved from container\n";
echo "Step 6: Method called with resolved parameters\n";
echo "Step 7: Response prepared and returned\n\n";

echo "8. PERFORMANCE OPTIMIZATIONS:\n";
echo "-----------------------------\n";
echo "• Controller instances cached per request\n";
echo "• Reflection metadata cached in production\n";
echo "• Middleware resolution optimized\n";
echo "• Route-to-controller mapping cached\n";
echo "• Dependency injection container optimized\n\n";

echo "9. DEBUGGING FEATURES:\n";
echo "----------------------\n";
echo "• Detailed error messages for failed injections\n";
echo "• Clear parameter resolution failure explanations\n";
echo "• Middleware execution tracing\n";
echo "• Controller method signature validation\n";
echo "• Performance profiling hooks\n\n";

echo "✓ Controller resolution and method injection architecture complete!\n";
echo "  Ready for both simple controllers and complex dependency injection scenarios.\n";