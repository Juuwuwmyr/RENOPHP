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

/**
 * Query Builder
 * 
 * Provides a fluent interface for building database queries.
 */
class QueryBuilder implements QueryBuilderInterface
{
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