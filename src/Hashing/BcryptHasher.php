<?php

namespace Reno\Hashing;

use RuntimeException;

/**
 * BcryptHasher
 * 
 * Bcrypt password hasher (industry standard).
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Uses PASSWORD_BCRYPT algorithm
 * - Automatic salt generation
 * - Configurable work factor (cost)
 * 
 * Security:
 * - Default cost: 10 (balanced security/performance)
 * - Uses password_hash() (secure)
 * - Timing-safe verification
 * - Automatic salt handling
 * 
 * Performance:
 * - Cost 10: ~100ms per hash
 * - Cost 12: ~400ms per hash
 * - Cost 14: ~1.6s per hash
 * 
 * Recommendation: Use cost 10-12 for most applications
 */
class BcryptHasher implements HasherContract
{
    /**
     * The default cost factor
     *
     * @var int
     */
    protected int $cost = 10;

    /**
     * Create a new Bcrypt hasher
     *
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->cost = $options['cost'] ?? $this->cost;
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
        $hash = password_hash($value, PASSWORD_BCRYPT, [
            'cost' => $this->cost($options),
        ]);

        if ($hash === false) {
            throw new RuntimeException('Bcrypt hashing not supported.');
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
        return password_needs_rehash($hashedValue, PASSWORD_BCRYPT, [
            'cost' => $this->cost($options),
        ]);
    }

    /**
     * Set the default cost factor
     *
     * @param int $cost
     * @return $this
     */
    public function setCost(int $cost): self
    {
        $this->cost = $cost;

        return $this;
    }

    /**
     * Extract the cost value from the options array
     *
     * @param array $options
     * @return int
     */
    protected function cost(array $options = []): int
    {
        return $options['cost'] ?? $this->cost;
    }
}
