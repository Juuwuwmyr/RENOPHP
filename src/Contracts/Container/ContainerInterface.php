<?php

declare(strict_types=1);

namespace Horizon\Contracts\Container;

use Closure;
use Psr\Container\ContainerInterface as PsrContainerInterface;

interface ContainerInterface extends PsrContainerInterface
{
    /**
     * Bind an abstract type to a concrete implementation.
     */
    public function bind(string $abstract, mixed $concrete = null, bool $shared = false): void;

    /**
     * Bind an abstract type as a singleton.
     */
    public function singleton(string $abstract, mixed $concrete = null): void;

    /**
     * Bind an existing instance as shared in the container.
     */
    public function instance(string $abstract, object $instance): object;

    /**
     * Resolve the given type from the container.
     */
    public function make(string $abstract, array $parameters = []): mixed;

    /**
     * Determine if the given abstract type has been bound.
     */
    public function bound(string $abstract): bool;

    /**
     * Determine if the given abstract type has been resolved.
     */
    public function resolved(string $abstract): bool;

    /**
     * Register a binding if it hasn't already been registered.
     */
    public function bindIf(string $abstract, mixed $concrete = null, bool $shared = false): void;

    /**
     * Alias a type to a different name.
     */
    public function alias(string $abstract, string $alias): void;

    /**
     * Assign a set of tags to a given binding.
     */
    public function tag(array|string $abstracts, array|string $tags): void;

    /**
     * Resolve all of the bindings for a given tag.
     */
    public function tagged(string $tag): iterable;

    /**
     * Register a contextual binding.
     */
    public function when(string $concrete): ContextualBindingBuilder;

    /**
     * Get the container's bindings.
     */
    public function getBindings(): array;

    /**
     * Flush the container of all bindings and resolved instances.
     */
    public function flush(): void;
}

interface ContextualBindingBuilder
{
    /**
     * Define the abstract target that depends on the context.
     */
    public function needs(string $abstract): ContextualBindingBuilder;

    /**
     * Define the implementation that should be used for the given context.
     */
    public function give(mixed $implementation): void;

    /**
     * Define tagged services to be used for the given context.
     */
    public function giveTagged(string $tag): void;
}