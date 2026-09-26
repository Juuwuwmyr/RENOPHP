<?php

namespace Horizon\Auth;

/**
 * Authenticatable Trait
 * 
 * Provides default implementation of Authenticatable contract for Eloquent models.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Simple trait for models
 * - Sensible defaults
 * - Easy to customize
 * 
 * Usage:
 *   class User extends Model
 *   {
 *       use Authenticatable;
 *   }
 */
trait Authenticatable
{
    /**
     * Get the unique identifier for the user
     *
     * @return mixed
     */
    public function getAuthIdentifier()
    {
        return $this->{$this->getAuthIdentifierName()};
    }

    /**
     * Get the name of the unique identifier for the user
     *
     * @return string
     */
    public function getAuthIdentifierName(): string
    {
        return $this->getKeyName();
    }

    /**
     * Get the password for the user
     *
     * @return string
     */
    public function getAuthPassword(): string
    {
        return $this->password;
    }

    /**
     * Get the "remember me" token value
     *
     * @return string|null
     */
    public function getRememberToken(): ?string
    {
        if (!empty($this->getRememberTokenName())) {
            return (string) $this->{$this->getRememberTokenName()};
        }

        return null;
    }

    /**
     * Set the "remember me" token value
     *
     * @param string $value
     * @return void
     */
    public function setRememberToken(string $value): void
    {
        if (!empty($this->getRememberTokenName())) {
            $this->{$this->getRememberTokenName()} = $value;
        }
    }

    /**
     * Get the column name for the "remember me" token
     *
     * @return string
     */
    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }

    /**
     * Get the email address where password reset links should be sent
     *
     * @return string
     */
    public function getEmailForPasswordReset(): string
    {
        return $this->email;
    }
}
