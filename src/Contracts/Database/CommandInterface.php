<?php

declare(strict_types=1);

namespace Reno\Contracts\Database;

/**
 * Command Interface
 * 
 * Defines the contract for schema blueprint commands.
 */
interface CommandInterface
{
    /**
     * Get the name of the command.
     */
    public function getName(): string;

    /**
     * Get the parameters of the command.
     */
    public function getParameters(): array;

    /**
     * Get a parameter value.
     */
    public function getParameter(string $key, mixed $default = null): mixed;

    /**
     * Set a parameter value.
     */
    public function setParameter(string $key, mixed $value): void;

    /**
     * Check if a parameter exists.
     */
    public function hasParameter(string $key): bool;
}