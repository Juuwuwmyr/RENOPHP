<?php

declare(strict_types=1);

namespace Horizon\Database\Query\Concerns;

use Closure;
use Horizon\Database\Query\Expression;

/**
 * Builds Queries Trait
 * 
 * Provides common query building functionality.
 */
trait BuildsQueries
{
    /**
     * Chunk the results of the query.
     */
    public function chunk(int $count, callable $callback): bool
    {
        $this->enforceOrderBy();

        $page = 1;

        do {
            // We'll execute the query for the given page and get the results. If there are
            // no results we can just break and return from here. When there are results
            // we will call the callback with the current chunk of these results here.
            $results = $this->forPage($page, $count)->get();

            $countResults = count($results);

            if ($countResults == 0) {
                break;
            }

            // On each chunk result set, we will pass them to the callback and then let the
            // developer take care of everything within the callback, which allows us to
            // keep the memory low for spinning through large result sets for working.
            if ($callback($results, $page) === false) {
                return false;
            }

            unset($results);

            $page++;
        } while ($countResults == $count);

        return true;
    }

    /**
     * Execute a callback over each item while chunking.
     */
    public function each(callable $callback, int $count = 1000): bool
    {
        return $this->chunk($count, function ($results) use ($callback) {
            foreach ($results as $key => $value) {
                if ($callback($value, $key) === false) {
                    return false;
                }
            }
        });
    }

    /**
     * Chunk the results of a query by comparing IDs.
     */
    public function chunkById(int $count, callable $callback, ?string $column = null, ?string $alias = null): bool
    {
        $column = $column ?? $this->defaultKeyName();
        $alias = $alias ?? $column;

        $lastId = null;

        $page = 1;

        do {
            $clone = clone $this;

            // We'll execute the query for the given page and get the results. If there are
            // no results we can just break and return from here. When there are results
            // we will call the callback with the current chunk of these results here.
            $results = $clone->forPageAfterId($count, $lastId, $column)->get();

            $countResults = count($results);

            if ($countResults == 0) {
                break;
            }

            // On each chunk result set, we will pass them to the callback and then let the
            // developer take care of everything within the callback, which allows us to
            // keep the memory low for spinning through large result sets for working.
            if ($callback($results, $page) === false) {
                return false;
            }

            $lastId = data_get(last($results), $alias);

            if ($lastId === null) {
                throw new RuntimeException("The chunkById operation was aborted because the [{$alias}] column is not present in the query result.");
            }

            unset($results);

            $page++;
        } while ($countResults == $count);

        return true;
    }

    /**
     * Execute the query and get the first result or execute a callback.
     */
    public function firstOr(array $columns = ['*'], ?Closure $callback = null): mixed
    {
        if ($columns instanceof Closure) {
            $callback = $columns;

            $columns = ['*'];
        }

        $result = $this->first($columns);

        if (!is_null($result)) {
            return $result;
        }

        return $callback ? $callback() : null;
    }

    /**
     * Get a single column's value from the first result of the query.
     */
    public function value(string $column): mixed
    {
        if ($result = $this->first([$column])) {
            return $result->{$column};
        }
    }

    /**
     * Get a single column's value from the first result of a query if it's the sole matching record.
     */
    public function soleValue(string $column): mixed
    {
        if ($result = $this->sole([$column])) {
            return $result->{$column};
        }
    }

    /**
     * Execute the query and get the first result if it's the sole matching record.
     */
    public function sole(array $columns = ['*']): mixed
    {
        $result = $this->take(2)->get($columns);

        $count = count($result);

        if ($count === 0) {
            throw new ModelNotFoundException;
        }

        if ($count > 1) {
            throw new MultipleRecordsFoundException;
        }

        return $result[0];
    }

    /**
     * Get an array with the values of a given column.
     */
    public function pluck(string $column, ?string $key = null): array
    {
        // First, we will need to select the results of the query accounting for the
        // given columns / key. Once we have the results, we will be able to take
        // the results and get the exact data that was requested for the query.
        $queryResult = $this->onceWithColumns(
            is_null($key) ? [$column] : [$column, $key],
            function () {
                return $this->processor->processSelect(
                    $this, $this->runSelect()
                );
            }
        );

        if (empty($queryResult)) {
            return [];
        }

        // If the columns are qualified with a table or have an alias, we cannot use
        // those directly in the "pluck" operations since the results from the DB
        // are only keyed by the column itself. We'll strip the table out here.
        $column = $this->stripTableForPluck($column);

        $key = $this->stripTableForPluck($key);

        return $this->pluckFromArrayColumn($queryResult, $column, $key);
    }

    /**
     * Strip off the table name or alias from a column identifier.
     */
    protected function stripTableForPluck(string $column): string
    {
        if (is_null($column)) {
            return $column;
        }

        $separator = str_contains(strtolower($column), ' as ') ? ' as ' : '\.';

        return last(preg_split('~' . $separator . '~i', $column));
    }

    /**
     * Retrieve column values from rows represented as objects.
     */
    protected function pluckFromArrayColumn(array $queryResult, string $column, ?string $key): array
    {
        $results = [];

        if (is_null($key)) {
            foreach ($queryResult as $row) {
                $results[] = $row->{$column};
            }
        } else {
            foreach ($queryResult as $row) {
                $results[$row->{$key}] = $row->{$column};
            }
        }

        return $results;
    }

    /**
     * Concatenate values of a given column as a string.
     */
    public function implode(string $column, string $glue = ''): string
    {
        return implode($glue, $this->pluck($column));
    }

    /**
     * Determine if any rows exist for the current query.
     */
    public function exists(): bool
    {
        $this->applyBeforeQueryCallbacks();

        $results = $this->connection->select(
            $this->grammar->compileExists($this), $this->getBindings(), !$this->useWritePdo
        );

        // If the results have rows, we will get the row and see if the exists column is a
        // boolean true. If there is no results, we will return false as there are no
        // rows for this query in the database and we can return that info to the dev.
        if (isset($results[0])) {
            $results = (array) $results[0];

            return (bool) $results['exists'];
        }

        return false;
    }

    /**
     * Determine if no rows exist for the current query.
     */
    public function doesntExist(): bool
    {
        return !$this->exists();
    }

    /**
     * Execute the given callback if no rows exist for the current query.
     */
    public function existsOr(Closure $callback): mixed
    {
        return $this->exists() ? true : $callback();
    }

    /**
     * Execute the given callback if rows exist for the current query.
     */
    public function doesntExistOr(Closure $callback): mixed
    {
        return $this->doesntExist() ? true : $callback();
    }
}