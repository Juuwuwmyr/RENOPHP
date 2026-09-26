<?php

namespace Reno\Validation\Rules;

use Reno\Validation\ValidationRule;
use Reno\Validation\Validator;

/**
 * PhoneRule
 * 
 * Validates phone numbers with configurable formats.
 * 
 * Example:
 * ```php
 * $rule = new PhoneRule('US'); // US format
 * $rule = new PhoneRule('international'); // International format
 * ```
 */
class PhoneRule implements ValidationRule
{
    /**
     * Phone format
     *
     * @var string
     */
    protected string $format;

    /**
     * Create a new phone rule
     *
     * @param string $format
     */
    public function __construct(string $format = 'international')
    {
        $this->format = $format;
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

        // Remove common separators
        $cleaned = preg_replace('/[\s\-\(\)\.]/', '', $value);

        return match ($this->format) {
            'US' => $this->validateUS($cleaned),
            'UK' => $this->validateUK($cleaned),
            'international' => $this->validateInternational($cleaned),
            default => $this->validateInternational($cleaned),
        };
    }

    /**
     * Validate US phone number
     *
     * @param string $phone
     * @return bool
     */
    protected function validateUS(string $phone): bool
    {
        // US: 10 digits, optional +1 prefix
        return preg_match('/^\+?1?[2-9]\d{9}$/', $phone) > 0;
    }

    /**
     * Validate UK phone number
     *
     * @param string $phone
     * @return bool
     */
    protected function validateUK(string $phone): bool
    {
        // UK: 10-11 digits, optional +44 prefix
        return preg_match('/^\+?44?[1-9]\d{9,10}$/', $phone) > 0;
    }

    /**
     * Validate international phone number
     *
     * @param string $phone
     * @return bool
     */
    protected function validateInternational(string $phone): bool
    {
        // International: 7-15 digits, optional + prefix
        return preg_match('/^\+?[1-9]\d{6,14}$/', $phone) > 0;
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
        return "The {$attribute} must be a valid phone number.";
    }

    /**
     * Get the rule name
     *
     * @return string
     */
    public function getName(): string
    {
        return 'phone';
    }
}
