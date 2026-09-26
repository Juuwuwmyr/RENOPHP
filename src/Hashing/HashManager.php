<?php

namespace Reno\Hashing;

use RuntimeException;

/**
 * HashManager
 * 
 * Manages password hashing with secure algorithms.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Secure by default (bcrypt/argon2)
 * - Clear API
 * - Explicit algorithm selection
 * - Automatic rehashing when needed
 * 
 * Security:
 * - Uses password_hash() (secure)
 * - Automatic salt generation
 * - Cost/work factor configuration
 * - Timing-safe verification
 * - Rehashing for security updates
 */
class HashManager
{
    /**
     * The default hasher driver
     *
     * @var string
     */
    protected string $driver = 'bcrypt';

    /**
     * The hasher instances
     *
     * @var array
     */
    protected array $hashers = [];

    /**
     * Create a new hash manager instance
     *
     * @param string $driver
     */
    public function __construct(string $driver = 'bcrypt')
    {
        $this->driver = $driver;
    }

    /**
     * Get a hasher instance
     *
     * @param string|null $driver
     * @return HasherContract
     */
    public function driver(?string $driver = null): HasherContract
    {
        $driver = $driver ?? $this->driver;

        if (!isset($this->hashers[$driver])) {
            $this->hashers[$driver] = $this->createDriver($driver);
        }

        return $this->hashers[$driver];
    }

    /**
     * Create a hasher driver
     *
     * @param string $driver
     * @return HasherContract
     * @throws RuntimeException
     */
    protected function createDriver(string $driver): HasherContract
    {
        return match ($driver) {
            'bcrypt' => new BcryptHasher(),
            'argon2i' => new Argon2IdHasher(['algorithm' => PASSWORD_ARGON2I]),
            'argon2id' => new Argon2IdHasher(['algorithm' => PASSWORD_ARGON2ID]),
            'argon' => new Argon2IdHasher(),
            default => throw new RuntimeException("Unsupported hashing driver [{$driver}].")
        };
    }

    /**
     * Hash the given value
     *
     * @param string $value
     * @param array $options
     * @return string
     */
    public function make(string $value, array $options = []): string
    {
        return $this->driver()->make($value, $options);
    }

    /**
     * Check the given plain value against a hash
     *
     * @param string $value
     * @param string $hashedValue
     * @param array $options
     * @return bool
     */
    public function check(string $value, string $hashedValue, array $options = []): bool
    {
        return $this->driver()->check($value, $hashedValue, $options);
    }

    /**
     * Check if the given hash has been hashed using the given options
     *
     * @param string $hashedValue
     * @param array $options
     * @return bool
     */
    public function needsRehash(string $hashedValue, array $options = []): bool
    {
        return $this->driver()->needsRehash($hashedValue, $options);
    }

    /**
     * Get information about the given hashed value
     *
     * @param string $hashedValue
     * @return array
     */
    public function info(string $hashedValue): array
    {
        return password_get_info($hashedValue);
    }

    /**
     * Set the default driver name
     *
     * @param string $driver
     * @return void
     */
    public function setDefaultDriver(string $driver): void
    {
        $this->driver = $driver;
    }

    /**
     * Get the default driver name
     *
     * @return string
     */
    public function getDefaultDriver(): string
    {
        return $this->driver;
    }

    /**
     * Dynamically call the default driver instance
     *
     * @param string $method
     * @param array $parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters)
    {
        return $this->driver()->{$method}(...$parameters);
    }
}
