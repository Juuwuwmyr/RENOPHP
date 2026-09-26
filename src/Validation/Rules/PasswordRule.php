<?php

namespace Reno\Validation\Rules;

use Reno\Validation\ValidationRule;
use Reno\Validation\Validator;

/**
 * PasswordRule
 * 
 * Validates password strength with configurable requirements.
 * 
 * Example:
 * ```php
 * $rule = new PasswordRule()
 *     ->min(8)
 *     ->requireUppercase()
 *     ->requireLowercase()
 *     ->requireNumbers()
 *     ->requireSpecialCharacters();
 * ```
 */
class PasswordRule implements ValidationRule
{
    /**
     * Minimum length
     *
     * @var int
     */
    protected int $minLength = 8;

    /**
     * Require uppercase letters
     *
     * @var bool
     */
    protected bool $requireUppercase = false;

    /**
     * Require lowercase letters
     *
     * @var bool
     */
    protected bool $requireLowercase = false;

    /**
     * Require numbers
     *
     * @var bool
     */
    protected bool $requireNumbers = false;

    /**
     * Require special characters
     *
     * @var bool
     */
    protected bool $requireSpecial = false;

    /**
     * Set minimum length
     *
     * @param int $length
     * @return $this
     */
    public function min(int $length): self
    {
        $this->minLength = $length;
        return $this;
    }

    /**
     * Require uppercase letters
     *
     * @return $this
     */
    public function requireUppercase(): self
    {
        $this->requireUppercase = true;
        return $this;
    }

    /**
     * Require lowercase letters
     *
     * @return $this
     */
    public function requireLowercase(): self
    {
        $this->requireLowercase = true;
        return $this;
    }

    /**
     * Require numbers
     *
     * @return $this
     */
    public function requireNumbers(): self
    {
        $this->requireNumbers = true;
        return $this;
    }

    /**
     * Require special characters
     *
     * @return $this
     */
    public function requireSpecialCharacters(): self
    {
        $this->requireSpecial = true;
        return $this;
    }

    /**
     * Validate the attribute
     *
     * @param string $attribute
     * @param mixed $value
     * @param array $parameters
     * @param Validator $validator
     * @return bool
     */
    public function passes(string $attribute, mixed $value, array $parameters, Validator $validator): bool
    {
        if (!is_string($value)) {
            return false;
        }

        if (strlen($value) < $this->minLength) {
            return false;
        }

        if ($this->requireUppercase && !preg_match('/[A-Z]/', $value)) {
            return false;
        }

        if ($this->requireLowercase && !preg_match('/[a-z]/', $value)) {
            return false;
        }

        if ($this->requireNumbers && !preg_match('/[0-9]/', $value)) {
            return false;
        }

        if ($this->requireSpecial && !preg_match('/[^A-Za-z0-9]/', $value)) {
            return false;
        }

        return true;
    }

    /**
     * Get the validation error message
     *
     * @param string $attribute
     * @param array $parameters
     * @return string
     */
    public function message(string $attribute, array $parameters): string
    {
        $requirements = [];

        $requirements[] = "at least {$this->minLength} characters";

        if ($this->requireUppercase) {
            $requirements[] = "uppercase letters";
        }

        if ($this->requireLowercase) {
            $requirements[] = "lowercase letters";
        }

        if ($this->requireNumbers) {
            $requirements[] = "numbers";
        }

        if ($this->requireSpecial) {
            $requirements[] = "special characters";
        }

        return "The {$attribute} must contain " . implode(', ', $requirements) . ".";
    }

    /**
     * Get the rule name
     *
     * @return string
     */
    public function getName(): string
    {
        return 'password';
    }
}
