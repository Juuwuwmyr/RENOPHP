<?php

declare(strict_types=1);

namespace Horizon\Database\Schema;

use Horizon\Contracts\Database\CommandInterface;

/**
 * Command
 * 
 * Represents a schema command to be executed.
 */
class Command implements CommandInterface
{
    /**
     * The name of the command.
     */
    public string $name;

    /**
     * The parameters for the command.
     */
    protected array $parameters;

    /**
     * Create a new schema command.
     */
    public function __construct(string $name, array $parameters = [])
    {
        $this->name = $name;
        $this->parameters = $parameters;
    }

    /**
     * Get the name of the command.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the parameters of the command.
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * Get a parameter value.
     */
    public function getParameter(string $key, mixed $default = null): mixed
    {
        return $this->parameters[$key] ?? $default;
    }

    /**
     * Set a parameter value.
     */
    public function setParameter(string $key, mixed $value): void
    {
        $this->parameters[$key] = $value;
    }

    /**
     * Check if a parameter exists.
     */
    public function hasParameter(string $key): bool
    {
        return array_key_exists($key, $this->parameters);
    }

    /**
     * Dynamically access command parameters.
     */
    public function __get(string $key): mixed
    {
        return $this->getParameter($key);
    }

    /**
     * Dynamically set command parameters.
     */
    public function __set(string $key, mixed $value): void
    {
        $this->setParameter($key, $value);
    }

    /**
     * Dynamically check if a parameter is set.
     */
    public function __isset(string $key): bool
    {
        return $this->hasParameter($key);
    }
}