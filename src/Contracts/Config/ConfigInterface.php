<?php

declare(strict_types=1);

namespace Horizon\Contracts\Config;

interface ConfigInterface
{
    /**
     * Get a configuration value.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Set a configuration value.
     */
    public function set(string $key, mixed $value): void;

    /**
     * Determine if a configuration option exists.
     */
    public function has(string $key): bool;

    /**
     * Get all configuration data.
     */
    public function all(): array;

    /**
     * Get a required configuration value.
     * 
     * @throws \Horizon\Exceptions\ConfigurationException
     */
    public function required(string $key): mixed;

    /**
     * Prepend a value to an array configuration value.
     */
    public function prepend(string $key, mixed $value): void;

    /**
     * Push a value to an array configuration value.
     */
    public function push(string $key, mixed $value): void;
}