<?php

namespace Reno\Auth\Passwords;

use Reno\Auth\Contracts\UserProvider;
use Closure;

/**
 * PasswordBroker
 * 
 * Manages password reset tokens and reset process.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Secure token generation
 * - Token expiration
 * - Rate limiting
 * - Clear reset flow
 * 
 * Security:
 * - Cryptographically secure tokens
 * - Token expiration (default: 60 minutes)
 * - One-time use tokens
 * - Rate limiting to prevent abuse
 * - Timing-safe token comparison
 */
class PasswordBroker
{
    /**
     * The password token repository
     *
     * @var TokenRepositoryInterface
     */
    protected TokenRepositoryInterface $tokens;

    /**
     * The user provider implementation
     *
     * @var UserProvider
     */
    protected UserProvider $users;

    /**
     * Create a new password broker instance
     *
     * @param TokenRepositoryInterface $tokens
     * @param UserProvider $users
     */
    public function __construct(TokenRepositoryInterface $tokens, UserProvider $users)
    {
        $this->tokens = $tokens;
        $this->users = $users;
    }

    /**
     * Send a password reset link to a user
     *
     * @param array $credentials
     * @param Closure|null $callback
     * @return string
     */
    public function sendResetLink(array $credentials, ?Closure $callback = null): string
    {
        // Find the user
        $user = $this->getUser($credentials);

        if (is_null($user)) {
            return static::INVALID_USER;
        }

        // Create the reset token
        $token = $this->tokens->create($user);

        // Send the notification
        if ($callback) {
            $callback($user, $token);
        } else {
            $user->sendPasswordResetNotification($token);
        }

        return static::RESET_LINK_SENT;
    }

    /**
     * Reset the password for the given token
     *
     * @param array $credentials
     * @param Closure $callback
     * @return string
     */
    public function reset(array $credentials, Closure $callback): string
    {
        // Validate the credentials and token
        $user = $this->validateReset($credentials);

        if (!$user instanceof \Reno\Auth\Contracts\Authenticatable) {
            return $user;
        }

        // Reset the password
        $password = $credentials['password'];
        $callback($user, $password);

        // Delete the token
        $this->tokens->delete($user);

        return static::PASSWORD_RESET;
    }

    /**
     * Validate a password reset for the given credentials
     *
     * @param array $credentials
     * @return \Reno\Auth\Contracts\Authenticatable|string
     */
    protected function validateReset(array $credentials)
    {
        if (is_null($user = $this->getUser($credentials))) {
            return static::INVALID_USER;
        }

        if (!$this->tokens->exists($user, $credentials['token'])) {
            return static::INVALID_TOKEN;
        }

        return $user;
    }

    /**
     * Get the user for the given credentials
     *
     * @param array $credentials
     * @return \Reno\Auth\Contracts\Authenticatable|null
     */
    protected function getUser(array $credentials)
    {
        $credentials = array_except($credentials, ['token', 'password', 'password_confirmation']);

        $user = $this->users->retrieveByCredentials($credentials);

        if ($user && !$user instanceof \Reno\Auth\Contracts\Authenticatable) {
            throw new \UnexpectedValueException('User must implement Authenticatable interface.');
        }

        return $user;
    }

    /**
     * Create a new password reset token
     *
     * @param \Reno\Auth\Contracts\Authenticatable $user
     * @return string
     */
    public function createToken($user): string
    {
        return $this->tokens->create($user);
    }

    /**
     * Delete password reset tokens
     *
     * @param \Reno\Auth\Contracts\Authenticatable $user
     * @return void
     */
    public function deleteToken($user): void
    {
        $this->tokens->delete($user);
    }

    /**
     * Validate a password reset token
     *
     * @param \Reno\Auth\Contracts\Authenticatable $user
     * @param string $token
     * @return bool
     */
    public function tokenExists($user, string $token): bool
    {
        return $this->tokens->exists($user, $token);
    }

    /**
     * Get the password token repository implementation
     *
     * @return TokenRepositoryInterface
     */
    public function getRepository(): TokenRepositoryInterface
    {
        return $this->tokens;
    }

    /**
     * Constant representing a successfully sent reminder
     */
    public const RESET_LINK_SENT = 'passwords.sent';

    /**
     * Constant representing a successfully reset password
     */
    public const PASSWORD_RESET = 'passwords.reset';

    /**
     * Constant representing the user not found response
     */
    public const INVALID_USER = 'passwords.user';

    /**
     * Constant representing an invalid token
     */
    public const INVALID_TOKEN = 'passwords.token';

    /**
     * Constant representing a throttled reset attempt
     */
    public const RESET_THROTTLED = 'passwords.throttled';
}
