<?php

declare(strict_types=1);

namespace Horizon\Database\Schema;

use Closure;
use Horizon\Contracts\Database\ConnectionInterface;
use Horizon\Contracts\Database\SchemaGrammarInterface;
use Horizon\Database\Schema\Blueprint;

/**
 * Schema Builder
 * 
 * Provides a fluent interface for creating and modifying database schema.
 */
class Builder
{
    /**
     * The database connection instance.
     */
    protected ConnectionInterface $connection;

    /**
     * The schema grammar instance.
     */
    protected SchemaGrammarInterface $grammar;

    /**
     * The Blueprint resolver callback.
     */
    protected ?Closure $resolver = null;

    /**
     * Create a new database Schema manager.
     */
    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
        $this->grammar = $connection->getSchemaGrammar();
    }

    /**
     * Set the Schema Blueprint resolver callback.
     */
    public function blueprintResolver(Closure $resolver): void
    {
        $this->resolver = $resolver;
    }

    /**
     * Determine if the given table exists.
     */
    public function hasTable(string $table): bool
    {
        $table = $this->connection->getTablePrefix() . $table;

        return count($this->connection->selectFromWriteConnection(
            $this->grammar->compileTableExists(), [$table]
        )) > 0;
    }

    /**
     * Get the column listing for a given table.
     */
    public function getColumnListing(string $table): array
    {
        $results = $this->connection->selectFromWriteConnection(
            $this->grammar->compileColumnListing($this->connection->getTablePrefix() . $table)
        );

        return $this->connection->getPostProcessor()->processColumnListing($results);
    }

    /**
     * Determine if the given table has a given column.
     */
    public function hasColumn(string $table, string $column): bool
    {
        return in_array(
            strtolower($column), array_map('strtolower', $this->getColumnListing($table))
        );
    }

    /**
     * Determine if the given table has given columns.
     */
    public function hasColumns(string $table, array $columns): bool
    {
        $tableColumns = array_map('strtolower', $this->getColumnListing($table));

        foreach ($columns as $column) {
            if (!in_array(strtolower($column), $tableColumns)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Execute the blueprint to build / modify the table.
     */
    protected function build(Blueprint $blueprint): void
    {
        $blueprint->build($this->connection, $this->grammar);
    }

    /**
     * Create a new table on the schema.
     */
    public function create(string $table, Closure $callback): void
    {
        $this->build(tap($this->createBlueprint($table), function ($blueprint) use ($callback) {
            $blueprint->create();

            $callback($blueprint);
        }));
    }

    /**
     * Drop a table from the schema.
     */
    public function drop(string $table): void
    {
        $this->build($this->createBlueprint($table, function ($blueprint) {
            $blueprint->drop();
        }));
    }

    /**
     * Drop a table from the schema if it exists.
     */
    public function dropIfExists(string $table): void
    {
        $this->build($this->createBlueprint($table, function ($blueprint) {
            $blueprint->dropIfExists();
        }));
    }
}
    /**
     * Modify a table on the schema.
     */
    public function table(string $table, Closure $callback): void
    {
        $this->build(tap($this->createBlueprint($table), function ($blueprint) use ($callback) {
            $callback($blueprint);
        }));
    }

    /**
     * Rename a table on the schema.
     */
    public function rename(string $from, string $to): void
    {
        $this->build($this->createBlueprint($from, function ($blueprint) use ($to) {
            $blueprint->rename($to);
        }));
    }

    /**
     * Enable foreign key constraints.
     */
    public function enableForeignKeyConstraints(): bool
    {
        return $this->connection->statement(
            $this->grammar->compileEnableForeignKeyConstraints()
        );
    }

    /**
     * Disable foreign key constraints.
     */
    public function disableForeignKeyConstraints(): bool
    {
        return $this->connection->statement(
            $this->grammar->compileDisableForeignKeyConstraints()
        );
    }

    /**
     * Disable foreign key constraints during the execution of a callback.
     */
    public function withoutForeignKeyConstraints(Closure $callback): mixed
    {
        $this->disableForeignKeyConstraints();

        try {
            return $callback();
        } finally {
            $this->enableForeignKeyConstraints();
        }
    }

    /**
     * Get the database connection instance.
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * Set the database connection instance.
     */
    public function setConnection(ConnectionInterface $connection): static
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Create a new command set with a Closure.
     */
    protected function createBlueprint(string $table, ?Closure $callback = null): Blueprint
    {
        $prefix = $this->connection->getConfig('prefix_indexes')
                    ? $this->connection->getConfig('prefix')
                    : '';

        if (isset($this->resolver)) {
            return call_user_func($this->resolver, $table, $callback, $prefix);
        }

        return new Blueprint($table, $callback, $prefix);
    }

    /**
     * Get the schema grammar instance.
     */
    public function getSchemaGrammar(): SchemaGrammarInterface
    {
        return $this->grammar;
    }
}