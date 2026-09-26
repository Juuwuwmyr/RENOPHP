<?php

declare(strict_types=1);

namespace Horizon\Http;

use Horizon\Http\Request;
use Horizon\Http\Response;
use Horizon\Routing\Router;
use Horizon\Routing\Route;
use Horizon\Routing\RouteParameterBinder;
use Horizon\Routing\Exceptions\RouteNotFoundException;
use Horizon\Routing\Exceptions\MethodNotAllowedException;
use Horizon\Routing\Exceptions\ModelNotFoundException;
use Horizon\Middleware\MiddlewareManager;
use Horizon\Middleware\Pipeline;
use Throwable;
use Closure;

/**
 * HTTP Kernel
 * 
 * Central request/response processing system that orchestrates
 * routing, middleware, parameter binding, and error handling.
 */
class Kernel
{
    /**
     * The router instance.
     */
    protected Router $router;

    /**
     * The middleware manager.
     */
    protected MiddlewareManager $middleware;

    /**
     * The parameter binder.
     */
    protected RouteParameterBinder $binder;

    /**
     * Exception handlers.
     */
    protected array $exceptionHandlers = [];

    /**
     * Bootstrappers to run before handling requests.
     */
    protected array $bootstrappers = [];

    /**
     * Global middleware stack.
     */
    protected array $globalMiddleware = [];

    /**
     * Route middleware aliases.
     */
    protected array $routeMiddleware = [];

    /**
     * Middleware groups.
     */
    protected array $middlewareGroups = [];

    /**
     * Whether the kernel has been booted.
     */
    protected bool $booted = false;

    /**
     * Request lifecycle hooks.
     */
    protected array $hooks = [];

    /**
     * Performance metrics.
     */
    protected array $metrics = [];

    /**
     * Create a new HTTP Kernel instance.
     */
    public function __construct(Router $router, ?MiddlewareManager $middleware = null, ?RouteParameterBinder $binder = null)
    {
        $this->router = $router;
        $this->middleware = $middleware ?? new MiddlewareManager();
        $this->binder = $binder ?? new RouteParameterBinder();
        
        $this->registerDefaultExceptionHandlers();
        $this->registerDefaultMiddleware();
    }

    // ====================================================================
    // Request Handling
    // ====================================================================

    /**
     * Handle an incoming HTTP request.
     */
    public function handle(Request $request): Response
    {
        $startTime = microtime(true);
        
        try {
            $this->bootIfNotBooted();
            $this->triggerHook('request.start', $request);
            
            // Find matching route
            $route = $this->findRoute($request);
            
            // Bind route to request
            $request->setRoute($route);
            
            // Resolve route parameters
            $this->resolveRouteParameters($route, $request);
            
            // Execute middleware pipeline
            $response = $this->executeMiddlewarePipeline($request, $route);
            
            // Ensure we have a Response object
            $response = $this->prepareResponse($response, $request);
            
            $this->triggerHook('request.handled', $request, $response);
            
        } catch (Throwable $exception) {
            $response = $this->handleException($request, $exception);
        } finally {
            $this->recordMetrics($startTime, $request, $response ?? null);
            $this->triggerHook('request.end', $request, $response ?? null);
        }

        return $response;
    }

    /**
     * Handle a request and send the response.
     */
    public function handleAndSend(Request $request): void
    {
        $response = $this->handle($request);
        $this->sendResponse($response);
    }

    /**
     * Send the response to the client.
     */
    public function sendResponse(Response $response): void
    {
        // Send status line
        http_response_code($response->getStatusCode());
        
        // Send headers
        foreach ($response->getHeaders() as $name => $value) {
            header("{$name}: {$value}");
        }
        
        // Send cookies
        foreach ($response->getCookies() as $cookie) {
            $cookie->send();
        }
        
        // Send content
        echo $response->getContent();
        
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }

    // ====================================================================
    // Route Resolution
    // ====================================================================

    /**
     * Find the route for the given request.
     */
    protected function findRoute(Request $request): Route
    {
        try {
            return $this->router->matchRequest($request);
        } catch (RouteNotFoundException | MethodNotAllowedException $e) {
            $this->triggerHook('route.not_found', $request, $e);
            throw $e;
        }
    }

    /**
     * Resolve route parameters.
     */
    protected function resolveRouteParameters(Route $route, Request $request): void
    {
        try {
            $parameters = $this->binder->resolveParameters($route, $request);
            $route->setParameters($parameters);
            $request->setRouteParameters($parameters);
        } catch (ModelNotFoundException $e) {
            $this->triggerHook('model.not_found', $request, $route, $e);
            throw $e;
        }
    }

