<?php

declare(strict_types=1);

namespace Reno\Database\Migrations;

use Reno\Database\Schema\Builder;

/**
 * Migration
 * 
 * Base class for database migrations.
 */
abstract class Migration
{
    /**
     * The database schema builder.
     */
    protected ?Builder $schema = null;

    /**
     * Enable or disable foreign key constraints during the migration.
     */
    protected bool $withinTransaction = true;

    /**
     * Run the migrations.
     */
    abstract public function up(): void;

    /**
     * Reverse the migrations.
     */
    abstract public function down(): void;

    /**
     * Get the migration connection name.
     */
    public function getConnection(): ?string
    {
        return null;
    }

    /**
     * Set the migration schema builder.
     */
    public function setSchema(Builder $schema): void
    {
        $this->schema = $schema;
    }

    /**
     * Get the migration schema builder.
     */
    public function getSchema(): Builder
    {
        if (is_null($this->schema)) {
            throw new \RuntimeException('Schema builder not set for migration.');
        }

        return $this->schema;
    }

    /**
     * Determine if the migration should run within a transaction.
     */
    public function withinTransaction(): bool
    {
        return $this->withinTransaction;
    }
}