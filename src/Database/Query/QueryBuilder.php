<?php

declare(strict_types=1);

namespace Horizon\Database\Query;

use Closure;
use InvalidArgumentException;
use Horizon\Contracts\Database\ConnectionInterface;
use Horizon\Contracts\Database\QueryBuilderInterface;
use Horizon\Contracts\Database\QueryGrammarInterface;
use Horizon\Contracts\Database\ProcessorInterface;
use Horizon\Database\Query\JoinClause;
use Horizon\Database\Query\Expression;
use Horizon\Database\Concerns\PreventsSqlInjection;

/**
 * Query Builder
 * 
 * Provides a fluent interface for building database queries.
 */
class QueryBuilder implements QueryBuilderInterface
{
    use PreventsSqlInjection;
    /**
     * The database connection instance.
     */
    public ConnectionInterface $connection;

    /**
     * The database query grammar instance.
     */
    public QueryGrammarInterface $grammar;

    /**
     * The database query post processor instance.
     */
    public ProcessorInterface $processor;

    /**
     * The current query value bindings.
     */
    public array $bindings = [
        'select' => [],
        'from' => [],
        'join' => [],
        'where' => [],
        'groupBy' => [],
        'having' => [],
        'order' => [],
        'union' => [],
        'unionOrder' => [],
    ];

    /**
     * An aggregate function and column to be run.
     */
    public ?array $aggregate = null;

    /**
     * The columns that should be returned.
     */
    public ?array $columns = null;

    /**
     * Indicates if the query returns distinct results.
     */
    public bool $distinct = false;

    /**
     * The table which the query is targeting.
     */
    public ?string $from = null;

    /**
     * The table joins for the query.
     */
    public array $joins = [];

    /**
     * The where constraints for the query.
     */
    public array $wheres = [];

    /**
     * The groupings for the query.
     */
    public array $groups = [];

    /**
     * The having constraints for the query.
     */
    public array $havings = [];

    /**
     * The orderings for the query.
     */
    public array $orders = [];

    /**
     * The maximum number of records to return.
     */
    public ?int $limit = null;

    /**
     * The number of records to skip.
     */
    public ?int $offset = null;
    /**
     * The query union statements.
     */
    public array $unions = [];

    /**
     * The maximum number of union records to return.
     */
    public ?int $unionLimit = null;

    /**
     * The number of union records to skip.
     */
    public ?int $unionOffset = null;

    /**
     * The orderings for the union query.
     */
    public array $unionOrders = [];

    /**
     * Indicates whether row locking is being used.
     */
    public bool|string $lock = false;

    /**
     * All of the available clause operators.
     */
    public array $operators = [
        '=', '<', '>', '<=', '>=', '<>', '!=', '<=>',
        'like', 'like binary', 'not like', 'ilike',
        '&', '|', '^', '<<', '>>', '&~', 'is', 'is not',
        'rlike', 'not rlike', 'regexp', 'not regexp',
        '~', '~*', '!~', '!~*', 'similar to',
        'not similar to', 'not ilike', '~~*', '!~~*',
    ];

    /**
     * Whether use write pdo for select.
     */
    public bool $useWritePdo = false;

    /**
     * Create a new query builder instance.
     */
    public function __construct(
        ConnectionInterface $connection,
        QueryGrammarInterface $grammar = null,
        ProcessorInterface $processor = null
    ) {
        $this->connection = $connection;
        $this->grammar = $grammar ?: $connection->getQueryGrammar();
        $this->processor = $processor ?: $connection->getPostProcessor();
    }

    /**
     * Set the columns to be selected.
     */
    public function select(array|string $columns = ['*']): static
    {
        $this->columns = [];
        $this->bindings['select'] = [];

        $columns = is_array($columns) ? $columns : func_get_args();

        foreach ($columns as $as => $column) {
            if (is_string($as) && $this->isQueryable($column)) {
                $this->selectSub($column, $as);
            } else {
                $this->columns[] = $column;
            }
        }

        return $this;
    }
    /**
     * Add a subselect expression to the query.
     */
    protected function selectSub(Closure|QueryBuilder|string $query, string $as): static
    {
        [$query, $bindings] = $this->createSub($query);

        return $this->selectRaw(
            '('.$query.') as '.$this->grammar->wrap($as), $bindings
        );
    }

