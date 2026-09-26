<?php

namespace Horizon\Hashing;

use RuntimeException;

/**
 * Argon2IdHasher
 * 
 * Argon2 password hasher (modern, memory-hard algorithm).
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Uses PASSWORD_ARGON2ID or PASSWORD_ARGON2I
 * - Memory-hard (resistant to GPU attacks)
 * - Configurable memory, time, threads
 * 
 * Security:
 * - Argon2id recommended (hybrid mode)
 * - Resistant to side-channel attacks
 * - Resistant to GPU cracking
 * - Winner of Password Hashing Competition (2015)
 * 
 * Default Parameters:
 * - Memory: 65536 KB (64 MB)
 * - Time: 4 iterations
 * - Threads: 1
 * 
 * Requirements: PHP 7.2+ with Argon2 support
 */
class Argon2IdHasher implements HasherContract
{
    /**
     * The algorithm to use
     *
     * @var int
     */
    protected int $algorithm = PASSWORD_ARGON2ID;

    /**
     * The default memory cost
     *
     * @var int
     */
    protected int $memory = 65536; // 64 MB

    /**
     * The default time cost
     *
     * @var int
     */
    protected int $time = 4;

    /**
     * The default thread count
     *
     * @var int
     */
    protected int $threads = 1;

    /**
     * Create a new Argon2 hasher
     *
     * @param array $options
     * @throws RuntimeException
     */
    public function __construct(array $options = [])
    {
        $this->algorithm = $options['algorithm'] ?? $this->algorithm;
        $this->memory = $options['memory'] ?? $this->memory;
        $this->time = $options['time'] ?? $this->time;
        $this->threads = $options['threads'] ?? $this->threads;

        $this->verifyAlgorithmSupport();
    }

    /**
     * Verify that Argon2 is supported
     *
     * @return void
     * @throws RuntimeException
     */
    protected function verifyAlgorithmSupport(): void
    {
        if (!defined('PASSWORD_ARGON2ID') && !defined('PASSWORD_ARGON2I')) {
            throw new RuntimeException('Argon2 hashing is not supported on this system.');
        }

        if ($this->algorithm === PASSWORD_ARGON2ID && !defined('PASSWORD_ARGON2ID')) {
            throw new RuntimeException('Argon2id hashing is not supported. Using Argon2i instead.');
        }
    }

    /**
     * Hash the given value
     *
     * @param string $value
     * @param array $options
     * @return string
     * @throws RuntimeException
     */
    public function make(string $value, array $options = []): string
    {
        $hash = password_hash($value, $this->algorithm, [
            'memory_cost' => $this->memory($options),
            'time_cost' => $this->time($options),
            'threads' => $this->threads($options),
        ]);

        if ($hash === false) {
            throw new RuntimeException('Argon2 hashing not supported.');
        }

        return $hash;
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
        if (strlen($hashedValue) === 0) {
            return false;
        }

        return password_verify($value, $hashedValue);
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
        return password_needs_rehash($hashedValue, $this->algorithm, [
            'memory_cost' => $this->memory($options),
            'time_cost' => $this->time($options),
            'threads' => $this->threads($options),
        ]);
    }

    /**
     * Set the default memory cost
     *
     * @param int $memory
     * @return $this
     */
    public function setMemory(int $memory): self
    {
        $this->memory = $memory;

        return $this;
    }

    /**
     * Set the default time cost
     *
     * @param int $time
     * @return $this
     */
    public function setTime(int $time): self
    {
        $this->time = $time;

        return $this;
    }

    /**
     * Set the default thread count
     *
     * @param int $threads
     * @return $this
     */
    public function setThreads(int $threads): self
    {
        $this->threads = $threads;

        return $this;
    }

    /**
     * Extract the memory cost value from the options array
     *
     * @param array $options
     * @return int
     */
    protected function memory(array $options = []): int
    {
        return $options['memory'] ?? $this->memory;
    }

    /**
     * Extract the time cost value from the options array
     *
     * @param array $options
     * @return int
     */
    protected function time(array $options = []): int
    {
        return $options['time'] ?? $this->time;
    }

    /**
     * Extract the thread count value from the options array
     *
     * @param array $options
     * @return int
     */
    protected function threads(array $options = []): int
    {
        return $options['threads'] ?? $this->threads;
    }
}
