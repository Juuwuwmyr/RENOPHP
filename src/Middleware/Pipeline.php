<?php

declare(strict_types=1);

namespace Reno\Middleware;

use Reno\Http\Request;
use Reno\Http\Response;
use Reno\Middleware\MiddlewareInterface;
use Closure;
use InvalidArgumentException;
use ReflectionClass;

/**
 * Pipeline
 * 
 * Middleware pipeline that processes requests through a chain of middleware.
 * Supports both class-based and closure-based middleware with parameter passing.
 */
class Pipeline
{
    /**
     * The object being passed through the pipeline.
     */
    protected Request $passable;

    /**
     * The array of middleware to run.
     */
    protected array $pipes = [];

    /**
     * The method to call on each middleware.
     */
    protected string $method = 'handle';

    /**
     * Additional parameters to pass to middleware.
     */
    protected array $parameters = [];

    /**
     * Middleware resolver.
     */
    protected ?Closure $resolver = null;

    /**
     * Error handler for middleware exceptions.
     */
    protected ?Closure $errorHandler = null;

    /**
     * Create a new Pipeline instance.
     */
    public function __construct(?Closure $resolver = null)
    {
        $this->resolver = $resolver;
    }

    /**
     * Set the object being sent through the pipeline.
     */
    public function send(Request $passable): static
    {
        $this->passable = $passable;
        return $this;
    }

    /**
     * Set the array of pipes.
     */
    public function through(array $pipes): static
    {
        $this->pipes = $pipes;
        return $this;
    }

    /**
     * Add a single pipe to the pipeline.
     */
    public function pipe(mixed $pipe): static
    {
        $this->pipes[] = $pipe;
        return $this;
    }

    /**
     * Set the method to call on the pipes.
     */
    public function via(string $method): static
    {
        $this->method = $method;
        return $this;
    }

    /**
     * Set additional parameters to pass to middleware.
     */
    public function with(array $parameters): static
    {
        $this->parameters = $parameters;
        return $this;
    }

    /**
     * Set error handler for middleware exceptions.
     */
    public function onError(Closure $handler): static
    {
        $this->errorHandler = $handler;
        return $this;
    }

    /**
     * Run the pipeline with a final destination.
     */
    public function then(Closure $destination): mixed
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes), 
            $this->carry(), 
            $this->prepareDestination($destination)
        );

        return $pipeline($this->passable);
    }

    /**
     * Run the pipeline and return the result.
     */
    public function thenReturn(): mixed
    {
        return $this->then(function (Request $passable) {
            return $passable;
        });
    }

    /**
     * Get the final piece of the Closure onion.
     */
    protected function prepareDestination(Closure $destination): Closure
    {
        return function (Request $passable) use ($destination) {
            try {
                return $destination($passable);
            } catch (\Throwable $e) {
                return $this->handleException($passable, $e);
            }
        };
    }

    /**
     * Get a Closure that represents a slice of the application onion.
     */
    protected function carry(): Closure
    {
        return function (Closure $stack, mixed $pipe) {
            return function (Request $passable) use ($stack, $pipe) {
                try {
                    if (is_callable($pipe)) {
                        // Closure middleware
                        return $pipe($passable, $stack, ...$this->parameters);
                    } elseif (is_object($pipe)) {
                        // Object middleware
                        return $this->callMiddleware($pipe, $passable, $stack);
                    } elseif (is_string($pipe)) {
                        // Class name or alias
                        $resolved = $this->resolvePipe($pipe);
                        return $this->callMiddleware($resolved, $passable, $stack);
                    } else {
                        throw new InvalidArgumentException('Invalid middleware type: ' . gettype($pipe));
                    }
                } catch (\Throwable $e) {
                    return $this->handleException($passable, $e);
                }
            };
        };
    }

    /**
     * Call middleware method.
     */
    protected function callMiddleware(object $middleware, Request $passable, Closure $stack): mixed
    {
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware->handle($passable, $stack);
        }

        if (method_exists($middleware, $this->method)) {
            return $middleware->{$this->method}($passable, $stack, ...$this->parameters);
        }

        throw new InvalidArgumentException(
            'Middleware ' . get_class($middleware) . ' does not implement MiddlewareInterface or have method: ' . $this->method
        );
    }

    /**
     * Resolve a pipe string to an object.
     */
    protected function resolvePipe(string $pipe): object
    {
        // Parse middleware with parameters
        [$name, $parameters] = $this->parsePipeString($pipe);

        // Use resolver if available
        if ($this->resolver) {
            $resolved = ($this->resolver)($name);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        // Fallback to class instantiation
        if (class_exists($name)) {
            return new $name(...$parameters);
        }

        throw new InvalidArgumentException("Unable to resolve middleware: {$name}");
    }

    /**
     * Parse pipe string to extract name and parameters.
     */
    protected function parsePipeString(string $pipe): array
    {
        if (str_contains($pipe, ':')) {
            [$name, $paramString] = explode(':', $pipe, 2);
            $parameters = explode(',', $paramString);
            return [trim($name), array_map('trim', $parameters)];
        }

        return [trim($pipe), []];
    }

    /**
     * Handle middleware exception.
     */
    protected function handleException(Request $passable, \Throwable $exception): mixed
    {
        if ($this->errorHandler) {
            return ($this->errorHandler)($passable, $exception);
        }

        throw $exception;
    }

    // ====================================================================
    // Static Factory Methods
    // ====================================================================

    /**
     * Create a new pipeline instance.
     */
    public static function create(?Closure $resolver = null): static
    {
        return new static($resolver);
    }

    /**
     * Create and run a pipeline in one call.
     */
    public static function run(Request $request, array $middleware, Closure $destination, ?Closure $resolver = null): mixed
    {
        return static::create($resolver)
            ->send($request)
            ->through($middleware)
            ->then($destination);
    }

    // ====================================================================
    // Utility Methods
    // ====================================================================

    /**
     * Get current pipes.
     */
    public function getPipes(): array
    {
        return $this->pipes;
    }

    /**
     * Get pipe count.
     */
    public function count(): int
    {
        return count($this->pipes);
    }

    /**
     * Check if pipeline is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->pipes);
    }

    /**
     * Clear all pipes.
     */
    public function clear(): static
    {
        $this->pipes = [];
        return $this;
    }

    /**
     * Clone pipeline.
     */
    public function __clone()
    {
        // Deep clone if needed
    }
}