<?php

declare(strict_types=1);

namespace Reno\Database\Migrations;

use Reno\Contracts\Database\ConnectionInterface;
use Reno\Database\Query\QueryBuilder;

/**
 * Migration Repository
 * 
 * Manages the storage and retrieval of migration records.
 */
class MigrationRepository
{
    /**
     * The database connection instance.
     */
    protected ConnectionInterface $connection;

    /**
     * The name of the migration table.
     */
    protected string $table;

    /**
     * Create a new migration repository instance.
     */
    public function __construct(ConnectionInterface $connection, string $table)
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    /**
     * Get the ran migrations.
     */
    public function getRan(): array
    {
        return $this->table()
                    ->orderBy('batch', 'asc')
                    ->orderBy('migration', 'asc')
                    ->pluck('migration');
    }

    /**
     * Get list of migrations.
     */
    public function getMigrations(int $steps): array
    {
        $query = $this->table()->where('batch', '>=', '1');

        return $query->orderBy('batch', 'desc')
                     ->orderBy('migration', 'desc')
                     ->take($steps)->get();
    }

    /**
     * Get the last migration batch.
     */
    public function getLast(): array
    {
        $query = $this->table()->where('batch', $this->getLastBatchNumber());

        return $query->orderBy('migration', 'desc')->get();
    }

    /**
     * Get the completed migrations with their batch numbers.
     */
    public function getMigrationBatches(): array
    {
        return $this->table()
                    ->orderBy('batch', 'asc')
                    ->orderBy('migration', 'asc')
                    ->pluck('batch', 'migration');
    }

    /**
     * Log that a migration was run.
     */
    public function log(string $file, int $batch): void
    {
        $record = ['migration' => $file, 'batch' => $batch];

        $this->table()->insert($record);
    }

    /**
     * Remove a migration from the log.
     */
    public function delete(object $migration): void
    {
        $this->table()->where('migration', $migration->migration)->delete();
    }

    /**
     * Get the next migration batch number.
     */
    public function getNextBatchNumber(): int
    {
        return $this->getLastBatchNumber() + 1;
    }

    /**
     * Get the last migration batch number.
     */
    public function getLastBatchNumber(): int
    {
        return $this->table()->max('batch') ?? 0;
    }

    /**
     * Create the migration repository data store.
     */
    public function createRepository(): void
    {
        $schema = $this->connection->getSchemaBuilder();

        $schema->create($this->table, function ($table) {
            // The migrations table is responsible for keeping track of which of the
            // migrations have actually run for the application. We'll create the
            // table to hold the migration file's path as well as the batch ID.
            $table->increments('id');
            $table->string('migration');
            $table->integer('batch');
        });
    }

    /**
     * Determine if the migration repository exists.
     */
    public function repositoryExists(): bool
    {
        $schema = $this->connection->getSchemaBuilder();

        return $schema->hasTable($this->table);
    }

    /**
     * Delete the migration repository.
     */
    public function deleteRepository(): void
    {
        $schema = $this->connection->getSchemaBuilder();

        $schema->drop($this->table);
    }

    /**
     * Get a query builder for the migration table.
     */
    protected function table(): QueryBuilder
    {
        return $this->connection->table($this->table);
    }

    /**
     * Get the connection instance.
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * Set the information source to gather data.
     */
    public function setSource(string $name): void
    {
        $this->connection = $this->connection->getDatabase()->connection($name);
    }
}