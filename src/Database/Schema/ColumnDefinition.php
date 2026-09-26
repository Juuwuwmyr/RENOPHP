<?php

declare(strict_types=1);

namespace Reno\Database\Schema;

use Reno\Contracts\Database\ColumnDefinitionInterface;

/**
 * Column Definition
 * 
 * Represents a database column definition with attributes and constraints.
 */
class ColumnDefinition implements ColumnDefinitionInterface
{
    /**
     * The column attributes.
     */
    protected array $attributes = [];

    /**
     * Create a new column definition.
     */
    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    /**
     * Set the column to allow null values.
     */
    public function nullable(bool $value = true): static
    {
        $this->attributes['nullable'] = $value;

        return $this;
    }

    /**
     * Set the default value for the column.
     */
    public function default(mixed $value): static
    {
        $this->attributes['default'] = $value;

        return $this;
    }

    /**
     * Set the column to be unsigned (MySQL).
     */
    public function unsigned(): static
    {
        $this->attributes['unsigned'] = true;

        return $this;
    }

    /**
     * Set the column to be auto-incrementing.
     */
    public function autoIncrement(): static
    {
        $this->attributes['autoIncrement'] = true;

        return $this;
    }

    /**
     * Set the column comment (MySQL/PostgreSQL).
     */
    public function comment(string $comment): static
    {
        $this->attributes['comment'] = $comment;

        return $this;
    }

    /**
     * Set the column to be unique.
     */
    public function unique(?string $indexName = null): static
    {
        $this->attributes['unique'] = $indexName ?: true;

        return $this;
    }

    /**
     * Set the column to be indexed.
     */
    public function index(?string $indexName = null): static
    {
        $this->attributes['index'] = $indexName ?: true;

        return $this;
    }

    /**
     * Set the column to be a primary key.
     */
    public function primary(): static
    {
        $this->attributes['primary'] = true;

        return $this;
    }

    /**
     * Change the column (MySQL).
     */
    public function change(): static
    {
        $this->attributes['change'] = true;

        return $this;
    }

    /**
     * Place the column "first" in the table (MySQL).
     */
    public function first(): static
    {
        $this->attributes['first'] = true;

        return $this;
    }

    /**
     * Place the column "after" another column (MySQL).
     */
    public function after(string $column): static
    {
        $this->attributes['after'] = $column;

        return $this;
    }

    /**
     * Add a check constraint to the column (PostgreSQL/SQL Server).
     */
    public function check(string $expression): static
    {
        $this->attributes['check'] = $expression;

        return $this;
    }

    /**
     * Set the SRID for the column (MySQL/PostgreSQL).
     */
    public function srid(int $srid): static
    {
        $this->attributes['srid'] = $srid;

        return $this;
    }

    /**
     * Get the column name.
     */
    public function getName(): string
    {
        return $this->attributes['name'] ?? '';
    }

    /**
     * Get the column type.
     */
    public function getType(): string
    {
        return $this->attributes['type'] ?? '';
    }

    /**
     * Get the column attributes.
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get a specific attribute.
     */
    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Set an attribute.
     */
    public function setAttribute(string $key, mixed $value): static
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Check if an attribute exists.
     */
    public function hasAttribute(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * Dynamically access column attributes.
     */
    public function __get(string $key): mixed
    {
        return $this->getAttribute($key);
    }

    /**
     * Dynamically set column attributes.
     */
    public function __set(string $key, mixed $value): void
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Dynamically check if an attribute is set.
     */
    public function __isset(string $key): bool
    {
        return $this->hasAttribute($key);
    }
}