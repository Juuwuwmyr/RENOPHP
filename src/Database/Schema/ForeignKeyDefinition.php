<?php

declare(strict_types=1);

namespace Horizon\Database\Schema;

use Horizon\Contracts\Database\ForeignKeyDefinitionInterface;
use Horizon\Contracts\Database\CommandInterface;

/**
 * Foreign Key Definition
 * 
 * Represents a foreign key constraint definition.
 */
class ForeignKeyDefinition implements ForeignKeyDefinitionInterface
{
    /**
     * The foreign key command.
     */
    protected CommandInterface $command;

    /**
     * Create a new foreign key definition.
     */
    public function __construct(CommandInterface $command)
    {
        $this->command = $command;
    }

    /**
     * Specify the referenced table.
     */
    public function references(array|string $columns): static
    {
        $this->command->references = (array) $columns;

        return $this;
    }

    /**
     * Specify the referenced table.
     */
    public function on(string $table): static
    {
        $this->command->on = $table;

        return $this;
    }

    /**
     * Add an ON DELETE action.
     */
    public function onDelete(string $action): static
    {
        $this->command->onDelete = $action;

        return $this;
    }

    /**
     * Add an ON UPDATE action.
     */
    public function onUpdate(string $action): static
    {
        $this->command->onUpdate = $action;

        return $this;
    }

    /**
     * Set the foreign key to cascade on delete.
     */
    public function cascadeOnDelete(): static
    {
        return $this->onDelete('cascade');
    }

    /**
     * Set the foreign key to restrict on delete.
     */
    public function restrictOnDelete(): static
    {
        return $this->onDelete('restrict');
    }

    /**
     * Set the foreign key to set null on delete.
     */
    public function nullOnDelete(): static
    {
        return $this->onDelete('set null');
    }

    /**
     * Set the foreign key to no action on delete.
     */
    public function noActionOnDelete(): static
    {
        return $this->onDelete('no action');
    }

    /**
     * Set the foreign key to cascade on update.
     */
    public function cascadeOnUpdate(): static
    {
        return $this->onUpdate('cascade');
    }

    /**
     * Set the foreign key to restrict on update.
     */
    public function restrictOnUpdate(): static
    {
        return $this->onUpdate('restrict');
    }

    /**
     * Set the foreign key to set null on update.
     */
    public function nullOnUpdate(): static
    {
        return $this->onUpdate('set null');
    }

    /**
     * Set the foreign key to no action on update.
     */
    public function noActionOnUpdate(): static
    {
        return $this->onUpdate('no action');
    }

    /**
     * Get the foreign key name.
     */
    public function getName(): ?string
    {
        return $this->command->index ?? null;
    }

    /**
     * Get the local columns.
     */
    public function getLocalColumns(): array
    {
        return $this->command->columns ?? [];
    }

    /**
     * Get the referenced table.
     */
    public function getReferencedTable(): ?string
    {
        return $this->command->on ?? null;
    }

    /**
     * Get the referenced columns.
     */
    public function getReferencedColumns(): array
    {
        return $this->command->references ?? [];
    }

    /**
     * Get the ON DELETE action.
     */
    public function getOnDelete(): ?string
    {
        return $this->command->onDelete ?? null;
    }

    /**
     * Get the ON UPDATE action.
     */
    public function getOnUpdate(): ?string
    {
        return $this->command->onUpdate ?? null;
    }
}