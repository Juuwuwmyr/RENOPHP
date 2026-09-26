<?php

declare(strict_types=1);

namespace Horizon\Foundation;

use Horizon\Http\Kernel;
use Horizon\Http\Request;
use Horizon\Http\Response;
use Horizon\Routing\Router;
use Horizon\Routing\RouteGroup;
use Horizon\Routing\RouteParameterBinder;
use Horizon\Middleware\MiddlewareManager;
use Closure;

/**
 * Application
 * 
 * Main application class that provides a high-level interface
 * for configuring and running the HTTP kernel.
 */
class Application
{
    /**
     * The HTTP kernel instance.
     */
    protected Kernel $kernel;

    /**
     * The router instance.
     */
    protected Router $router;

    /**
     * Application configuration.
     */
    protected array $config = [];

    /**
     * Service providers.
     */
    protected array $providers = [];

    /**
     * Application state.
     */
    protected bool $booted = false;

    /**
     * Base path of the application.
     */
    protected string $basePath;

    /**
     * Environment name.
     */
    protected string $environment;

    /**
     * Debug mode flag.
     */
    protected bool $debug;

    /**
     * Create a new Application instance.
     */
    public function __construct(string $basePath = '')
    {
        $this->basePath = $basePath ?: getcwd();
        $this->environment = $_ENV['APP_ENV'] ?? 'production';
        $this->debug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $this->bootstrapApplication();
    }

    // ====================================================================
    // Application Bootstrap
    // ====================================================================

    /**
     * Bootstrap the application.
     */
    protected function bootstrapApplication(): void
    {
        // Create core instances
        $this->router = new Router();
        $middleware = new MiddlewareManager();
        $binder = new RouteParameterBinder();

        // Create kernel
        $this->kernel = new Kernel($this->router, $middleware, $binder);

        // Load default configuration
        $this->loadDefaultConfiguration();

        // Register default exception handlers
        $this->registerDefaultExceptionHandlers();

        // Register application hooks
        $this->registerApplicationHooks();
    }

    /**
     * Load default configuration.
     */
    protected function loadDefaultConfiguration(): void
    {
        $this->config = [
            'app' => [
                'name' => $_ENV['APP_NAME'] ?? 'Horizon Application',
                'env' => $this->environment,
                'debug' => $this->debug,
                'url' => $_ENV['APP_URL'] ?? 'http://localhost',
                'timezone' => $_ENV['APP_TIMEZONE'] ?? 'UTC',
            ],
            'routing' => [
                'cache' => $_ENV['ROUTE_CACHE'] ?? false,
                'cache_file' => $this->basePath . '/storage/cache/routes.php',
            ],
            'middleware' => [
                'global' => [],
                'groups' => [
                    'web' => ['security', 'csrf'],
                    'api' => ['cors', 'throttle'],
                ],
                'aliases' => [
                    'auth' => 'AuthMiddleware',
                    'cors' => \Horizon\Middleware\CorsMiddleware::class,
                    'security' => \Horizon\Middleware\SecurityMiddleware::class,
                    'throttle' => \Horizon\Middleware\ThrottleMiddleware::class,
                ],
            ],
        ];
    }

    /**
     * Register default exception handlers.
     */
    protected function registerDefaultExceptionHandlers(): void
    {
        // Custom exception handlers can be registered here
        $this->kernel->registerExceptionHandler(\InvalidArgumentException::class, function ($exception, $request) {
            return Response::json([
                'error' => 'Invalid Argument',
                'message' => $exception->getMessage(),
            ], 400);
        });
    }

