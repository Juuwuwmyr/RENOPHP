<?php

declare(strict_types=1);

namespace Horizon\Routing;

use Horizon\Contracts\Foundation\ApplicationInterface;
use Horizon\Contracts\Http\RequestInterface;
use Horizon\Contracts\Http\ResponseInterface;
use Horizon\Contracts\Routing\RouteInterface;
use Horizon\Http\Response;
use Horizon\Support\Str;
use Horizon\Support\Arr;

class Route implements RouteInterface
{
    /**
     * The application instance.
     */
    protected ApplicationInterface $container;

    /**
     * The HTTP methods the route responds to.
     */
    protected array $methods;

    /**
     * The route URI.
     */
    protected string $uri;

    /**
     * The route action array.
     */
    protected array $action = [];

    /**
     * The route parameter names.
     */
    protected ?array $parameterNames = null;

    /**
     * The route parameters.
     */
    protected array $parameters = [];

    /**
     * The compiled version of the route.
     */
    protected ?CompiledRoute $compiled = null;

    /**
     * The router instance used by the route.
     */
    protected Router $router;

    /**
     * The computed gathered middleware.
     */
    protected ?array $computedMiddleware = null;

    /**
     * The default values for the route.
     */
    public array $defaults = [];

    /**
     * The regular expression requirements.
     */
    public array $wheres = [];

    /**
     * Indicates whether the route is a fallback route.
     */
    public bool $isFallback = false;

    /**
     * Create a new Route instance.
     */
    public function __construct(array $methods, string $uri, mixed $action)
    {
        $this->methods = (array) $methods;
        $this->uri = $uri;
        $this->action = $this->parseAction($action);

        if (in_array('GET', $this->methods) && !in_array('HEAD', $this->methods)) {
            $this->methods[] = 'HEAD';
        }
    }

    /**
     * Parse the route action into a standard array.
     */
    protected function parseAction(mixed $action): array
    {
        return RouteAction::parse($this->uri, $action);
    }

    /**
     * Get the HTTP methods for the route.
     */
    public function methods(): array
    {
        return $this->methods;
    }

    /**
     * Determine if the route only responds to HTTPS requests.
     */
    public function secure(): bool
    {
        return in_array('https', $this->action, true);
    }

    /**
     * Get the domain defined for the route.
     */
    public function domain(): ?string
    {
        return $this->action['domain'] ?? null;
    }

    /**
     * Get the URI associated with the route.
     */
    public function uri(): string
    {
        return $this->uri;
    }

    /**
     * Set the URI that the route responds to.
     */
    public function setUri(string $uri): static
    {
        $this->uri = $uri;

        return $this;
    }

    /**
     * Get the prefix of the route instance.
     */
    public function getPrefix(): string
    {
        return $this->action['prefix'] ?? '';
    }

    /**
     * Get the name of the route instance.
     */
    public function getName(): ?string
    {
        return $this->action['as'] ?? null;
    }

    /**
     * Add or change the route name.
     */
    public function name(string $name): static
    {
        $this->action['as'] = isset($this->action['as']) ? $this->action['as'] . $name : $name;

        return $this;
    }

    /**
     * Get the action associated with the route.
     */
    public function getAction(): mixed
    {
        return $this->action;
    }

    /**
     * Set the action array for the route.
     */
    public function setAction(array $action): static
    {
        $this->action = $action;

        return $this;
    }

    /**
     * Get the controller class for the route.
     */
    public function getController(): ?string
    {
        return $this->action['controller'] ?? null;
    }

    /**
     * Get the controller method for the route.
     */
    public function getControllerMethod(): ?string
    {
        return $this->action['method'] ?? null;
    }

    /**
     * Set or get the middlewares attached to the route.
     */
    public function middleware(mixed $middleware = null): static|array
    {
        if (is_null($middleware)) {
            return $this->gatherMiddleware();
        }

        if (is_string($middleware)) {
            $middleware = func_get_args();
        }

        $this->action['middleware'] = array_merge(
            (array) ($this->action['middleware'] ?? []),
            $middleware
        );

        return $this;
    }

