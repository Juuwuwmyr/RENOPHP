<?php

namespace Horizon\Auth\Contracts;

/**
 * Guard
 * 
 * Contract for authentication guards.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Clear authentication interface
 * - State management
 * - Explicit check/get pattern
 */
interface Guard
{
    /**
     * Determine if the current user is authenticated
     *
     * @return bool
     */
    public function check(): bool;

    /**
     * Determine if the current user is a guest
     *
     * @return bool
     */
    public function guest(): bool;

    /**
     * Get the currently authenticated user
     *
     * @return Authenticatable|null
     */
    public function user(): ?Authenticatable;

    /**
     * Get the ID for the currently authenticated user
     *
     * @return mixed
     */
    public function id();

    /**
     * Validate a user's credentials
     *
     * @param array $credentials
     * @return bool
     */
    public function validate(array $credentials = []): bool;

    /**
     * Set the current user
     *
     * @param Authenticatable $user
     * @return void
     */
    public function setUser(Authenticatable $user): void;
}