    /**
     * Register application lifecycle hooks.
     */
    protected function registerApplicationHooks(): void
    {
        // Request logging
        $this->kernel->hook('request.start', function (Request $request) {
            if ($this->debug) {
                error_log("[{$request->method()}] {$request->fullUrl()}");
            }
        });

        // Response logging
        $this->kernel->hook('request.handled', function (Request $request, Response $response) {
            if ($this->debug) {
                error_log("[{$response->getStatusCode()}] {$request->method()} {$request->path()}");
            }
        });

        // Exception logging
        $this->kernel->hook('exception.thrown', function (Request $request, \Throwable $exception) {
            error_log("Exception: " . $exception->getMessage() . " in " . $exception->getFile() . ":" . $exception->getLine());
        });
    }

    // ====================================================================
    // Request Handling
    // ====================================================================

    /**
     * Handle an HTTP request.
     */
    public function handle(Request $request): Response
    {
        $this->bootIfNotBooted();
        return $this->kernel->handle($request);
    }

    /**
     * Handle request from global variables.
     */
    public function handleRequest(): Response
    {
        $request = Request::createFromGlobals();
        return $this->handle($request);
    }

    /**
     * Run the application.
     */
    public function run(): void
    {
        $request = Request::createFromGlobals();
        $response = $this->handle($request);
        $this->kernel->sendResponse($response);
    }

    // ====================================================================
    // Routing Interface
    // ====================================================================

    /**
     * Register a GET route.
     */
    public function get(string $uri, mixed $action): \Horizon\Routing\Route
    {
        return $this->router->get($uri, $action);
    }

    /**
     * Register a POST route.
     */
    public function post(string $uri, mixed $action): \Horizon\Routing\Route
    {
        return $this->router->post($uri, $action);
    }

    /**
     * Register a PUT route.
     */
    public function put(string $uri, mixed $action): \Horizon\Routing\Route
    {
        return $this->router->put($uri, $action);
    }

    /**
     * Register a PATCH route.
     */
    public function patch(string $uri, mixed $action): \Horizon\Routing\Route
    {
        return $this->router->patch($uri, $action);
    }

    /**
     * Register a DELETE route.
     */
    public function delete(string $uri, mixed $action): \Horizon\Routing\Route
    {
        return $this->router->delete($uri, $action);
    }

    /**
     * Register a route for any HTTP method.
     */
    public function any(string $uri, mixed $action): \Horizon\Routing\Route
    {
        return $this->router->any($uri, $action);
    }

    /**
     * Register a route for multiple HTTP methods.
     */
    public function match(array $methods, string $uri, mixed $action): \Horizon\Routing\Route
    {
        return $this->router->match($methods, $uri, $action);
    }

    /**
     * Create a route group.
     */
    public function group(array $attributes, Closure $callback): void
    {
        $this->router->group($attributes, $callback);
    }

    /**
     * Create a new route group.
     */
    public function newGroup(array $attributes = []): RouteGroup
    {
        return $this->router->newGroup($attributes);
    }

    /**
     * Register a resource route.
     */
    public function resource(string $name, string $controller, array $options = []): \Horizon\Routing\ResourceRouteRegistrar
    {
        return $this->router->resourceAdvanced($name, $controller, $options);
    }

    /**
     * Register an API resource route.
     */
    public function apiResource(string $name, string $controller, array $options = []): \Horizon\Routing\ResourceRouteRegistrar
    {
        return $this->router->apiResource($name, $controller, $options);
    }

    // ====================================================================
    // Middleware Management
    // ====================================================================

    /**
     * Add global middleware.
     */
    public function middleware(string|array $middleware): void
    {
        $middleware = is_array($middleware) ? $middleware : [$middleware];
        $this->kernel->registerGlobalMiddleware($middleware);
    }

    /**
     * Register route middleware.
     */
    public function routeMiddleware(array $middleware): void
    {
        $this->kernel->registerRouteMiddleware($middleware);
    }

    /**
     * Register middleware groups.
     */
    public function middlewareGroups(array $groups): void
    {
        $this->kernel->registerMiddlewareGroups($groups);
    }

    // ====================================================================
    // Configuration
    // ====================================================================