    /**
     * Add a new "raw" select expression to the query.
     */
    public function selectRaw(string $expression, array $bindings = []): static
    {
        $this->addSelect(new Expression($expression));

        if ($bindings) {
            $this->addBinding($bindings, 'select');
        }

        return $this;
    }

    /**
     * Add a new select column to the query.
     */
    public function addSelect(array|string $column): static
    {
        $columns = is_array($column) ? $column : func_get_args();

        foreach ($columns as $as => $column) {
            if (is_string($as) && $this->isQueryable($column)) {
                if (is_null($this->columns)) {
                    $this->select($this->from.'.*');
                }

                $this->selectSub($column, $as);
            } else {
                $this->columns[] = $column;
            }
        }

        return $this;
    }

    /**
     * Force the query to only return distinct results.
     */
    public function distinct(): static
    {
        $columns = func_get_args();

        if (count($columns) > 0) {
            $this->distinct = is_array($columns[0]) || is_bool($columns[0]) ? $columns[0] : $columns;
        } else {
            $this->distinct = true;
        }

        return $this;
    }

    /**
     * Set the table which the query is targeting.
     */
    public function from(string $table, ?string $as = null): static
    {
        if ($this->isQueryable($table)) {
            return $this->fromSub($table, $as);
        }

        $this->from = $as ? "{$table} as {$as}" : $table;

        return $this;
    }
    /**
     * Makes "from" fetch from a subquery.
     */
    public function fromSub(Closure|QueryBuilder|string $query, string $as): static
    {
        [$query, $bindings] = $this->createSub($query);

        return $this->fromRaw('('.$query.') as '.$this->grammar->wrapTable($as), $bindings);
    }

    /**
     * Add a raw from clause to the query.
     */
    public function fromRaw(string $expression, array $bindings = []): static
    {
        $this->from = new Expression($expression);

        $this->addBinding($bindings, 'from');

        return $this;
    }

    /**
     * Add a join clause to the query.
     */
    public function join(string $table, string $first, ?string $operator = null, ?string $second = null, string $type = 'inner', bool $where = false): static
    {
        $join = $this->newJoinClause($this, $type, $table);

        // If the first "column" of the join is really a Closure instance the developer
        // is trying to build a join with a complex "on" clause containing more than
        // one condition, so we'll add the join and call a Closure with the query.
        if ($first instanceof Closure) {
            $first($join);

            $this->joins[] = $join;

            $this->addBinding($join->getBindings(), 'join');
        }

        // If the column is simply a string, we can assume the join simply has a basic
        // "on" clause with a single condition. So we will just build the join with
        // this simple join clauses attached to it. There is not a join callback.
        else {
            $method = $where ? 'where' : 'on';

            $this->joins[] = $join->$method($first, $operator, $second);

            $this->addBinding($join->getBindings(), 'join');
        }

        return $this;
    }

    /**
     * Add a left join to the query.
     */
    public function leftJoin(string $table, string $first, ?string $operator = null, ?string $second = null): static
    {
        return $this->join($table, $first, $operator, $second, 'left');
    }

    /**
     * Add a right join to the query.
     */
    public function rightJoin(string $table, string $first, ?string $operator = null, ?string $second = null): static
    {
        return $this->join($table, $first, $operator, $second, 'right');
    }
    /**
     * Add a cross join to the query.
     */
    public function crossJoin(string $table, ?Closure $first = null, ?string $operator = null, ?string $second = null): static
    {
        if ($first) {
            return $this->join($table, $first, $operator, $second, 'cross');
        }

        $this->joins[] = $this->newJoinClause($this, 'cross', $table);

        return $this;
    }

    /**
     * Get a new join clause.
     */
    protected function newJoinClause(QueryBuilder $parentQuery, string $type, string $table): JoinClause
    {
        return new JoinClause($parentQuery, $type, $table);
    }