    // ====================================================================
    // Middleware Pipeline
    // ====================================================================

    /**
     * Execute the middleware pipeline.
     */
    protected function executeMiddlewarePipeline(Request $request, Route $route): mixed
    {
        // Collect middleware
        $middleware = $this->gatherMiddleware($route);
        
        // Create destination closure
        $destination = function (Request $request) use ($route) {
            return $this->runRoute($request, $route);
        };

        // Execute pipeline
        return $this->middleware->execute($request, $middleware, $destination);
    }

    /**
     * Gather middleware for the route.
     */
    protected function gatherMiddleware(Route $route): array
    {
        $middleware = [];
        
        // Global middleware
        $middleware = array_merge($middleware, $this->globalMiddleware);
        
        // Route middleware
        $routeMiddleware = $route->getMiddleware();
        $middleware = array_merge($middleware, $routeMiddleware);
        
        return $middleware;
    }

    /**
     * Run the route action.
     */
    protected function runRoute(Request $request, Route $route): mixed
    {
        $this->triggerHook('route.start', $request, $route);
        
        try {
            // Resolve method dependencies
            $parameters = $this->binder->resolveMethodDependencies($route, $request, $route->getParameters());
            
            // Execute route action
            $response = $route->run($request);
            
            $this->triggerHook('route.executed', $request, $route, $response);
            
            return $response;
            
        } catch (Throwable $e) {
            $this->triggerHook('route.exception', $request, $route, $e);
            throw $e;
        }
    }

    // ====================================================================
    // Response Preparation
    // ====================================================================

    /**
     * Prepare the response.
     */
    protected function prepareResponse(mixed $response, Request $request): Response
    {
        if ($response instanceof Response) {
            return $response;
        }

        if (is_string($response)) {
            return new Response($response);
        }

        if (is_array($response) || is_object($response)) {
            return Response::json($response);
        }

        if ($response === null) {
            return new Response('', 204); // No Content
        }

        if (is_bool($response)) {
            return Response::json(['success' => $response]);
        }

        if (is_numeric($response)) {
            return new Response((string) $response);
        }

        // Attempt to convert to string
        return new Response((string) $response);
    }

    // ====================================================================
    // Exception Handling
    // ====================================================================

    /**
     * Handle an exception during request processing.
     */
    protected function handleException(Request $request, Throwable $exception): Response
    {
        $this->triggerHook('exception.thrown', $request, $exception);
        
        // Find appropriate exception handler
        $handler = $this->findExceptionHandler($exception);
        
        if ($handler) {
            try {
                $response = $handler($exception, $request);
                $this->triggerHook('exception.handled', $request, $exception, $response);
                return $this->prepareResponse($response, $request);
            } catch (Throwable $handlerException) {
                $this->triggerHook('exception.handler_failed', $request, $exception, $handlerException);
            }
        }

        // Fallback to default error response
        return $this->createDefaultErrorResponse($exception, $request);
    }

    /**
     * Find exception handler for the given exception.
     */
    protected function findExceptionHandler(Throwable $exception): ?Closure
    {
        $exceptionClass = get_class($exception);
        
        // Check for exact match
        if (isset($this->exceptionHandlers[$exceptionClass])) {
            return $this->exceptionHandlers[$exceptionClass];
        }

        // Check for parent class matches
        foreach ($this->exceptionHandlers as $handlerClass => $handler) {
            if (is_subclass_of($exception, $handlerClass)) {
                return $handler;
            }
        }

        return null;
    }

    /**
     * Create default error response.
     */
    protected function createDefaultErrorResponse(Throwable $exception, Request $request): Response
    {
        $isDevelopment = $this->isDevelopmentMode();
        
        if ($exception instanceof RouteNotFoundException) {
            return $this->createNotFoundResponse($exception, $request, $isDevelopment);
        }

        if ($exception instanceof MethodNotAllowedException) {
            return $this->createMethodNotAllowedResponse($exception, $request, $isDevelopment);
        }

        if ($exception instanceof ModelNotFoundException) {
            return $this->createModelNotFoundResponse($exception, $request, $isDevelopment);
        }

        // Generic server error
        return $this->createServerErrorResponse($exception, $request, $isDevelopment);
    }

