<?php

namespace Reno\Auth\Providers;

use Reno\Auth\Contracts\Authenticatable;
use Reno\Auth\Contracts\UserProvider;

/**
 * EloquentUserProvider
 * 
 * User provider that retrieves users from database using Eloquent ORM.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Uses Eloquent for database queries
 * - Password verification via hasher
 * - Clear credential handling
 * 
 * Security:
 * - Uses password_verify() for secure comparison
 * - Timing-safe token comparison
 * - No password logging
 */
class EloquentUserProvider implements UserProvider
{
    /**
     * The Eloquent user model class name
     *
     * @var string
     */
    protected string $model;

    /**
     * Create a new Eloquent user provider
     *
     * @param string $model
     */
    public function __construct(string $model)
    {
        $this->model = $model;
    }

    /**
     * Retrieve a user by their unique identifier
     *
     * @param mixed $identifier
     * @return Authenticatable|null
     */
    public function retrieveById($identifier): ?Authenticatable
    {
        $model = $this->createModel();

        return $model->newQuery()
            ->where($model->getAuthIdentifierName(), $identifier)
            ->first();
    }

    /**
     * Retrieve a user by their unique identifier and "remember me" token
     *
     * @param mixed $identifier
     * @param string $token
     * @return Authenticatable|null
     */
    public function retrieveByToken($identifier, string $token): ?Authenticatable
    {
        $model = $this->createModel();

        $user = $model->newQuery()
            ->where($model->getAuthIdentifierName(), $identifier)
            ->first();

        if (!$user) {
            return null;
        }

        $rememberToken = $user->getRememberToken();

        // Timing-safe token comparison
        return $rememberToken && hash_equals($rememberToken, $token) ? $user : null;
    }

    /**
     * Update the "remember me" token for the given user in storage
     *
     * @param Authenticatable $user
     * @param string $token
     * @return void
     */
    public function updateRememberToken(Authenticatable $user, string $token): void
    {
        $user->setRememberToken($token);
        
        $timestamps = $user->timestamps;
        $user->timestamps = false;

        $user->save();

        $user->timestamps = $timestamps;
    }

    /**
     * Retrieve a user by the given credentials
     *
     * @param array $credentials
     * @return Authenticatable|null
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        if (empty($credentials)) {
            return null;
        }

        // Build the query from credentials, excluding password
        $query = $this->createModel()->newQuery();

        foreach ($credentials as $key => $value) {
            if (!str_contains($key, 'password')) {
                $query->where($key, $value);
            }
        }

        return $query->first();
    }

    /**
     * Validate a user against the given credentials
     *
     * @param Authenticatable $user
     * @param array $credentials
     * @return bool
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        $plain = $credentials['password'] ?? '';

        // Use password_verify for secure password checking
        return password_verify($plain, $user->getAuthPassword());
    }

    /**
     * Create a new instance of the model
     *
     * @return Authenticatable
     */
    protected function createModel(): Authenticatable
    {
        $class = '\\' . ltrim($this->model, '\\');

        return new $class;
    }

    /**
     * Gets the name of the Eloquent user model
     *
     * @return string
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Sets the name of the Eloquent user model
     *
     * @param string $model
     * @return $this
     */
    public function setModel(string $model): self
    {
        $this->model = $model;

        return $this;
    }
}
