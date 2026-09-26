<?php

declare(strict_types=1);

namespace Horizon\Database\Eloquent;

use RuntimeException;

/**
 * Mass Assignment Exception
 * 
 * Thrown when attempting to mass assign a non-fillable attribute.
 */
class MassAssignmentException extends RuntimeException
{
    /**
     * Create a new mass assignment exception instance.
     */
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create an exception for a specific model and attribute.
     */
    public static function forAttribute(string $model, string $attribute): static
    {
        return new static(
            "Add [{$attribute}] to fillable property to allow mass assignment on [{$model}]."
        );
    }

    /**
     * Create an exception when no fillable attributes are defined.
     */
    public static function forModel(string $model): static
    {
        return new static(
            "Model [{$model}] has no fillable attributes. Either add to fillable array or use guarded => []."
        );
    }

    /**
     * Create an exception for guarded attributes.
     */
    public static function forGuardedAttribute(string $model, string $attribute): static
    {
        return new static(
            "Attribute [{$attribute}] is guarded and cannot be mass assigned on [{$model}]."
        );
    }
}