    /**
     * Add a basic where clause to the query.
     */
    public function where(string|array|Closure $column, mixed $operator = null, mixed $value = null, string $boolean = 'and'): static
    {
        // If the column is an array, we will assume it is an array of key-value pairs
        // and can add them each as a where clause. We will maintain the boolean we
        // received when the method was called and pass it into the nested where.
        if (is_array($column)) {
            return $this->addArrayOfWheres($column, $boolean);
        }

        // Here we will make some assumptions about the operator. If only 2 values are
        // passed to the method, we will assume that the operator is an equals sign
        // and keep going. Otherwise, we'll require the operator to be passed in.
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        // If the columns is actually a Closure instance, we will assume the developer
        // wants to begin a nested where statement which is wrapped in parentheses.
        // We'll add that Closure to the query and return back out immediately.
        if ($column instanceof Closure && is_null($operator)) {
            return $this->whereNested($column, $boolean);
        }

        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if ($this->invalidOperator($operator)) {
            [$value, $operator] = [$operator, '='];
        }

        // If the value is a Closure, it means the developer is performing an entire
        // sub-select within the query and we will need to compile the sub-select
        // within the where clause to get the appropriate query record results.
        if ($value instanceof Closure) {
            return $this->whereSub($column, $operator, $value, $boolean);
        }

        // If the value is "null", we will just assume the developer wants to add a
        // where null clause to the query. So, we will allow a short-cut here to
        // that method for convenience so the developer doesn't have to check.
        if (is_null($value)) {
            return $this->whereNull($column, $boolean, $operator !== '=');
        }

        $type = 'Basic';

        $this->wheres[] = compact(
            'type', 'column', 'operator', 'value', 'boolean'
        );

        if (! $value instanceof Expression) {
            $this->addBinding($this->flattenValue($value), 'where');
        }

        return $this;
    }
    /**
     * Add an array of where clauses to the query.
     */
    protected function addArrayOfWheres(array $column, string $boolean, string $method = 'where'): static
    {
        return $this->whereNested(function ($query) use ($column, $method, $boolean) {
            foreach ($column as $key => $value) {
                if (is_numeric($key) && is_array($value)) {
                    $query->{$method}(...array_values($value));
                } else {
                    $query->$method($key, '=', $value, $boolean);
                }
            }
        }, $boolean);
    }

    /**
     * Prepare the value and operator for a where clause.
     */
    public function prepareValueAndOperator(mixed $value, mixed $operator, bool $useDefault = false): array
    {
        if ($useDefault) {
            return [$operator, '='];
        } elseif ($this->invalidOperatorAndValue($operator, $value)) {
            throw new InvalidArgumentException('Illegal operator and value combination.');
        }

        return [$value, $operator];
    }

    /**
     * Determine if the given operator and value combination is legal.
     */
    protected function invalidOperatorAndValue(string $operator, mixed $value): bool
    {
        return is_null($value) && in_array($operator, $this->operators) &&
               ! in_array($operator, ['=', '<>', '!=']);
    }

    /**
     * Determine if the given operator is supported.
     */
    protected function invalidOperator(string $operator): bool
    {
        return ! is_string($operator) || (! in_array(strtolower($operator), $this->operators, true) &&
               ! in_array(strtolower($operator), $this->grammar->getOperators(), true));
    }

