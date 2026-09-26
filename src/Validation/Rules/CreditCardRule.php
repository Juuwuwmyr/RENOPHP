<?php

namespace Horizon\Validation\Rules;

use Horizon\Validation\ValidationRule;
use Horizon\Validation\Validator;

/**
 * CreditCardRule
 * 
 * Validates credit card numbers using the Luhn algorithm.
 * 
 * Example:
 * ```php
 * $rule = new CreditCardRule(); // Any card
 * $rule = new CreditCardRule('visa'); // Visa only
 * $rule = new CreditCardRule(['visa', 'mastercard']); // Visa or Mastercard
 * ```
 */
class CreditCardRule implements ValidationRule
{
    /**
     * Allowed card types
     *
     * @var array
     */
    protected array $allowedTypes;

    /**
     * Card type patterns
     *
     * @var array
     */
    protected array $patterns = [
        'visa' => '/^4[0-9]{12}(?:[0-9]{3})?$/',
        'mastercard' => '/^5[1-5][0-9]{14}$/',
        'amex' => '/^3[47][0-9]{13}$/',
        'discover' => '/^6(?:011|5[0-9]{2})[0-9]{12}$/',
        'diners' => '/^3(?:0[0-5]|[68][0-9])[0-9]{11}$/',
        'jcb' => '/^(?:2131|1800|35\d{3})\d{11}$/',
    ];

    /**
     * Create a new credit card rule
     *
     * @param string|array|null $types
     */
    public function __construct(string|array|null $types = null)
    {
        if ($types === null) {
            $this->allowedTypes = array_keys($this->patterns);
        } else {
            $this->allowedTypes = is_array($types) ? $types : [$types];
        }
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

        // Remove spaces and dashes
        $number = preg_replace('/[\s\-]/', '', $value);

        // Check if it's numeric
        if (!ctype_digit($number)) {
            return false;
        }

        // Validate using Luhn algorithm
        if (!$this->luhnCheck($number)) {
            return false;
        }

        // Check card type if specified
        if (!empty($this->allowedTypes)) {
            $matchesType = false;

            foreach ($this->allowedTypes as $type) {
                if (isset($this->patterns[$type]) && preg_match($this->patterns[$type], $number)) {
                    $matchesType = true;
                    break;
                }
            }

            if (!$matchesType) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate using Luhn algorithm
     *
     * @param string $number
     * @return bool
     */
    protected function luhnCheck(string $number): bool
    {
        $sum = 0;
        $length = strlen($number);
        $parity = $length % 2;

        for ($i = 0; $i < $length; $i++) {
            $digit = (int) $number[$i];

            if ($i % 2 == $parity) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
        }

        return $sum % 10 === 0;
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
        if (count($this->allowedTypes) === 1) {
            $type = ucfirst($this->allowedTypes[0]);
            return "The {$attribute} must be a valid {$type} card number.";
        }

        if (!empty($this->allowedTypes)) {
            $types = implode(', ', array_map('ucfirst', $this->allowedTypes));
            return "The {$attribute} must be a valid card number ({$types}).";
        }

        return "The {$attribute} must be a valid credit card number.";
    }

    /**
     * Get the rule name
     *
     * @return string
     */
    public function getName(): string
    {
        return 'credit_card';
    }
}