    /**
     * Create 404 Not Found response.
     */
    protected function createNotFoundResponse(RouteNotFoundException $exception, Request $request, bool $isDevelopment): Response
    {
        if ($request->expectsJson()) {
            $data = ['error' => 'Not Found', 'message' => 'The requested resource was not found.'];
            
            if ($isDevelopment) {
                $data['debug'] = [
                    'method' => $request->method(),
                    'uri' => $request->path(),
                    'exception' => $exception->getMessage(),
                ];
            }
            
            return Response::json($data, 404);
        }

        $content = $isDevelopment ? $exception->getDeveloperMessage() : 'Page Not Found';
        return new Response($content, 404);
    }

    /**
     * Create 405 Method Not Allowed response.
     */
    protected function createMethodNotAllowedResponse(MethodNotAllowedException $exception, Request $request, bool $isDevelopment): Response
    {
        $response = $request->expectsJson()
            ? Response::json([
                'error' => 'Method Not Allowed',
                'message' => 'The requested method is not allowed for this resource.',
                'allowed_methods' => $exception->getAllowedMethods(),
            ], 405)
            : new Response($isDevelopment ? $exception->getDeveloperMessage() : 'Method Not Allowed', 405);

        $response->header('Allow', $exception->getAllowHeader());
        return $response;
    }

    /**
     * Create 404 Model Not Found response.
     */
    protected function createModelNotFoundResponse(ModelNotFoundException $exception, Request $request, bool $isDevelopment): Response
    {
        if ($request->expectsJson()) {
            $data = ['error' => 'Not Found', 'message' => 'The requested resource was not found.'];
            
            if ($isDevelopment) {
                $data['debug'] = [
                    'model' => $exception->getModel(),
                    'parameter' => $exception->getParameterKey(),
                    'value' => $exception->getParameterValue(),
                ];
            }
            
            return Response::json($data, 404);
        }

        $content = $isDevelopment ? $exception->getDeveloperMessage() : 'Resource Not Found';
        return new Response($content, 404);
    }

    /**
     * Create 500 Server Error response.
     */
    protected function createServerErrorResponse(Throwable $exception, Request $request, bool $isDevelopment): Response
    {
        if ($request->expectsJson()) {
            $data = ['error' => 'Internal Server Error', 'message' => 'An internal server error occurred.'];
            
            if ($isDevelopment) {
                $data['debug'] = [
                    'exception' => get_class($exception),
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $exception->getTraceAsString(),
                ];
            }
            
            return Response::json($data, 500);
        }

        $content = $isDevelopment 
            ? $this->formatDevelopmentError($exception)
            : 'Internal Server Error';
            
        return new Response($content, 500);
    }

    /**
     * Format exception for development mode.
     */
    protected function formatDevelopmentError(Throwable $exception): string
    {
        return sprintf(
            "<h1>%s</h1>\n<p><strong>%s</strong></p>\n<p>in %s:%d</p>\n<pre>%s</pre>",
            get_class($exception),
            htmlspecialchars($exception->getMessage()),
            $exception->getFile(),
            $exception->getLine(),
            htmlspecialchars($exception->getTraceAsString())
        );
    }

    // ====================================================================
    // Kernel Configuration
    // ====================================================================

    /**
     * Register exception handler.
     */
    public function registerExceptionHandler(string $exceptionClass, Closure $handler): void
    {
        $this->exceptionHandlers[$exceptionClass] = $handler;
    }

    /**
     * Register multiple exception handlers.
     */
    public function registerExceptionHandlers(array $handlers): void
    {
        foreach ($handlers as $exceptionClass => $handler) {
            $this->registerExceptionHandler($exceptionClass, $handler);
        }
    }

    /**
     * Register bootstrapper.
     */
    public function registerBootstrapper(Closure $bootstrapper): void
    {
        $this->bootstrappers[] = $bootstrapper;
    }

    /**
     * Register multiple bootstrappers.
     */
    public function registerBootstrappers(array $bootstrappers): void
    {
        $this->bootstrappers = array_merge($this->bootstrappers, $bootstrappers);
    }

    /**
     * Register global middleware.
     */
    public function registerGlobalMiddleware(array $middleware): void
    {
        $this->globalMiddleware = array_merge($this->globalMiddleware, $middleware);
    }

    /**
     * Register route middleware.
     */
    public function registerRouteMiddleware(array $middleware): void
    {
        $this->routeMiddleware = array_merge($this->routeMiddleware, $middleware);
        $this->middleware->routes($middleware);
    }

