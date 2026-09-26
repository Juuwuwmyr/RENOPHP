<?php

namespace Reno\Auth\Contracts;

/**
 * Authenticatable
 * 
 * Contract for authenticatable entities (typically User models).
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Clear contract definition
 * - Explicit requirements
 * - No hidden assumptions
 */
interface Authenticatable
{
    /**
     * Get the unique identifier for the user
     *
     * @return mixed
     */
    public function getAuthIdentifier();

    /**
     * Get the name of the unique identifier for the user
     *
     * @return string
     */
    public function getAuthIdentifierName(): string;

    /**
     * Get the password for the user
     *
     * @return string
     */
    public function getAuthPassword(): string;

    /**
     * Get the "remember me" token value
     *
     * @return string|null
     */
    public function getRememberToken(): ?string;

    /**
     * Set the "remember me" token value
     *
     * @param string $value
     * @return void
     */
    public function setRememberToken(string $value): void;

    /**
     * Get the column name for the "remember me" token
     *
     * @return string
     */
    public function getRememberTokenName(): string;

    /**
     * Get the email address where password reset links should be sent
     *
     * @return string
     */
    public function getEmailForPasswordReset(): string;
}
