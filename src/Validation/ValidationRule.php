<?php

namespace Horizon\Validation;

/**
 * ValidationRule Interface
 * 
 * Contract for all validation rules.
 * Each rule implements a single validation check.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - One rule, one responsibility
 * - Clear pass/fail semantics
 * - Explicit error messages
 */
interface ValidationRule
{
    /**
     * Validate the attribute
     *
     * @param string $attribute The name of the attribute being validated
     * @param mixed $value The value to validate
     * @param array $parameters Rule parameters (e.g., min:5 -> ['5'])
     * @param Validator $validator The validator instance
     * @return bool True if validation passes, false otherwise
     */
    public function passes(string $attribute, mixed $value, array $parameters, Validator $validator): bool;

    /**
     * Get the validation error message
     *
     * @param string $attribute The name of the attribute
     * @param array $parameters Rule parameters
     * @return string The error message
     */
    public function message(string $attribute, array $parameters): string;

    /**
     * Get the rule name
     *
     * @return string
     */
    public function getName(): string;
}
