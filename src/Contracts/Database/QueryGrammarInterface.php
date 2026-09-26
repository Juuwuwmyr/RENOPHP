<?php

declare(strict_types=1);

namespace Reno\Contracts\Database;

/**
 * Query Grammar Interface
 * 
 * Defines the contract for database query grammar implementations.
 */
interface QueryGrammarInterface
{
    /**
     * Compile a select query into SQL.
     */
    public function compileSelect(QueryBuilderInterface $query): string;

    /**
     * Compile an insert statement into SQL.
     */
    public function compileInsert(QueryBuilderInterface $query, array $values): string;

    /**
     * Compile an update statement into SQL.
     */
    public function compileUpdate(QueryBuilderInterface $query, array $values): string;

    /**
     * Compile a delete statement into SQL.
     */
    public function compileDelete(QueryBuilderInterface $query): string;

    /**
     * Compile the "select *" portion of the query.
     */
    public function compileColumns(QueryBuilderInterface $query, array $columns): string;

    /**
     * Compile the "from" portion of the query.
     */
    public function compileFrom(QueryBuilderInterface $query, string $table): string;

    /**
     * Compile the "join" portions of the query.
     */
    public function compileJoins(QueryBuilderInterface $query, array $joins): string;

    /**
     * Compile the "where" portions of the query.
     */
    public function compileWheres(QueryBuilderInterface $query): string;

    /**
     * Compile the "order by" portions of the query.
     */
    public function compileOrders(QueryBuilderInterface $query, array $orders): string;

    /**
     * Compile the "limit" portions of the query.
     */
    public function compileLimit(QueryBuilderInterface $query, int $limit): string;

    /**
     * Compile the "offset" portions of the query.
     */
    public function compileOffset(QueryBuilderInterface $query, int $offset): string;

    /**
     * Wrap a value in keyword identifiers.
     */
    public function wrap(string $value): string;

    /**
     * Wrap a table in keyword identifiers.
     */
    public function wrapTable(string $table): string;

    /**
     * Get the format for database stored dates.
     */
    public function getDateFormat(): string;

    /**
     * Get the grammar's table prefix.
     */
    public function getTablePrefix(): string;

    /**
     * Set the grammar's table prefix.
     */
    public function setTablePrefix(string $prefix): void;
}