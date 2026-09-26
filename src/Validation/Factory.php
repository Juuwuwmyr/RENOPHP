<?php

namespace Horizon\Validation;

/**
 * Validation Factory
 * 
 * Factory for creating Validator instances.
 * Provides a convenient interface for validation.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Simple factory pattern
 * - Clear validator creation
 * - Easy to extend
 */
class Factory
{
    /**
     * Database connection
     *
     * @var mixed
     */
    protected mixed $db = null;

    /**
     * Set the database connection
     *
     * @param mixed $db
     * @return $this
     */
    public function setDatabase(mixed $db): self
    {
        $this->db = $db;
        return $this;
    }

    /**
     * Create a new validator instance
     *
     * @param array $data
     * @param array $rules
     * @param array $messages
     * @param array $customAttributes
     * @return Validator
     */
    public function make(
        array $data,
        array $rules,
        array $messages = [],
        array $customAttributes = []
    ): Validator {
        $validator = new Validator($data, $rules, $messages, $customAttributes);

        if ($this->db) {
            $validator->setDatabase($this->db);
        }

        return $validator;
    }

    /**
     * Validate data and throw exception on failure
     *
     * @param array $data
     * @param array $rules
     * @param array $messages
     * @param array $customAttributes
     * @return array Validated data
     * @throws ValidationException
     */
    public function validate(
        array $data,
        array $rules,
        array $messages = [],
        array $customAttributes = []
    ): array {
        $validator = $this->make($data, $rules, $messages, $customAttributes);

        return $validator->validated();
    }

    /**
     * Register a custom validation rule
     *
     * @param string $name
     * @param ValidationRule $rule
     * @return void
     */
    public function extend(string $name, ValidationRule $rule): void
    {
        Validator::extend($name, $rule);
    }
}
