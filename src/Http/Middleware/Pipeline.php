<?php

declare(strict_types=1);

namespace Horizon\Http\Middleware;

use Closure;
use Horizon\Contracts\Foundation\ApplicationInterface;
use Horizon\Contracts\Http\RequestInterface;
use Horizon\Contracts\Http\ResponseInterface;
use RuntimeException;

class Pipeline
{
    /**
     * The application instance.
     */
    protected ApplicationInterface $app;

    /**
     * The object being passed through the pipeline.
     */
    protected mixed $passable;

    /**
     * The array of class pipes.
     */
    protected array $pipes = [];

    /**
     * The method to call on each pipe.
     */
    protected string $method = 'handle';

    /**
     * Create a new class instance.
     */
    public function __construct(ApplicationInterface $app)
    {
        $this->app = $app;
    }

    /**
     * Set the object being sent through the pipeline.
     */
    public function send(mixed $passable): static
    {
        $this->passable = $passable;

        return $this;
    }

    /**
     * Set the array of pipes.
     */
    public function through(array $pipes): static
    {
        $this->pipes = is_array($pipes) ? $pipes : func_get_args();

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
     * Run the pipeline with a final destination callback.
     */
    public function then(Closure $destination): mixed
    {
        $pipeline = array_reduce(
            array_reverse($this->pipes()),
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
        return $this->then(function ($passable) {
            return $passable;
        });
    }

    /**
     * Get the final piece of the Closure onion.
     */
    protected function prepareDestination(Closure $destination): Closure
    {
        return function ($passable) use ($destination) {
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
        return function ($stack, $pipe) {
            return function ($passable) use ($stack, $pipe) {
                try {
                    if (is_callable($pipe)) {
                        // If the pipe is a callable, call it directly
                        return $pipe($passable, $stack);
                    } elseif (!is_object($pipe)) {
                        [$name, $parameters] = $this->parsePipeString($pipe);

                        // Resolve the pipe from the container
                        $pipe = $this->app->make($name);

                        $parameters = array_merge([$passable, $stack], $parameters);
                    } else {
                        // If we have an object, just use it
                        $parameters = [$passable, $stack];
                    }

                    $carry = method_exists($pipe, $this->method)
                        ? $pipe->{$this->method}(...$parameters)
                        : $pipe(...$parameters);

                    return $this->handleCarry($carry);
                } catch (\Throwable $e) {
                    return $this->handleException($passable, $e);
                }
            };
        };
    }

    /**
     * Parse full pipe string to get name and parameters.
     */
    protected function parsePipeString(string $pipe): array
    {
        [$name, $parameters] = array_pad(explode(':', $pipe, 2), 2, []);

        if (is_string($parameters)) {
            $parameters = explode(',', $parameters);
        }

        return [$name, $parameters];
    }

    /**
     * Get the array of configured pipes.
     */
    protected function pipes(): array
    {
        return $this->pipes;
    }

    /**
     * Handle the value returned from each pipe before passing it to the next.
     */
    protected function handleCarry(mixed $carry): mixed
    {
        return $carry;
    }

    /**
     * Handle the given exception.
     */
    protected function handleException(mixed $passable, \Throwable $e): mixed
    {
        throw $e;
    }
}