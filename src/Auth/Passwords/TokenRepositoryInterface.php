<?php

namespace Reno\Auth\Passwords;

use Reno\Auth\Contracts\Authenticatable;

/**
 * TokenRepositoryInterface
 * 
 * Contract for password reset token storage.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Clear token lifecycle
 * - Storage-agnostic
 * - Expiration support
 */
interface TokenRepositoryInterface
{
    /**
     * Create a new token
     *
     * @param Authenticatable $user
     * @return string
     */
    public function create(Authenticatable $user): string;

    /**
     * Determine if a token record exists and is valid
     *
     * @param Authenticatable $user
     * @param string $token
     * @return bool
     */
    public function exists(Authenticatable $user, string $token): bool;

    /**
     * Delete a token record
     *
     * @param Authenticatable $user
     * @return void
     */
    public function delete(Authenticatable $user): void;

    /**
     * Delete expired tokens
     *
     * @return void
     */
    public function deleteExpired(): void;
}