    /**
     * Get the middleware attached to the route.
     */
    public function gatherMiddleware(): array
    {
        if (!is_null($this->computedMiddleware)) {
            return $this->computedMiddleware;
        }

        return $this->computedMiddleware = Router::uniqueMiddleware(
            array_merge($this->middleware(), $this->controllerMiddleware())
        );
    }

    /**
     * Get the middleware from the action array.
     */
    protected function middleware(): array
    {
        return (array) ($this->action['middleware'] ?? []);
    }

    /**
     * Get the controller middleware for the route.
     */
    protected function controllerMiddleware(): array
    {
        if (!$controller = $this->getController()) {
            return [];
        }

        return $this->controllerDispatcher()->getMiddleware(
            $controller, $this->getControllerMethod()
        );
    }

    /**
     * Get the controller dispatcher instance.
     */
    protected function controllerDispatcher(): ControllerDispatcher
    {
        if ($this->container->bound(ControllerDispatcher::class)) {
            return $this->container->make(ControllerDispatcher::class);
        }

        return new ControllerDispatcher($this->container);
    }

    /**
     * Determine if the route matches given request.
     */
    public function matches(RequestInterface $request): bool
    {
        $this->compileRoute();

        foreach ($this->getValidators() as $validator) {
            if (!$validator->matches($this, $request)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Compile the route into a CompiledRoute instance.
     */
    protected function compileRoute(): CompiledRoute
    {
        if (!$this->compiled) {
            $this->compiled = (new RouteCompiler($this))->compile();
        }

        return $this->compiled;
    }

    /**
     * Get the route validators for the instance.
     */
    public static function getValidators(): array
    {
        return [
            new Matching\UriValidator,
            new Matching\MethodValidator,
            new Matching\SchemeValidator,
            new Matching\HostValidator,
        ];
    }

    /**
     * Get the compiled version of the route.
     */
    public function getCompiled(): CompiledRoute
    {
        return $this->compileRoute();
    }

    /**
     * Bind the route to a given request for execution.
     */
    public function bind(RequestInterface $request): static
    {
        $this->compileRoute();

        $this->parameters = (new RouteParameterBinder($this))
            ->parameters($request);

        // Resolve implicit route model binding
        ImplicitRouteBinding::resolveForRoute($this, $request);

        return $this;
    }

    /**
     * Determine if the route has parameters.
     */
    public function hasParameters(): bool
    {
        return isset($this->parameters);
    }

    /**
     * Get route parameters.
     */
    public function parameters(): array
    {
        if (isset($this->parameters)) {
            return $this->parameters;
        }

        throw new \LogicException('Route is not bound.');
    }

    /**
     * Get a specific parameter by key.
     */
    public function parameter(string $name, mixed $default = null): mixed
    {
        return $this->parameters()[$name] ?? $default;
    }

    /**
     * Set a parameter to the given value.
     */
    public function setParameter(string $name, mixed $value): void
    {
        $this->parameters();

        $this->parameters[$name] = $value;
    }

    /**
     * Unset a parameter on the route.
     */
    public function forgetParameter(string $name): void
    {
        $this->parameters();

        unset($this->parameters[$name]);
    }

    /**
     * Get the key / value list of parameters for the route.
     */
    public function parametersWithoutNulls(): array
    {
        return array_filter($this->parameters(), function ($p) {
            return !is_null($p);
        });
    }

    /**
     * Get all of the parameter names for the route.
     */
    public function parameterNames(): array
    {
        if (isset($this->parameterNames)) {
            return $this->parameterNames;
        }

        return $this->parameterNames = $this->compileParameterNames();
    }

    /**
     * Get the parameter names for the route.
     */
    protected function compileParameterNames(): array
    {
        preg_match_all('/\{(.*?)\}/', $this->domain() . $this->uri, $matches);

        return array_map(function ($m) {
            return trim($m, '?');
        }, $matches[1]);
    }

    /**
     * Run the route action and return the response.
     */
    public function run(): ResponseInterface
    {
        $this->container = $this->container ?: app();

        try {
            if ($this->isControllerAction()) {
                return $this->runController();
            }

            return $this->runCallable();
        } catch (\Throwable $e) {
            return $this->handleRunException($e);
        }
    }

    /**
     * Checks whether the route's action is a controller.
     */
    protected function isControllerAction(): bool
    {
        return is_string($this->action['uses']) && !$this->isSerializedClosure();
    }

    /**
     * Checks whether the route's action is a serialized Closure.
     */
    protected function isSerializedClosure(): bool
    {
        return RouteAction::containsSerializedClosure($this->action);
    }

    /**
     * Run the route action and return the response.
     */
    protected function runCallable(): ResponseInterface
    {
        $callable = $this->action['uses'];

        if ($this->isSerializedClosure()) {
            $callable = unserialize($this->action['uses']);
        }

        return $this->prepareResponse(
            $this->container->call($callable, $this->resolveMethodDependencies(
                $this->parametersWithoutNulls(), new \ReflectionFunction($callable)
            ))
        );
    }

    /**
     * Run the route action and return the response.
     */
    protected function runController(): ResponseInterface
    {
        return $this->controllerDispatcher()->dispatch(
            $this, $this->container->make('request'), $this->getController(), $this->getControllerMethod()
        );
    }

    /**
     * Resolve the object method's type-hinted dependencies.
     */
    public function resolveMethodDependencies(array $parameters, \ReflectionFunctionAbstract $reflector): array
    {
        $instanceCount = 0;

        $values = array_values($parameters);

        $skippedValue = false;

        foreach ($reflector->getParameters() as $key => $parameter) {
            $instance = $this->transformDependency($parameter, $parameters, $skippedValue);

            if (!is_null($instance)) {
                $instanceCount++;

                $this->spliceIntoParameters($parameters, $key, $instance);
            } elseif (!isset($values[$key - $instanceCount]) &&
                      $parameter->isDefaultValueAvailable()) {
                $this->spliceIntoParameters($parameters, $key, $parameter->getDefaultValue());
            }
        }

        return $parameters;
    }

    /**
     * Attempt to transform the given parameter into a class instance.
     */
    protected function transformDependency(\ReflectionParameter $parameter, array $parameters, bool &$skippedValue): mixed
    {
        $className = $this->getParameterClassName($parameter);

        if ($className && !$this->alreadyInParameters($className, $parameters)) {
            return $parameter->isDefaultValueAvailable()
                ? $parameter->getDefaultValue()
                : $this->container->make($className);
        }

        return null;
    }

    /**
     * Get the class name of the given parameter's type, if possible.
     */
    protected function getParameterClassName(\ReflectionParameter $parameter): ?string
    {
        $type = $parameter->getType();

        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        $name = $type->getName();

        if (!is_null($class = $parameter->getDeclaringClass())) {
            if ($name === 'self') {
                return $class->getName();
            }

            if ($name === 'parent' && $parent = $class->getParentClass()) {
                return $parent->getName();
            }
        }

        return $name;
    }

    /**
     * Determine if an object of the given class is in the list of parameters.
     */
    protected function alreadyInParameters(string $class, array $parameters): bool
    {
        foreach ($parameters as $value) {
            if ($value instanceof $class) {
                return true;
            }
        }
        return false;
    }

    /**
     * Splice the given value into the parameter list.
     */
    protected function spliceIntoParameters(array &$parameters, int $offset, mixed $value): void
    {
        array_splice(
            $parameters, $offset, 0, [$value]
        );
    }

    /**
     * Handle the exception thrown while running the route.
     */
    protected function handleRunException(\Throwable $e): ResponseInterface
    {
        throw $e;
    }

    /**
     * Prepare the response instance.
     */
    public function prepareResponse(mixed $response): ResponseInterface
    {
        return static::toResponse($response);
    }

    /**
     * Static version of prepareResponse.
     */
    public static function toResponse(mixed $response): ResponseInterface
    {
        if ($response instanceof ResponseInterface) {
            return $response;
        }

        if (is_string($response)) {
            return new Response($response);
        }

        if (is_array($response) || is_object($response)) {
            return (new Response())->json($response);
        }

        return new Response((string) $response);
    }

    /**
     * Set the container instance on the route.
     */
    public function setContainer(ApplicationInterface $container): static
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Set the router instance on the route.
     */
    public function setRouter(Router $router): static
    {
        $this->router = $router;

        return $this;
    }

    /**
     * Set the default values for the route.
     */
    public function defaults(array $defaults): static
    {
        $this->defaults = $defaults;

        return $this;
    }

    /**
     * Set a default value for the route.
     */
    public function default(string $key, mixed $value): static
    {
        $this->defaults[$key] = $value;

        return $this;
    }

    /**
     * Set a regular expression requirement on the route.
     */
    public function where(array|string $name, ?string $expression = null): static
    {
        foreach ($this->parseWhere($name, $expression) as $name => $expression) {
            $this->wheres[$name] = $expression;
        }

        return $this;
    }

    /**
     * Parse arguments to the where method into an array.
     */
    protected function parseWhere(array|string $name, ?string $expression): array
    {
        return is_array($name) ? $name : [$name => $expression];
    }

    /**
     * Get the regular expression requirements.
     */
    public function wheres(): array
    {
        return $this->wheres;
    }

    /**
     * Set a regular expression requirement on the route.
     */
    public function whereNumber(array|string $parameters): static
    {
        return $this->assignExpressionToParameters($parameters, '[0-9]+');
    }

    /**
     * Set a regular expression requirement on the route.
     */
    public function whereAlpha(array|string $parameters): static
    {
        return $this->assignExpressionToParameters($parameters, '[a-zA-Z]+');
    }

    /**
     * Set a regular expression requirement on the route.
     */
    public function whereAlphaNumeric(array|string $parameters): static
    {
        return $this->assignExpressionToParameters($parameters, '[a-zA-Z0-9]+');
    }

    /**
     * Set a regular expression requirement on the route.
     */
    public function whereUuid(array|string $parameters): static
    {
        return $this->assignExpressionToParameters($parameters, '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');
    }

    /**
     * Set a regular expression requirement on the route.
     */
    public function whereUlid(array|string $parameters): static
    {
        return $this->assignExpressionToParameters($parameters, '[0-7][0-9A-HJKMNP-TV-Z]{25}');
    }

    /**
     * Set a regular expression requirement on the route.
     */
    public function whereIn(string $parameter, array $values): static
    {
        return $this->where($parameter, implode('|', $values));
    }

    /**
     * Assign the given expression to the given parameters.
     */
    protected function assignExpressionToParameters(array|string $parameters, string $expression): static
    {
        $parametersArray = Arr::wrap($parameters);
        $assignments = [];
        
        foreach ($parametersArray as $parameter) {
            $assignments[$parameter] = $expression;
        }
        
        return $this->where($assignments);
    }

    /**
     * Mark this route as a fallback route.
     */
    public function fallback(): static
    {
        $this->isFallback = true;

        return $this;
    }

    /**
     * Specify that this route should not be subject to implicit model binding.
     */
    public function withoutMiddleware(string|array $middleware): static
    {
        $middleware = (array) $middleware;
        
        $this->action['excluded_middleware'] = array_merge(
            (array) ($this->action['excluded_middleware'] ?? []),
            $middleware
        );

        return $this;
    }

    /**
     * Specify the binding field for a parameter.
     */
    public function scopeBindings(): static
    {
        $this->action['scope_bindings'] = true;

        return $this;
    }

    /**
     * Specify that the route should not automatically scope bindings.
     */
    public function withoutScopedBindings(): static
    {
        $this->action['scope_bindings'] = false;

        return $this;
    }

    /**
     * Get the domain defined for the route.
     */
    public function getDomain(): ?string
    {
        return $this->domain();
    }

    /**
     * Compile the route into a CompiledRoute instance.
     */
    public function compileRoute(): CompiledRoute
    {
        if (!$this->compiled) {
            $this->compiled = (new RouteCompiler($this))->compile();
        }

        return $this->compiled;
    }

    /**
     * Get the compiled version of the route.
     */
    public function getCompiled(): CompiledRoute
    {
        return $this->compileRoute();
    }

    /**
     * Set the compiled route instance.
     */
    public function setCompiledRoute(CompiledRoute $compiled): static
    {
        $this->compiled = $compiled;

        return $this;
    }

    /**
     * Get the host regex for the route.
     */
    public function getHostRegex(): ?string
    {
        return $this->getCompiled()->getHostRegex();
    }

    /**
     * Convert the route to its string representation.
     */
    public function __toString(): string
    {
        return '{' . implode(', ', $this->methods()) . '} ' . $this->uri();
    }
}