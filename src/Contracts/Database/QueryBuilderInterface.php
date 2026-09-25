<?php

declare(strict_types=1);

namespace Horizon\Contracts\Database;

use Closure;

/**
 * Query Builder Interface
 * 
 * Defines the contract for database query builders.
 */
interface QueryBuilderInterface
{
    /**
     * Set the columns to be selected.
     */
    public function select(array|string $columns = ['*']): static;

    /**
     * Add a new select column to the query.
     */
    public function addSelect(array|string $column): static;

    /**
     * Set the table which the query is targeting.
     */
    public function from(string $table, ?string $as = null): static;

    /**
     * Add a join clause to the query.
     */
    public function join(string $table, string $first, ?string $operator = null, ?string $second = null, string $type = 'inner', bool $where = false): static;

    /**
     * Add a left join to the query.
     */
    public function leftJoin(string $table, string $first, ?string $operator = null, ?string $second = null): static;

    /**
     * Add a right join to the query.
     */
    public function rightJoin(string $table, string $first, ?string $operator = null, ?string $second = null): static;

    /**
     * Add a basic where clause to the query.
     */
    public function where(string|array|Closure $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): static;

    /**
     * Add an "or where" clause to the query.
     */
    public function orWhere(string|array|Closure $column, mixed $operator = null, mixed $value = null): static;

    /**
     * Add a "where in" clause to the query.
     */
    public function whereIn(string $column, mixed $values, string $boolean = 'and', bool $not = false): static;

    /**
     * Add an "or where in" clause to the query.
     */
    public function orWhereIn(string $column, mixed $values): static;

    /**
     * Add a "where not in" clause to the query.
     */
    public function whereNotIn(string $column, mixed $values, string $boolean = 'and'): static;

    /**
     * Add an "or where not in" clause to the query.
     */
    public function orWhereNotIn(string $column, mixed $values): static;

    /**
     * Add a "where null" clause to the query.
     */
    public function whereNull(string $column, string $boolean = 'and', bool $not = false): static;

    /**
     * Add an "or where null" clause to the query.
     */
    public function orWhereNull(string $column): static;

    /**
     * Add a "where not null" clause to the query.
     */
    public function whereNotNull(string $column, string $boolean = 'and'): static;

    /**
     * Add an "or where not null" clause to the query.
     */
    public function orWhereNotNull(string $column): static;

    /**
     * Add a "where between" statement to the query.
     */
    public function whereBetween(string $column, array $values, string $boolean = 'and', bool $not = false): static;

    /**
     * Add an "or where between" statement to the query.
     */
    public function orWhereBetween(string $column, array $values): static;

    /**
     * Add a "where not between" statement to the query.
     */
    public function whereNotBetween(string $column, array $values, string $boolean = 'and'): static;

    /**
     * Add an "or where not between" statement to the query.
     */
    public function orWhereNotBetween(string $column, array $values): static;

    /**
     * Add an "order by" clause to the query.
     */
    public function orderBy(string $column, string $direction = 'asc'): static;

    /**
     * Add a descending "order by" clause to the query.
     */
    public function orderByDesc(string $column): static;

    /**
     * Put the query's results in random order.
     */
    public function inRandomOrder(?string $seed = null): static;

    /**
     * Add a "group by" clause to the query.
     */
    public function groupBy(array|string ...$groups): static;

    /**
     * Add a "having" clause to the query.
     */
    public function having(string $column, ?string $operator = null, ?string $value = null, string $boolean = 'and'): static;

    /**
     * Add an "or having" clause to the query.
     */
    public function orHaving(string $column, ?string $operator = null, ?string $value = null): static;

    /**
     * Set the "limit" value of the query.
     */
    public function limit(int $value): static;

    /**
     * Alias to set the "limit" value of the query.
     */
    public function take(int $value): static;

    /**
     * Set the "offset" value of the query.
     */
    public function offset(int $value): static;

    /**
     * Alias to set the "offset" value of the query.
     */
    public function skip(int $value): static;

    /**
     * Execute the query as a "select" statement.
     */
    public function get(array|string $columns = ['*']): array;

    /**
     * Get a single column's value from the first result of a query.
     */
    public function value(string $column): mixed;

    /**
     * Execute a query for a single record by ID.
     */
    public function find(int|string $id, array|string $columns = ['*']): ?array;

    /**
     * Get the first result of the query.
     */
    public function first(array|string $columns = ['*']): ?array;

    /**
     * Execute the query and get the first result or throw an exception.
     */
    public function firstOrFail(array|string $columns = ['*']): array;

    /**
     * Retrieve the "count" result of the query.
     */
    public function count(string $columns = '*'): int;

    /**
     * Retrieve the minimum value of a given column.
     */
    public function min(string $column): mixed;

    /**
     * Retrieve the maximum value of a given column.
     */
    public function max(string $column): mixed;

    /**
     * Retrieve the sum of the values of a given column.
     */
    public function sum(string $column): mixed;

    /**
     * Retrieve the average of the values of a given column.
     */
    public function avg(string $column): mixed;

    /**
     * Alias for the "avg" method.
     */
    public function average(string $column): mixed;

    /**
     * Insert new records into the database.
     */
    public function insert(array $values): bool;

    /**
     * Insert new records into the database and return the ID.
     */
    public function insertGetId(array $values, ?string $sequence = null): int;

    /**
     * Update records in the database.
     */
    public function update(array $values): int;

    /**
     * Insert or update a record matching the attributes, and fill it with values.
     */
    public function updateOrInsert(array $attributes, array $values = []): bool;

    /**
     * Delete records from the database.
     */
    public function delete(?int $id = null): int;

    /**
     * Run a truncate statement on the table.
     */
    public function truncate(): void;

    /**
     * Get the SQL representation of the query.
     */
    public function toSql(): string;

    /**
     * Get the current query value bindings.
     */
    public function getBindings(): array;

    /**
     * Get the raw array of bindings.
     */
    public function getRawBindings(): array;

    /**
     * Set the bindings on the query builder.
     */
    public function setBindings(array $bindings, string $type = 'where'): static;

    /**
     * Add a binding to the query.
     */
    public function addBinding(mixed $value, string $type = 'where'): static;

    /**
     * Get the database connection instance.
     */
    public function getConnection(): ConnectionInterface;

    /**
     * Get the query processor instance.
     */
    public function getProcessor(): ProcessorInterface;

    /**
     * Get the query grammar instance.
     */
    public function getGrammar(): QueryGrammarInterface;

    /**
     * Clone the query without the given properties.
     */
    public function cloneWithout(array $properties): static;

    /**
     * Clone the query without the given bindings.
     */
    public function cloneWithoutBindings(array $except): static;
}