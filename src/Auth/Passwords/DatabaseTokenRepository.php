<?php

namespace Horizon\Auth\Passwords;

use Horizon\Auth\Contracts\Authenticatable;
use Horizon\Database\Connection;

/**
 * DatabaseTokenRepository
 * 
 * Database-backed password reset token repository.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Stores tokens in database
 * - Cryptographically secure tokens
 * - Automatic expiration
 * - One-time use
 * 
 * Security:
 * - Tokens are hashed before storage
 * - Configurable expiration time
 * - Timing-safe comparison
 * - Automatic cleanup of expired tokens
 */
class DatabaseTokenRepository implements TokenRepositoryInterface
{
    /**
     * The database connection
     *
     * @var Connection
     */
    protected Connection $connection;

    /**
     * The token database table
     *
     * @var string
     */
    protected string $table;

    /**
     * The hashing key
     *
     * @var string
     */
    protected string $hashKey;

    /**
     * The number of seconds a token should last
     *
     * @var int
     */
    protected int $expires;

    /**
     * Create a new token repository instance
     *
     * @param Connection $connection
     * @param string $table
     * @param string $hashKey
     * @param int $expires
     */
    public function __construct(
        Connection $connection,
        string $table,
        string $hashKey,
        int $expires = 3600
    ) {
        $this->connection = $connection;
        $this->table = $table;
        $this->hashKey = $hashKey;
        $this->expires = $expires;
    }

    /**
     * Create a new token record
     *
     * @param Authenticatable $user
     * @return string
     */
    public function create(Authenticatable $user): string
    {
        $email = $user->getEmailForPasswordReset();

        // Delete existing tokens
        $this->deleteExisting($user);

        // Generate a new token
        $token = $this->createNewToken();

        // Insert the token
        $this->getTable()->insert([
            'email' => $email,
            'token' => hash_hmac('sha256', $token, $this->hashKey),
            'created_at' => time(),
        ]);

        return $token;
    }

    /**
     * Delete existing tokens for the user
     *
     * @param Authenticatable $user
     * @return void
     */
    protected function deleteExisting(Authenticatable $user): void
    {
        $this->getTable()
            ->where('email', $user->getEmailForPasswordReset())
            ->delete();
    }

    /**
     * Create a new token for the user
     *
     * @return string
     */
    public function createNewToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Determine if a token record exists and is valid
     *
     * @param Authenticatable $user
     * @param string $token
     * @return bool
     */
    public function exists(Authenticatable $user, string $token): bool
    {
        $record = $this->getTable()
            ->where('email', $user->getEmailForPasswordReset())
            ->first();

        if (!$record) {
            return false;
        }

        // Check if token is expired
        if ($this->tokenExpired($record['created_at'])) {
            return false;
        }

        // Timing-safe token comparison
        return hash_equals(
            $record['token'],
            hash_hmac('sha256', $token, $this->hashKey)
        );
    }

    /**
     * Determine if the token has expired
     *
     * @param int $createdAt
     * @return bool
     */
    protected function tokenExpired(int $createdAt): bool
    {
        return time() - $createdAt > $this->expires;
    }

    /**
     * Delete a token record by user
     *
     * @param Authenticatable $user
     * @return void
     */
    public function delete(Authenticatable $user): void
    {
        $this->deleteExisting($user);
    }

    /**
     * Delete expired tokens
     *
     * @return void
     */
    public function deleteExpired(): void
    {
        $expiredAt = time() - $this->expires;

        $this->getTable()
            ->where('created_at', '<', $expiredAt)
            ->delete();
    }

    /**
     * Get the database query builder for the table
     *
     * @return \Horizon\Database\Query\QueryBuilder
     */
    protected function getTable()
    {
        return $this->connection->table($this->table);
    }

    /**
     * Get the database connection
     *
     * @return Connection
     */
    public function getConnection(): Connection
    {
        return $this->connection;
    }
}
