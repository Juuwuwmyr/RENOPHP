<?php

namespace Reno\Auth\Contracts;

/**
 * StatefulGuard
 * 
 * Contract for stateful authentication guards (session-based).
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Extends base Guard
 * - Adds login/logout
 * - Remember me support
 */
interface StatefulGuard extends Guard
{
    /**
     * Attempt to authenticate a user using the given credentials
     *
     * @param array $credentials
     * @param bool $remember
     * @return bool
     */
    public function attempt(array $credentials = [], bool $remember = false): bool;

    /**
     * Log a user into the application without sessions or cookies
     *
     * @param array $credentials
     * @return bool
     */
    public function once(array $credentials = []): bool;

    /**
     * Log a user into the application
     *
     * @param Authenticatable $user
     * @param bool $remember
     * @return void
     */
    public function login(Authenticatable $user, bool $remember = false): void;

    /**
     * Log the given user ID into the application
     *
     * @param mixed $id
     * @param bool $remember
     * @return Authenticatable|false
     */
    public function loginUsingId($id, bool $remember = false);

    /**
     * Log the user out of the application
     *
     * @return void
     */
    public function logout(): void;
}
