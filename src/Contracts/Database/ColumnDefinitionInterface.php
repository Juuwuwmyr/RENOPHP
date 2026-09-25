<?php

declare(strict_types=1);

namespace Horizon\Contracts\Database;

/**
 * Column Definition Interface
 * 
 * Defines the contract for database column definitions.
 */
interface ColumnDefinitionInterface
{
    /**
     * Set the column to allow null values.
     */
    public function nullable(bool $value = true): static;

    /**
     * Set the default value for the column.
     */
    public function default(mixed $value): static;

    /**
     * Set the column to be unsigned (MySQL).
     */
    public function unsigned(): static;

    /**
     * Set the column to be auto-incrementing.
     */
    public function autoIncrement(): static;

    /**
     * Set the column comment (MySQL/PostgreSQL).
     */
    public function comment(string $comment): static;

    /**
     * Set the column to be unique.
     */
    public function unique(?string $indexName = null): static;

    /**
     * Set the column to be indexed.
     */
    public function index(?string $indexName = null): static;

    /**
     * Set the column to be a primary key.
     */
    public function primary(): static;

    /**
     * Change the column (MySQL).
     */
    public function change(): static;

    /**
     * Place the column "first" in the table (MySQL).
     */
    public function first(): static;

    /**
     * Place the column "after" another column (MySQL).
     */
    public function after(string $column): static;

    /**
     * Add a check constraint to the column (PostgreSQL/SQL Server).
     */
    public function check(string $expression): static;

    /**
     * Set the SRID for the column (MySQL/PostgreSQL).
     */
    public function srid(int $srid): static;

    /**
     * Get the column name.
     */
    public function getName(): string;

    /**
     * Get the column type.
     */
    public function getType(): string;

    /**
     * Get the column attributes.
     */
    public function getAttributes(): array;

    /**
     * Get a specific attribute.
     */
    public function getAttribute(string $key, mixed $default = null): mixed;

    /**
     * Set an attribute.
     */
    public function setAttribute(string $key, mixed $value): static;

    /**
     * Check if an attribute exists.
     */
    public function hasAttribute(string $key): bool;
}