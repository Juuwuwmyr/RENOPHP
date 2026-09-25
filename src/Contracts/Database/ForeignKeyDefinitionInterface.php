<?php

declare(strict_types=1);

namespace Horizon\Contracts\Database;

/**
 * Foreign Key Definition Interface
 * 
 * Defines the contract for database foreign key definitions.
 */
interface ForeignKeyDefinitionInterface
{
    /**
     * Specify the referenced table.
     */
    public function references(array|string $columns): static;

    /**
     * Specify the referenced table.
     */
    public function on(string $table): static;

    /**
     * Add an ON DELETE action.
     */
    public function onDelete(string $action): static;

    /**
     * Add an ON UPDATE action.
     */
    public function onUpdate(string $action): static;

    /**
     * Set the foreign key to cascade on delete.
     */
    public function cascadeOnDelete(): static;

    /**
     * Set the foreign key to restrict on delete.
     */
    public function restrictOnDelete(): static;

    /**
     * Set the foreign key to set null on delete.
     */
    public function nullOnDelete(): static;

    /**
     * Set the foreign key to no action on delete.
     */
    public function noActionOnDelete(): static;

    /**
     * Set the foreign key to cascade on update.
     */
    public function cascadeOnUpdate(): static;

    /**
     * Set the foreign key to restrict on update.
     */
    public function restrictOnUpdate(): static;

    /**
     * Set the foreign key to set null on update.
     */
    public function nullOnUpdate(): static;

    /**
     * Set the foreign key to no action on update.
     */
    public function noActionOnUpdate(): static;

    /**
     * Get the foreign key name.
     */
    public function getName(): ?string;

    /**
     * Get the local columns.
     */
    public function getLocalColumns(): array;

    /**
     * Get the referenced table.
     */
    public function getReferencedTable(): ?string;

    /**
     * Get the referenced columns.
     */
    public function getReferencedColumns(): array;

    /**
     * Get the ON DELETE action.
     */
    public function getOnDelete(): ?string;

    /**
     * Get the ON UPDATE action.
     */
    public function getOnUpdate(): ?string;
}