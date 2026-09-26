<?php

namespace Reno\Auth\Guards;

use Reno\Auth\Contracts\Authenticatable;
use Reno\Auth\Contracts\StatefulGuard;
use Reno\Auth\Contracts\UserProvider;

/**
 * SessionGuard
 * 
 * Session-based authentication guard.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Session storage for auth state
 * - Remember me token support
 * - Clear login/logout lifecycle
 * - Event-driven for extensibility
 * 
 * Security:
 * - Session regeneration on login
 * - Secure remember token generation
 * - Session fixation protection
 * - No password storage in session
 */
class SessionGuard implements StatefulGuard
{
    /**
     * The name of the guard
     *
     * @var string
     */
    protected string $name;

    /**
     * The user provider implementation
     *
     * @var UserProvider
     */
    protected UserProvider $provider;

    /**
     * The session store instance
     *
     * @var mixed
     */
    protected $session;

    /**
     * The currently authenticated user
     *
     * @var Authenticatable|null
     */
    protected ?Authenticatable $user = null;

    /**
     * Indicates if the user was authenticated via a remember cookie
     *
     * @var bool
     */
    protected bool $viaRemember = false;

    /**
     * Indicates if the logout method has been called
     *
     * @var bool
     */
    protected bool $loggedOut = false;

    /**
     * Create a new authentication guard
     *
     * @param string $name
     * @param UserProvider $provider
     * @param mixed $session
     */
    public function __construct(string $name, UserProvider $provider, $session = null)
    {
        $this->name = $name;
        $this->provider = $provider;
        $this->session = $session;
    }

    /**
     * Determine if the current user is authenticated
     *
     * @return bool
     */
    public function check(): bool
    {
        return !is_null($this->user());
    }

    /**
     * Determine if the current user is a guest
     *
     * @return bool
     */
    public function guest(): bool
    {
        return !$this->check();
    }

    /**
     * Get the currently authenticated user
     *
     * @return Authenticatable|null
     */
    public function user(): ?Authenticatable
    {
        if ($this->loggedOut) {
            return null;
        }

        // If we've already retrieved the user, return it
        if (!is_null($this->user)) {
            return $this->user;
        }

        // Get user ID from session
        $id = $this->session?->get($this->getName());

        if (!is_null($id)) {
            $this->user = $this->provider->retrieveById($id);
        }

        // If we didn't get a user from session, try remember cookie
        if (is_null($this->user)) {
            $this->user = $this->getUserFromRememberCookie();
        }

        return $this->user;
    }

    /**
     * Get the ID for the currently authenticated user
     *
     * @return mixed
     */
    public function id()
    {
        if ($this->loggedOut) {
            return null;
        }

        return $this->user()?->getAuthIdentifier() ?? $this->session?->get($this->getName());
    }

