<?php

declare(strict_types=1);

namespace Horizon\Database\Query;

/**
 * Expression
 * 
 * Represents a raw SQL expression that should not be escaped or bound.
 */
class Expression
{
    /**
     * The value of the expression.
     */
    protected mixed $value;

    /**
     * Create a new raw query expression.
     */
    public function __construct(mixed $value)
    {
        $this->value = $value;
    }

    /**
     * Get the value of the expression.
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * Get the string representation of the expression.
     */
    public function __toString(): string
    {
        return (string) $this->getValue();
    }
}