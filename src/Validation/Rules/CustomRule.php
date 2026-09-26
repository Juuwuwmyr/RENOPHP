<?php

namespace Horizon\Validation\Rules;

use Horizon\Validation\ValidationRule;
use Horizon\Validation\Validator;

/**
 * CustomRule
 * 
 * Base class for creating custom validation rules using closures.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Simple closure-based validation
 * - Clear pass/fail semantics
 * - Explicit error messages
 * 
 * Example:
 * ```php
 * $rule = new CustomRule(
 *     fn($value) => $value > 10,
 *     'The :attribute must be greater than 10'
 * );
 * ```
 */
class CustomRule implements ValidationRule
{
    /**
     * The validation closure
     *
     * @var callable
     */
    protected $callback;

    /**
     * The error message
     *
     * @var string
     */
    protected string $message;

    /**
     * The rule name
     *
     * @var string
     */
    protected string $name;

    /**
     * Create a new custom rule
     *
     * @param callable $callback
     * @param string $message
     * @param string $name
     */
    public function __construct(callable $callback, string $message, string $name = 'custom')
    {
        $this->callback = $callback;
        $this->message = $message;
        $this->name = $name;
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
        return call_user_func($this->callback, $value, $attribute, $parameters, $validator);
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
        $message = str_replace(':attribute', $attribute, $this->message);
        
        foreach ($parameters as $i => $parameter) {
            $message = str_replace(":{$i}", $parameter, $message);
        }
        
        return $message;
    }

    /**
     * Get the rule name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }
}
