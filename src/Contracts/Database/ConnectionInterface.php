<?php

declare(strict_types=1);

namespace Horizon\Contracts\Database;

use PDO;
use Closure;

/**
 * Database Connection Interface
 * 
 * Defines the contract for database connections in the Horizon framework.
 */
interface ConnectionInterface
{
    /**
     * Run a select statement against the database.
     */
    public function select(string $query, array $bindings = [], bool $useReadPdo = true): array;

    /**
     * Run an insert statement against the database.
     */
    public function insert(string $query, array $bindings = []): bool;

    /**
     * Run an update statement against the database.
     */
    public function update(string $query, array $bindings = []): int;

    /**
     * Run a delete statement against the database.
     */
    public function delete(string $query, array $bindings = []): int;

    /**
     * Execute an SQL statement and return the boolean result.
     */
    public function statement(string $query, array $bindings = []): bool;

    /**
     * Run an SQL statement and get the number of rows affected.
     */
    public function affectingStatement(string $query, array $bindings = []): int;

    /**
     * Run a raw, unprepared query against the PDO connection.
     */
    public function unprepared(string $query): bool;

    /**
     * Execute a Closure within a transaction.
     */
    public function transaction(Closure $callback, int $attempts = 1): mixed;

    /**
     * Start a new database transaction.
     */
    public function beginTransaction(): void;

    /**
     * Commit the active database transaction.
     */
    public function commit(): void;

    /**
     * Rollback the active database transaction.
     */
    public function rollBack(): void;

    /**
     * Get the number of active transactions.
     */
    public function transactionLevel(): int;

    /**
     * Prepare the query bindings for execution.
     */
    public function prepareBindings(array $bindings): array;

    /**
     * Log a query in the connection's query log.
     */
    public function logQuery(string $query, array $bindings, ?float $time = null): void;

    /**
     * Get the connection query log.
     */
    public function getQueryLog(): array;

    /**
     * Clear the query log.
     */
    public function flushQueryLog(): void;

    /**
     * Enable the query log on the connection.
     */
    public function enableQueryLog(): void;

    /**
     * Disable the query log on the connection.
     */
    public function disableQueryLog(): void;

    /**
     * Determine whether we're logging queries.
     */
    public function logging(): bool;

    /**
     * Get the database connection name.
     */
    public function getName(): ?string;

    /**
     * Get the database connection configuration.
     */
    public function getConfig(): array;

    /**
     * Get the PDO driver name.
     */
    public function getDriverName(): string;

    /**
     * Get the query grammar used by the connection.
     */
    public function getQueryGrammar(): QueryGrammarInterface;

    /**
     * Set the query grammar used by the connection.
     */
    public function setQueryGrammar(QueryGrammarInterface $grammar): void;

    /**
     * Get the schema grammar used by the connection.
     */
    public function getSchemaGrammar(): SchemaGrammarInterface;

    /**
     * Set the schema grammar used by the connection.
     */
    public function setSchemaGrammar(SchemaGrammarInterface $grammar): void;

    /**
     * Get the query post processor used by the connection.
     */
    public function getPostProcessor(): ProcessorInterface;

    /**
     * Set the query post processor used by the connection.
     */
    public function setPostProcessor(ProcessorInterface $processor): void;

    /**
     * Determine if the connection is in a "dry run".
     */
    public function pretending(): bool;

    /**
     * Get the default fetch mode for the connection.
     */
    public function getFetchMode(): int;

    /**
     * Set the default fetch mode for the connection.
     */
    public function setFetchMode(int $fetchMode): int;

    /**
     * Get the connection resolver for the connection.
     */
    public function getResolver(): ?Closure;

    /**
     * Set the connection resolver.
     */
    public function setResolver(Closure $resolver): void;

    /**
     * Disconnect from the underlying PDO connection.
     */
    public function disconnect(): void;

    /**
     * Reconnect to the database.
     */
    public function reconnect(): void;

    /**
     * Get the database connection.
     */
    public function getPdo(): ?PDO;

    /**
     * Get the current PDO connection used for reading.
     */
    public function getReadPdo(): ?PDO;

    /**
     * Set the PDO connection.
     */
    public function setPdo(?PDO $pdo): static;

    /**
     * Set the PDO connection used for reading.
     */
    public function setReadPdo(?PDO $pdo): static;
}