<?php

namespace Horizon\Validation;

use Exception;

/**
 * ValidationException
 * 
 * Thrown when validation fails.
 * Contains all validation errors in a MessageBag.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Clear error messages
 * - Easy to access errors
 * - JSON serializable for APIs
 */
class ValidationException extends Exception
{
    /**
     * The message bag containing all validation errors
     *
     * @var MessageBag
     */
    protected MessageBag $errors;

    /**
     * The validated data
     *
     * @var array
     */
    protected array $data;

    /**
     * Create a new validation exception
     *
     * @param MessageBag $errors
     * @param array $data
     */
    public function __construct(MessageBag $errors, array $data = [])
    {
        $this->errors = $errors;
        $this->data = $data;

        parent::__construct('The given data was invalid.');
    }

    /**
     * Get the validation errors
     *
     * @return MessageBag
     */
    public function errors(): MessageBag
    {
        return $this->errors;
    }

    /**
     * Get the validated data
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get all error messages as an array
     *
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors->all();
    }

    /**
     * Get the first error message
     *
     * @return string|null
     */
    public function getFirstError(): ?string
    {
        return $this->errors->first();
    }

    /**
     * Convert to array for JSON responses
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'errors' => $this->errors->toArray(),
        ];
    }

    /**
     * Convert to JSON
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }
}