    /**
     * Register middleware groups.
     */
    public function registerMiddlewareGroups(array $groups): void
    {
        $this->middlewareGroups = array_merge($this->middlewareGroups, $groups);
        
        foreach ($groups as $name => $middleware) {
            $this->middleware->group($name, $middleware);
        }
    }

    // ====================================================================
    // Kernel Lifecycle
    // ====================================================================

    /**
     * Boot the kernel if not already booted.
     */
    protected function bootIfNotBooted(): void
    {
        if (!$this->booted) {
            $this->boot();
        }
    }

    /**
     * Boot the kernel.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->triggerHook('kernel.booting');
        
        // Run bootstrappers
        foreach ($this->bootstrappers as $bootstrapper) {
            $bootstrapper($this);
        }

        $this->booted = true;
        
        $this->triggerHook('kernel.booted');
    }

    /**
     * Check if kernel is booted.
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    // ====================================================================
    // Hooks System
    // ====================================================================

    /**
     * Register lifecycle hook.
     */
    public function hook(string $event, Closure $callback): void
    {
        if (!isset($this->hooks[$event])) {
            $this->hooks[$event] = [];
        }
        
        $this->hooks[$event][] = $callback;
    }

    /**
     * Trigger lifecycle hook.
     */
    protected function triggerHook(string $event, ...$arguments): void
    {
        if (!isset($this->hooks[$event])) {
            return;
        }

        foreach ($this->hooks[$event] as $callback) {
            try {
                $callback(...$arguments);
            } catch (Throwable $e) {
                // Log hook exception but don't interrupt request processing
                error_log("Hook exception in {$event}: " . $e->getMessage());
            }
        }
    }

    // ====================================================================
    // Default Configuration
    // ====================================================================

    /**
     * Register default exception handlers.
     */
    protected function registerDefaultExceptionHandlers(): void
    {
        // Exception handlers are registered via configuration
        // This method can be overridden in subclasses
    }

    /**
     * Register default middleware.
     */
    protected function registerDefaultMiddleware(): void
    {
        // Default global middleware
        $this->globalMiddleware = [
            // 'cors',
            // 'security',
        ];

        // Default middleware aliases
        $this->middleware->aliases([
            'auth' => 'AuthMiddleware',
            'cors' => \Horizon\Middleware\CorsMiddleware::class,
            'security' => \Horizon\Middleware\SecurityMiddleware::class,
            'throttle' => \Horizon\Middleware\ThrottleMiddleware::class,
        ]);

        // Default middleware groups
        $this->middleware->group('web', ['security', 'csrf']);
        $this->middleware->group('api', ['cors', 'throttle']);
    }

    // ====================================================================
    // Utility Methods
    // ====================================================================

    /**
     * Check if in development mode.
     */
    protected function isDevelopmentMode(): bool
    {
        return ($_ENV['APP_ENV'] ?? 'production') === 'development';
    }

    /**
     * Record performance metrics.
     */
    protected function recordMetrics(float $startTime, Request $request, ?Response $response): void
    {
        $this->metrics[] = [
            'start_time' => $startTime,
            'end_time' => microtime(true),
            'duration' => microtime(true) - $startTime,
            'method' => $request->method(),
            'uri' => $request->path(),
            'status' => $response ? $response->getStatusCode() : 500,
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
        ];

        // Keep only last 100 metrics to prevent memory leaks
        if (count($this->metrics) > 100) {
            $this->metrics = array_slice($this->metrics, -100);
        }
    }

    /**
     * Get performance metrics.
     */
    public function getMetrics(): array
    {
        return $this->metrics;
    }

    /**
     * Get kernel statistics.
     */
    public function getStats(): array
    {
        return [
            'booted' => $this->booted,
            'global_middleware_count' => count($this->globalMiddleware),
            'route_middleware_count' => count($this->routeMiddleware),
            'middleware_groups_count' => count($this->middlewareGroups),
            'exception_handlers_count' => count($this->exceptionHandlers),
            'bootstrappers_count' => count($this->bootstrappers),
            'hooks_count' => array_sum(array_map('count', $this->hooks)),
            'metrics_count' => count($this->metrics),
        ];
    }

    /**
     * Get router instance.
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * Get middleware manager.
     */
    public function getMiddleware(): MiddlewareManager
    {
        return $this->middleware;
    }

    /**
     * Get parameter binder.
     */
    public function getBinder(): RouteParameterBinder
    {
        return $this->binder;
    }
}