    /**
     * Validate a user's credentials
     *
     * @param array $credentials
     * @return bool
     */
    public function validate(array $credentials = []): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        return !is_null($user) && $this->provider->validateCredentials($user, $credentials);
    }

    /**
     * Attempt to authenticate a user using the given credentials
     *
     * @param array $credentials
     * @param bool $remember
     * @return bool
     */
    public function attempt(array $credentials = [], bool $remember = false): bool
    {
        $user = $this->provider->retrieveByCredentials($credentials);

        // If we found a user and the credentials are valid, log them in
        if ($this->hasValidCredentials($user, $credentials)) {
            $this->login($user, $remember);
            return true;
        }

        // Fire failed authentication event
        $this->fireFailedEvent($user, $credentials);

        return false;
    }

    /**
     * Determine if the user matches the credentials
     *
     * @param Authenticatable|null $user
     * @param array $credentials
     * @return bool
     */
    protected function hasValidCredentials(?Authenticatable $user, array $credentials): bool
    {
        return !is_null($user) && $this->provider->validateCredentials($user, $credentials);
    }

    /**
     * Log a user into the application without sessions or cookies
     *
     * @param array $credentials
     * @return bool
     */
    public function once(array $credentials = []): bool
    {
        if ($this->validate($credentials)) {
            $this->setUser($this->provider->retrieveByCredentials($credentials));
            return true;
        }

        return false;
    }

    /**
     * Log a user into the application
     *
     * @param Authenticatable $user
     * @param bool $remember
     * @return void
     */
    public function login(Authenticatable $user, bool $remember = false): void
    {
        $this->updateSession($user->getAuthIdentifier());

        // If remember me is enabled, create remember token
        if ($remember) {
            $this->createRememberToken($user);
        }

        // Fire login event
        $this->fireLoginEvent($user, $remember);

        $this->setUser($user);
    }

    /**
     * Log the given user ID into the application
     *
     * @param mixed $id
     * @param bool $remember
     * @return Authenticatable|false
     */
    public function loginUsingId($id, bool $remember = false)
    {
        $user = $this->provider->retrieveById($id);

        if (!is_null($user)) {
            $this->login($user, $remember);
            return $user;
        }

        return false;
    }

    /**
     * Log the user out of the application
     *
     * @return void
     */
    public function logout(): void
    {
        $user = $this->user();

        // Clear the remember token
        if (!is_null($user)) {
            $this->clearRememberToken($user);
        }

        // Clear session
        $this->clearUserDataFromStorage();

        // Fire logout event
        if (!is_null($user)) {
            $this->fireLogoutEvent($user);
        }

        // Reset user and state
        $this->user = null;
        $this->loggedOut = true;
    }

    /**
     * Update the session with the given ID
     *
     * @param string $id
     * @return void
     */
    protected function updateSession(string $id): void
    {
        $this->session?->put($this->getName(), $id);
        $this->session?->migrate(true); // Regenerate session ID (security)
    }

    /**
     * Clear user data from storage
     *
     * @return void
     */
    protected function clearUserDataFromStorage(): void
    {
        $this->session?->forget($this->getName());
        $this->session?->forget($this->getRememberName());
    }

    /**
     * Create a remember me token for the user
     *
     * @param Authenticatable $user
     * @return void
     */
    protected function createRememberToken(Authenticatable $user): void
    {
        $token = $this->generateRememberToken();
        
        $this->provider->updateRememberToken($user, $token);
        
        $this->session?->put($this->getRememberName(), $token);
    }

    /**
     * Generate a random remember token
     *
     * @return string
     */
    protected function generateRememberToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Get the user from the remember cookie
     *
     * @return Authenticatable|null
     */
    protected function getUserFromRememberCookie(): ?Authenticatable
    {
        $token = $this->session?->get($this->getRememberName());

        if (is_null($token)) {
            return null;
        }

        $user = $this->provider->retrieveByToken(
            $this->getRememberUserId(),
            $token
        );

        if (!is_null($user)) {
            $this->viaRemember = true;
        }

        return $user;
    }

    /**
     * Get the user ID from remember cookie
     *
     * @return mixed
     */
    protected function getRememberUserId()
    {
        return $this->session?->get($this->getName());
    }

    /**
     * Clear the remember token for the user
     *
     * @param Authenticatable $user
     * @return void
     */
    protected function clearRememberToken(Authenticatable $user): void
    {
        if (method_exists($user, 'setRememberToken')) {
            $user->setRememberToken('');
            $this->provider->updateRememberToken($user, '');
        }
    }

    /**
     * Set the current user
     *
     * @param Authenticatable $user
     * @return void
     */
    public function setUser(Authenticatable $user): void
    {
        $this->user = $user;
        $this->loggedOut = false;

        $this->fireAuthenticatedEvent($user);
    }

    /**
     * Get the name of the guard
     *
     * @return string
     */
    public function getName(): string
    {
        return 'login_' . $this->name . '_' . sha1(static::class);
    }

    /**
     * Get the remember me cookie name
     *
     * @return string
     */
    public function getRememberName(): string
    {
        return 'remember_' . $this->name . '_' . sha1(static::class);
    }

    /**
     * Determine if the user was authenticated via "remember me" cookie
     *
     * @return bool
     */
    public function viaRemember(): bool
    {
        return $this->viaRemember;
    }

    /**
     * Get the user provider used by the guard
     *
     * @return UserProvider
     */
    public function getProvider(): UserProvider
    {
        return $this->provider;
    }

    /**
     * Set the user provider used by the guard
     *
     * @param UserProvider $provider
     * @return void
     */
    public function setProvider(UserProvider $provider): void
    {
        $this->provider = $provider;
    }

    /**
     * Fire the login event
     *
     * @param Authenticatable $user
     * @param bool $remember
     * @return void
     */
    protected function fireLoginEvent(Authenticatable $user, bool $remember): void
    {
        // Event dispatching would go here
        // For now, we'll just log it
    }

    /**
     * Fire the authenticated event
     *
     * @param Authenticatable $user
     * @return void
     */
    protected function fireAuthenticatedEvent(Authenticatable $user): void
    {
        // Event dispatching would go here
    }

    /**
     * Fire the logout event
     *
     * @param Authenticatable $user
     * @return void
     */
    protected function fireLogoutEvent(Authenticatable $user): void
    {
        // Event dispatching would go here
    }

    /**
     * Fire the failed authentication attempt event
     *
     * @param Authenticatable|null $user
     * @param array $credentials
     * @return void
     */
    protected function fireFailedEvent(?Authenticatable $user, array $credentials): void
    {
        // Event dispatching would go here
    }
}
