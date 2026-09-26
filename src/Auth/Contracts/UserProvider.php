<?php

namespace Reno\Auth\Contracts;

/**
 * UserProvider
 * 
 * Contract for user providers that retrieve users from storage.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Clear retrieval interface
 * - Storage-agnostic
 * - Explicit credential validation
 */
interface UserProvider
{
    /**
     * Retrieve a user by their unique identifier
     *
     * @param mixed $identifier
     * @return Authenticatable|null
     */
    public function retrieveById($identifier): ?Authenticatable;

    /**
     * Retrieve a user by their unique identifier and "remember me" token
     *
     * @param mixed $identifier
     * @param string $token
     * @return Authenticatable|null
     */
    public function retrieveByToken($identifier, string $token): ?Authenticatable;

    /**
     * Update the "remember me" token for the given user in storage
     *
     * @param Authenticatable $user
     * @param string $token
     * @return void
     */
    public function updateRememberToken(Authenticatable $user, string $token): void;

    /**
     * Retrieve a user by the given credentials
     *
     * @param array $credentials
     * @return Authenticatable|null
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable;

    /**
     * Validate a user against the given credentials
     *
     * @param Authenticatable $user
     * @param array $credentials
     * @return bool
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool;
}