    /**
     * Add an "or where" clause to the query.
     */
    public function orWhere(string|array|Closure $column, mixed $operator = null, mixed $value = null): static
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        return $this->where($column, $operator, $value, 'or');
    }

    /**
     * Add a "where" clause comparing two columns to the query.
     */
    public function whereColumn(string|array $first, ?string $operator = null, ?string $second = null, ?string $boolean = 'and'): static
    {
        // If the column is an array, we will assume it is an array of key-value pairs
        // and can add them each as a where clause. We will maintain the boolean we
        // received when the method was called and pass it into the nested where.
        if (is_array($first)) {
            return $this->addArrayOfWheres($first, $boolean, 'whereColumn');
        }

        // If the given operator is not found in the list of valid operators we will
        // assume that the developer is just short-cutting the '=' operators and
        // we will set the operators to '=' and set the values appropriately.
        if ($this->invalidOperator($operator)) {
            [$second, $operator] = [$operator, '='];
        }

        $type = 'Column';

        $this->wheres[] = compact(
            'type', 'first', 'operator', 'second', 'boolean'
        );

        return $this;
    }
    /**
     * Add a "where in" clause to the query.
     */
    public function whereIn(string $column, mixed $values, string $boolean = 'and', bool $not = false): static
    {
        $type = $not ? 'NotIn' : 'In';

        // If the value is a query builder, we will create a bind expression for the entire
        // sub-select. This allows the developer to do really complex where in statements
        // that include multiple selects and joins with ease, providing a convenient API.
        if ($this->isQueryable($values)) {
            [$query, $bindings] = $this->createSub($values);

            $values = [new Expression($query)];

            $this->addBinding($bindings, 'where');
        }

        // Next, if the value is Arrayable we need to cast it to its raw array form so we
        // have the underlying array value instead of an Arrayable object which is not
        // able to be added as a binding, etc. We will then add to the wheres array.
        if ($values instanceof Arrayable) {
            $values = $values->toArray();
        }

        $this->wheres[] = compact('type', 'column', 'values', 'boolean');

        // Finally we'll add a binding for each values unless that value is an expression
        // in which case we will just skip over it since it will be handled by the query
        // grammar and we don't need to add the individual values as bindings to query.
        $this->addBinding($this->cleanBindings($values), 'where');

        return $this;
    }

    /**
     * Add an "or where in" clause to the query.
     */
    public function orWhereIn(string $column, mixed $values): static
    {
        return $this->whereIn($column, $values, 'or');
    }

    /**
     * Add a "where not in" clause to the query.
     */
    public function whereNotIn(string $column, mixed $values, string $boolean = 'and'): static
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    /**
     * Add an "or where not in" clause to the query.
     */
    public function orWhereNotIn(string $column, mixed $values): static
    {
        return $this->whereNotIn($column, $values, 'or');
    }

    /**
     * Add a "where null" clause to the query.
     */
    public function whereNull(string $column, string $boolean = 'and', bool $not = false): static
    {
        $type = $not ? 'NotNull' : 'Null';

        $this->wheres[] = compact('type', 'column', 'boolean');

        return $this;
    }

    /**
     * Add an "or where null" clause to the query.
     */
    public function orWhereNull(string $column): static
    {
        return $this->whereNull($column, 'or');
    }

    /**
     * Add a "where not null" clause to the query.
     */
    public function whereNotNull(string $column, string $boolean = 'and'): static
    {
        return $this->whereNull($column, $boolean, true);
    }

    /**
     * Add an "or where not null" clause to the query.
     */
    public function orWhereNotNull(string $column): static
    {
        return $this->whereNotNull($column, 'or');
    }
    /**
     * Add a "where between" statement to the query.
     */
    public function whereBetween(string $column, array $values, string $boolean = 'and', bool $not = false): static
    {
        $type = 'between';

        $this->wheres[] = compact('type', 'column', 'values', 'boolean', 'not');

        $this->addBinding(array_slice($this->cleanBindings($values), 0, 2), 'where');

        return $this;
    }

    /**
     * Add an "or where between" statement to the query.
     */
    public function orWhereBetween(string $column, array $values): static
    {
        return $this->whereBetween($column, $values, 'or');
    }

    /**
     * Add a "where not between" statement to the query.
     */
    public function whereNotBetween(string $column, array $values, string $boolean = 'and'): static
    {
        return $this->whereBetween($column, $values, $boolean, true);
    }

    /**
     * Add an "or where not between" statement to the query.
     */
    public function orWhereNotBetween(string $column, array $values): static
    {
        return $this->whereNotBetween($column, $values, 'or');
    }

    /**
     * Add an "order by" clause to the query.
     */
    public function orderBy(string $column, string $direction = 'asc'): static
    {
        $direction = strtolower($direction);

        if (!in_array($direction, ['asc', 'desc'], true)) {
            throw new InvalidArgumentException('Order direction must be "asc" or "desc".');
        }

        $this->orders[] = [
            'column' => $column,
            'direction' => $direction,
        ];

        return $this;
    }

    /**
     * Add a descending "order by" clause to the query.
     */
    public function orderByDesc(string $column): static
    {
        return $this->orderBy($column, 'desc');
    }

    /**
     * Add an "order by" clause for a timestamp to the query.
     */
    public function latest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'desc');
    }

    /**
     * Add an "order by" clause for a timestamp to the query.
     */
    public function oldest(string $column = 'created_at'): static
    {
        return $this->orderBy($column, 'asc');
    }

    /**
     * Put the query's results in random order.
     */
    public function inRandomOrder(?string $seed = null): static
    {
        return $this->orderByRaw($this->grammar->compileRandom($seed));
    }

    /**
     * Add a raw "order by" clause to the query.
     */
    public function orderByRaw(string $sql, array $bindings = []): static
    {
        $type = 'Raw';

        $this->orders[] = compact('type', 'sql');

        $this->addBinding($bindings, 'order');

        return $this;
    }
    /**
     * Add a "group by" clause to the query.
     */
    public function groupBy(array|string ...$groups): static
    {
        foreach ($groups as $group) {
            $this->groups = array_merge(
                $this->groups,
                is_array($group) ? $group : func_get_args()
            );
        }

        return $this;
    }

    /**
     * Add a raw groupBy clause to the query.
     */
    public function groupByRaw(string $sql, array $bindings = []): static
    {
        $this->groups[] = new Expression($sql);

        $this->addBinding($bindings, 'groupBy');

        return $this;
    }

    /**
     * Add a "having" clause to the query.
     */
    public function having(string $column, ?string $operator = null, ?string $value = null, string $boolean = 'and'): static
    {
        $type = 'Basic';

        // Here we will make some assumptions about the operator. If only 2 values are
        // passed to the method, we will assume that the operator is an equals sign
        // and keep going. Otherwise, we'll require the operator to be passed in.
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        $this->havings[] = compact('type', 'column', 'operator', 'value', 'boolean');

        if (!$value instanceof Expression) {
            $this->addBinding($this->flattenValue($value), 'having');
        }

        return $this;
    }

    /**
     * Add an "or having" clause to the query.
     */
    public function orHaving(string $column, ?string $operator = null, ?string $value = null): static
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        return $this->having($column, $operator, $value, 'or');
    }

    /**
     * Add a raw having clause to the query.
     */
    public function havingRaw(string $sql, array $bindings = [], string $boolean = 'and'): static
    {
        $type = 'Raw';

        $this->havings[] = compact('type', 'sql', 'boolean');

        $this->addBinding($bindings, 'having');

        return $this;
    }

    /**
     * Add a raw or having clause to the query.
     */
    public function orHavingRaw(string $sql, array $bindings = []): static
    {
        return $this->havingRaw($sql, $bindings, 'or');
    }

    /**
     * Set the "limit" value of the query.
     */
    public function limit(int $value): static
    {
        $this->limit = $value > 0 ? $value : null;

        return $this;
    }

    /**
     * Alias to set the "limit" value of the query.
     */
    public function take(int $value): static
    {
        return $this->limit($value);
    }

    /**
     * Set the "offset" value of the query.
     */
    public function offset(int $value): static
    {
        $this->offset = max(0, $value);

        return $this;
    }

    /**
     * Alias to set the "offset" value of the query.
     */
    public function skip(int $value): static
    {
        return $this->offset($value);
    }
    /**
     * Execute the query as a "select" statement.
     */
    public function get(array|string $columns = ['*']): array
    {
        return $this->onceWithColumns(is_array($columns) ? $columns : func_get_args(), function () {
            return $this->processor->processSelect($this, $this->runSelect());
        });
    }

    /**
     * Run the query as a "select" statement against the connection.
     */
    protected function runSelect(): array
    {
        return $this->connection->select(
            $this->toSql(), $this->getBindings(), !$this->useWritePdo
        );
    }

    /**
     * Execute the given callback while selecting the given columns.
     */
    protected function onceWithColumns(array $columns, Closure $callback): mixed
    {
        $original = $this->columns;

        if (is_null($original)) {
            $this->columns = $columns;
        }

        $result = $callback();

        $this->columns = $original;

        return $result;
    }

    /**
     * Get a single column's value from the first result of a query.
     */
    public function value(string $column): mixed
    {
        $result = $this->first([$column]);

        return $result ? $result[$column] : null;
    }

    /**
     * Execute a query for a single record by ID.
     */
    public function find(int|string $id, array|string $columns = ['*']): ?array
    {
        return $this->where('id', '=', $id)->first($columns);
    }

    /**
     * Get the first result of the query.
     */
    public function first(array|string $columns = ['*']): ?array
    {
        $results = $this->take(1)->get($columns);

        return count($results) > 0 ? $results[0] : null;
    }

    /**
     * Execute the query and get the first result or throw an exception.
     */
    public function firstOrFail(array|string $columns = ['*']): array
    {
        $result = $this->first($columns);

        if (is_null($result)) {
            throw new ModelNotFoundException;
        }

        return $result;
    }

    /**
     * Execute an aggregate function on the database.
     */
    public function aggregate(string $function, array $columns = ['*']): mixed
    {
        $results = $this->cloneWithout(['columns'])
                        ->cloneWithoutBindings(['select'])
                        ->setAggregate($function, $columns)
                        ->get($columns);

        if (!empty($results)) {
            return array_change_key_case($results[0], CASE_LOWER)[$function];
        }

        return null;
    }

    /**
     * Set the aggregate property without running the query.
     */
    protected function setAggregate(string $function, array $columns): static
    {
        $this->aggregate = compact('function', 'columns');

        if (empty($this->groups)) {
            $this->orders = [];
            $this->bindings['order'] = [];
        }

        return $this;
    }
    /**
     * Retrieve the "count" result of the query.
     */
    public function count(string $columns = '*'): int
    {
        return (int) $this->aggregate('count', [$columns]);
    }

    /**
     * Retrieve the minimum value of a given column.
     */
    public function min(string $column): mixed
    {
        return $this->aggregate('min', [$column]);
    }

    /**
     * Retrieve the maximum value of a given column.
     */
    public function max(string $column): mixed
    {
        return $this->aggregate('max', [$column]);
    }

    /**
     * Retrieve the sum of the values of a given column.
     */
    public function sum(string $column): mixed
    {
        $result = $this->aggregate('sum', [$column]);

        return $result ?: 0;
    }

    /**
     * Retrieve the average of the values of a given column.
     */
    public function avg(string $column): mixed
    {
        return $this->aggregate('avg', [$column]);
    }

    /**
     * Alias for the "avg" method.
     */
    public function average(string $column): mixed
    {
        return $this->avg($column);
    }

    /**
     * Insert new records into the database.
     */
    public function insert(array $values): bool
    {
        // Since every insert gets treated like a batch insert, we will make sure the
        // bindings are structured in a way that is convenient when building these
        // inserts statements by verifying these elements are actually an array.
        if (empty($values)) {
            return true;
        }

        if (!is_array(reset($values))) {
            $values = [$values];
        } else {
            foreach ($values as $key => $value) {
                ksort($value);
                $values[$key] = $value;
            }
        }

        $this->applyBeforeQueryCallbacks();

        // Finally, we will run this query against the database connection and return
        // the results. We will need to also flatten these bindings before running
        // the query so they are all in one flat array for execution.
        return $this->connection->insert(
            $this->grammar->compileInsert($this, $values),
            $this->cleanBindings(array_merge_recursive($this->bindings, $this->grammar->prepareBindingsForInsert($this->bindings, $values)))
        );
    }

    /**
     * Insert new records into the database and return the ID.
     */
    public function insertGetId(array $values, ?string $sequence = null): int
    {
        $this->applyBeforeQueryCallbacks();

        $sql = $this->grammar->compileInsertGetId($this, $values, $sequence);

        $values = $this->cleanBindings($values);

        return $this->processor->processInsertGetId($this, $sql, $values, $sequence);
    }
    /**
     * Update records in the database.
     */
    public function update(array $values): int
    {
        $this->applyBeforeQueryCallbacks();

        $sql = $this->grammar->compileUpdate($this, $values);

        return $this->connection->update($sql, $this->cleanBindings(
            $this->grammar->prepareBindingsForUpdate($this->bindings, $values)
        ));
    }

    /**
     * Insert or update a record matching the attributes, and fill it with values.
     */
    public function updateOrInsert(array $attributes, array $values = []): bool
    {
        if (!$this->where($attributes)->exists()) {
            return $this->insert(array_merge($attributes, $values));
        }

        if (empty($values)) {
            return true;
        }

        return (bool) $this->limit(1)->update($values);
    }

    /**
     * Delete records from the database.
     */
    public function delete(?int $id = null): int
    {
        // If an ID is passed to the method, we will set the where clause to check the
        // ID to let developers to simply and quickly remove a single row from the
        // database without manually specifying the "where" clauses on the query.
        if (!is_null($id)) {
            $this->where($this->from.'.id', '=', $id);
        }

        $this->applyBeforeQueryCallbacks();

        return $this->connection->delete(
            $this->grammar->compileDelete($this), $this->cleanBindings(
                $this->grammar->prepareBindingsForDelete($this->bindings)
            )
        );
    }

    /**
     * Run a truncate statement on the table.
     */
    public function truncate(): void
    {
        $this->applyBeforeQueryCallbacks();

        foreach ($this->grammar->compileTruncate($this) as $sql => $bindings) {
            $this->connection->statement($sql, $bindings);
        }
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
     * Get the SQL representation of the query.
     */
    public function toSql(): string
    {
        $this->applyBeforeQueryCallbacks();

        return $this->grammar->compileSelect($this);
    }
    /**
     * Get the current query value bindings.
     */
    public function getBindings(): array
    {
        return array_values($this->bindings);
    }

    /**
     * Get the raw array of bindings.
     */
    public function getRawBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Set the bindings on the query builder.
     */
    public function setBindings(array $bindings, string $type = 'where'): static
    {
        if (!array_key_exists($type, $this->bindings)) {
            throw new InvalidArgumentException("Invalid binding type: {$type}.");
        }

        $this->bindings[$type] = $bindings;

        return $this;
    }

    /**
     * Add a binding to the query.
     */
    public function addBinding(mixed $value, string $type = 'where'): static
    {
        if (!array_key_exists($type, $this->bindings)) {
            throw new InvalidArgumentException("Invalid binding type: {$type}.");
        }

        if (is_array($value)) {
            $this->bindings[$type] = array_values(array_merge($this->bindings[$type], $value));
        } else {
            $this->bindings[$type][] = $value;
        }

        return $this;
    }

    /**
     * Remove all of the expressions from a list of bindings.
     */
    public function cleanBindings(array $bindings): array
    {
        return array_values(array_filter($bindings, function ($binding) {
            return !$binding instanceof Expression;
        }));
    }

    /**
     * Flatten an array of values and merge them into the bindings.
     */
    public function flattenValue(mixed $value): mixed
    {
        return is_array($value) ? head(Arr::flatten($value)) : $value;
    }

    /**
     * Get the database connection instance.
     */
    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    /**
     * Get the query processor instance.
     */
    public function getProcessor(): ProcessorInterface
    {
        return $this->processor;
    }

    /**
     * Get the query grammar instance.
     */
    public function getGrammar(): QueryGrammarInterface
    {
        return $this->grammar;
    }

    /**
     * Clone the query without the given properties.
     */
    public function cloneWithout(array $properties): static
    {
        $clone = clone $this;

        foreach ($properties as $property) {
            $clone->{$property} = null;
        }

        return $clone;
    }

    /**
     * Clone the query without the given bindings.
     */
    public function cloneWithoutBindings(array $except): static
    {
        $clone = clone $this;

        foreach ($except as $type) {
            $clone->bindings[$type] = [];
        }

        return $clone;
    }
    /**
     * Create a subquery and parse it.
     */
    protected function createSub(Closure|QueryBuilder|string $query): array
    {
        // If the given query is a Closure, we will execute it while passing in a new
        // query instance to the Closure. This will give the developer a chance to
        // format and work with a fresh query instance for complex nested wheres.
        if ($query instanceof Closure) {
            $callback = $query;

            $callback($query = $this->forSubQuery());
        }

        return $this->parseSub($query);
    }

    /**
     * Parse the subquery into SQL and bindings.
     */
    protected function parseSub(QueryBuilder|string $query): array
    {
        if ($query instanceof QueryBuilder) {
            return [$query->toSql(), $query->getBindings()];
        } elseif (is_string($query)) {
            return [$query, []];
        } else {
            throw new InvalidArgumentException('A subquery must be a query builder instance, a Closure, or a string.');
        }
    }

    /**
     * Creates a subquery and parse it.
     */
    protected function forSubQuery(): static
    {
        return $this->newQuery();
    }

    /**
     * Get a new instance of the query builder.
     */
    public function newQuery(): static
    {
        return new static($this->connection, $this->grammar, $this->processor);
    }

    /**
     * Determine if the value is a query builder instance or a Closure.
     */
    protected function isQueryable(mixed $value): bool
    {
        return $value instanceof static ||
               $value instanceof QueryBuilderInterface ||
               $value instanceof Closure;
    }

    /**
     * Apply the callback if the given "value" is (or resolves to) truthy.
     */
    public function when(mixed $value, Closure $callback, ?Closure $default = null): static
    {
        $value = $value instanceof Closure ? $value($this) : $value;

        if ($value) {
            return $callback($this, $value) ?: $this;
        } elseif ($default) {
            return $default($this, $value) ?: $this;
        }

        return $this;
    }

    /**
     * Apply the callback if the given "value" is (or resolves to) falsy.
     */
    public function unless(mixed $value, Closure $callback, ?Closure $default = null): static
    {
        $value = $value instanceof Closure ? $value($this) : $value;

        if (!$value) {
            return $callback($this, $value) ?: $this;
        } elseif ($default) {
            return $default($this, $value) ?: $this;
        }

        return $this;
    }

    /**
     * Add a nested where statement to the query.
     */
    public function whereNested(Closure $callback, string $boolean = 'and'): static
    {
        call_user_func($callback, $query = $this->forNestedWhere());

        return $this->addNestedWhereQuery($query, $boolean);
    }

    /**
     * Create a new query instance for nested where condition.
     */
    public function forNestedWhere(): static
    {
        return $this->newQuery()->from($this->from);
    }

    /**
     * Add another query builder as a nested where to the query builder.
     */
    public function addNestedWhereQuery(QueryBuilder $query, string $boolean = 'and'): static
    {
        if (count($query->wheres)) {
            $type = 'Nested';

            $this->wheres[] = compact('type', 'query', 'boolean');

            $this->addBinding($query->getRawBindings()['where'], 'where');
        }

        return $this;
    }

    /**
     * Add a sub-select to the query.
     */
    protected function whereSub(string $column, string $operator, Closure $callback, string $boolean): static
    {
        $type = 'Sub';

        // Once we have the query instance we can simply execute it so it can add all
        // of the sub-select's conditions to itself, and then we can cache it off
        // in the array of where clauses for the "main" parent query instance.
        call_user_func($callback, $query = $this->forSubQuery());

        $this->wheres[] = compact(
            'type', 'column', 'operator', 'query', 'boolean'
        );

        $this->addBinding($query->getBindings(), 'where');

        return $this;
    }

    /**
     * Apply the callback's query changes if the given "value" is true.
     */
    protected function applyBeforeQueryCallbacks(): void
    {
        // Hook for any pre-query processing
        // This can be extended by subclasses or through events
    }

    /**
     * Dump the current SQL and bindings.
     */
    public function dump(): static
    {
        dump($this->toSql(), $this->getBindings());

        return $this;
    }

    /**
     * Die and dump the current SQL and bindings.
     */
    public function dd(): void
    {
        $this->dump();
        
        exit(1);
    }
}