    /**
     * Set configuration value.
     */
    public function config(string $key, mixed $value = null): mixed
    {
        if ($value === null) {
            return $this->getConfig($key);
        }

        $this->setConfig($key, $value);
        return $this;
    }

    /**
     * Get configuration value.
     */
    protected function getConfig(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Set configuration value.
     */
    protected function setConfig(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $segment) {
            if (!isset($config[$segment]) || !is_array($config[$segment])) {
                $config[$segment] = [];
            }
            $config = &$config[$segment];
        }

        $config = $value;
    }

    /**
     * Load configuration from file.
     */
    public function loadConfig(string $file): void
    {
        if (file_exists($file)) {
            $config = require $file;
            if (is_array($config)) {
                $this->config = array_merge_recursive($this->config, $config);
            }
        }
    }

    // ====================================================================
    // Environment and Paths
    // ====================================================================

    /**
     * Get the application base path.
     */
    public function basePath(string $path = ''): string
    {
        return $this->basePath . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : '');
    }

    /**
     * Get the application storage path.
     */
    public function storagePath(string $path = ''): string
    {
        return $this->basePath('storage') . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : '');
    }

    /**
     * Get the application public path.
     */
    public function publicPath(string $path = ''): string
    {
        return $this->basePath('public') . ($path ? DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR) : '');
    }

    /**
     * Get the application environment.
     */
    public function environment(): string
    {
        return $this->environment;
    }

    /**
     * Check if the application is in a specific environment.
     */
    public function isEnvironment(string ...$environments): bool
    {
        return in_array($this->environment, $environments);
    }

    /**
     * Check if debug mode is enabled.
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    // ====================================================================
    // Application Lifecycle
    // ====================================================================

    /**
     * Boot the application if not already booted.
     */
    protected function bootIfNotBooted(): void
    {
        if (!$this->booted) {
            $this->boot();
        }
    }

    /**
     * Boot the application.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        // Boot kernel
        $this->kernel->boot();

        // Register middleware configuration
        $this->applyMiddlewareConfiguration();

        // Boot service providers
        $this->bootServiceProviders();

        $this->booted = true;
    }

    /**
     * Apply middleware configuration.
     */
    protected function applyMiddlewareConfiguration(): void
    {
        $middlewareConfig = $this->config['middleware'] ?? [];

        if (isset($middlewareConfig['global'])) {
            $this->kernel->registerGlobalMiddleware($middlewareConfig['global']);
        }

        if (isset($middlewareConfig['aliases'])) {
            $this->kernel->registerRouteMiddleware($middlewareConfig['aliases']);
        }

        if (isset($middlewareConfig['groups'])) {
            $this->kernel->registerMiddlewareGroups($middlewareConfig['groups']);
        }
    }

    /**
     * Boot service providers.
     */
    protected function bootServiceProviders(): void
    {
        foreach ($this->providers as $provider) {
            if (method_exists($provider, 'boot')) {
                $provider->boot($this);
            }
        }
    }

    /**
     * Register a service provider.
     */
    public function register(object $provider): void
    {
        $this->providers[] = $provider;

        if (method_exists($provider, 'register')) {
            $provider->register($this);
        }
    }

    /**
     * Check if application is booted.
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    // ====================================================================
    // Utility Methods
    // ====================================================================

    /**
     * Get application statistics.
     */
    public function getStats(): array
    {
        return [
            'environment' => $this->environment,
            'debug' => $this->debug,
            'booted' => $this->booted,
            'providers_count' => count($this->providers),
            'kernel_stats' => $this->kernel->getStats(),
            'router_stats' => $this->router->getStats(),
            'config_keys' => array_keys($this->config),
        ];
    }

    /**
     * Get the kernel instance.
     */
    public function getKernel(): Kernel
    {
        return $this->kernel;
    }

    /**
     * Get the router instance.
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * Create application instance from environment.
     */
    public static function create(string $basePath = ''): static
    {
        return new static($basePath);
    }
}