<?php

declare(strict_types=1);

namespace Horizon\Database\Eloquent;

use RuntimeException;

/**
 * Security Exception
 * 
 * Thrown when a security violation is detected during model operations.
 */
class SecurityException extends RuntimeException
{
    /**
     * The attribute that caused the security violation.
     */
    protected ?string $attribute = null;

    /**
     * The security violation type.
     */
    protected ?string $violationType = null;

    /**
     * Create a new security exception instance.
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        ?string $attribute = null,
        ?string $violationType = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->attribute = $attribute;
        $this->violationType = $violationType;
    }

    /**
     * Get the attribute that caused the violation.
     */
    public function getAttribute(): ?string
    {
        return $this->attribute;
    }

    /**
     * Get the violation type.
     */
    public function getViolationType(): ?string
    {
        return $this->violationType;
    }

    /**
     * Create an exception for SQL injection detection.
     */
    public static function sqlInjection(string $attribute, string $value): static
    {
        return new static(
            "Potential SQL injection detected in attribute [{$attribute}]: " . substr($value, 0, 100),
            0,
            null,
            $attribute,
            'sql_injection'
        );
    }

    /**
     * Create an exception for XSS detection.
     */
    public static function xssAttack(string $attribute, string $value): static
    {
        return new static(
            "Potential XSS attack detected in attribute [{$attribute}]: " . substr($value, 0, 100),
            0,
            null,
            $attribute,
            'xss_attack'
        );
    }

    /**
     * Create an exception for invalid data format.
     */
    public static function invalidFormat(string $attribute, string $expectedFormat, mixed $value): static
    {
        return new static(
            "Invalid {$expectedFormat} format for attribute [{$attribute}]: " . (string) $value,
            0,
            null,
            $attribute,
            'invalid_format'
        );
    }

    /**
     * Create an exception for oversized data.
     */
    public static function oversizedData(string $attribute, int $maxSize, int $actualSize): static
    {
        return new static(
            "Data too large for attribute [{$attribute}]. Maximum: {$maxSize}, Actual: {$actualSize}",
            0,
            null,
            $attribute,
            'oversized_data'
        );
    }

    /**
     * Create an exception for suspicious attribute names.
     */
    public static function suspiciousAttribute(string $attribute): static
    {
        return new static(
            "Suspicious attribute name detected: [{$attribute}]",
            0,
            null,
            $attribute,
            'suspicious_attribute'
        );
    }

    /**
     * Create an exception for binary data in text fields.
     */
    public static function binaryInTextField(string $attribute): static
    {
        return new static(
            "Binary data detected in text field: [{$attribute}]",
            0,
            null,
            $attribute,
            'binary_in_text'
        );
    }

    /**
     * Create an exception for unauthorized access.
     */
    public static function unauthorizedAccess(string $operation, ?string $model = null): static
    {
        $message = "Unauthorized access to operation: [{$operation}]";
        if ($model) {
            $message .= " on model [{$model}]";
        }

        return new static(
            $message,
            0,
            null,
            null,
            'unauthorized_access'
        );
    }
}