<?php

declare(strict_types=1);

namespace Reno\Database\Query\Grammars;

use Reno\Contracts\Database\QueryGrammarInterface;
use Reno\Contracts\Database\QueryBuilderInterface;
use Reno\Database\Query\Expression;
use Reno\Database\Query\QueryBuilder;

/**
 * Grammar
 * 
 * Base grammar for compiling database queries.
 */
class Grammar implements QueryGrammarInterface
{
    /**
     * The grammar table prefix.
     */
    protected string $tablePrefix = '';

    /**
     * The components that make up a select clause.
     */
    protected array $selectComponents = [
        'aggregate',
        'columns',
        'from',
        'joins',
        'wheres',
        'groups',
        'havings',
        'orders',
        'limit',
        'offset',
        'lock',
    ];

    /**
     * All of the available clause operators.
     */
    protected array $operators = [];

    /**
     * Compile a select query into SQL.
     */
    public function compileSelect(QueryBuilderInterface $query): string
    {
        if ($query->unions && $query->aggregate) {
            return $this->compileUnionAggregate($query);
        }

        $sql = trim($this->concatenate(
            $this->compileComponents($query))
        );

        if ($query->unions) {
            $sql = $this->wrapUnion($sql) . ' ' . $this->compileUnions($query);
        }

        return $sql;
    }

    /**
     * Compile the components necessary for a select clause.
     */
    protected function compileComponents(QueryBuilderInterface $query): array
    {
        $sql = [];

        foreach ($this->selectComponents as $component) {
            if (isset($query->{$component}) && !is_null($query->{$component})) {
                $method = 'compile' . ucfirst($component);

                $sql[$component] = $this->$method($query, $query->{$component});
            }
        }

        return $sql;
    }

    /**
     * Compile an aggregated select clause.
     */
    protected function compileAggregate(QueryBuilderInterface $query, array $aggregate): string
    {
        $column = $this->columnize($aggregate['columns']);

        // If the query has a "distinct" constraint and we're not asking for all columns
        // we need to prepend "distinct" onto the column name so that the query takes
        // it into account when it performs the aggregating operations on the data.
        if (is_array($query->distinct)) {
            $column = 'distinct ' . $this->columnize($query->distinct);
        } elseif ($query->distinct && $column !== '*') {
            $column = 'distinct ' . $column;
        }

        return 'select ' . $aggregate['function'] . '(' . $column . ') as aggregate';
    }

    /**
     * Compile the "select *" portion of the query.
     */
    protected function compileColumns(QueryBuilderInterface $query, array $columns): string
    {
        // If the query is actually performing an aggregating select, we will let that
        // compiler handle the building of the select clauses, as it will need some
        // more syntax that is best handled by that function to keep things neat.
        if (!is_null($query->aggregate)) {
            return '';
        }

        if ($query->distinct) {
            $select = 'select distinct ';
        } else {
            $select = 'select ';
        }

        return $select . $this->columnize($columns);
    }

    /**
     * Compile the "from" portion of the query.
     */
    protected function compileFrom(QueryBuilderInterface $query, string $table): string
    {
        return 'from ' . $this->wrapTable($table);
    }

    /**
     * Compile the "join" portions of the query.
     */
    protected function compileJoins(QueryBuilderInterface $query, array $joins): string
    {
        return collect($joins)->map(function ($join) use ($query) {
            $table = $this->wrapTable($join->table);

            $nestedJoins = is_null($join->joins) ? '' : ' ' . $this->compileJoins($query, $join->joins);

            $tableAndNestedJoins = is_null($join->joins) ? $table : '(' . $table . $nestedJoins . ')';

            return trim("{$join->type} join {$tableAndNestedJoins} {$this->compileWheres($join)}");
        })->implode(' ');
    }

    /**
     * Compile the "where" portions of the query.
     */
    protected function compileWheres(QueryBuilderInterface $query): string
    {
        if (is_null($query->wheres)) {
            return '';
        }

        // Each type of where clauses has its own compiler function which is responsible
        // for actually creating the where clauses SQL. This helps keep the code nice
        // and maintainable since each clause has a very small method that it uses.
        if (count($sql = $this->compileWheresToArray($query)) > 0) {
            return $this->concatenateWhereClauses($query, $sql);
        }

        return '';
    }

    /**
     * Get an array of all the where clauses for the query.
     */
    protected function compileWheresToArray(QueryBuilderInterface $query): array
    {
        return collect($query->wheres)->map(function ($where) use ($query) {
            return $where['boolean'] . ' ' . $this->{"where{$where['type']}"}($query, $where);
        })->all();
    }
}
    /**
     * Format the where clause statements into one string.
     */
    protected function concatenateWhereClauses(QueryBuilderInterface $query, array $sql): string
    {
        $conjunction = $query instanceof JoinClause ? 'on' : 'where';

        return $conjunction . ' ' . $this->removeLeadingBoolean(implode(' ', $sql));
    }

    /**
     * Remove the leading boolean from a statement.
     */
    protected function removeLeadingBoolean(string $value): string
    {
        return preg_replace('/and |or /i', '', $value, 1);
    }

    /**
     * Compile a basic where clause.
     */
    protected function whereBasic(QueryBuilderInterface $query, array $where): string
    {
        $value = $this->parameter($where['value']);

        $operator = str_replace('?', '??', $where['operator']);

        return $this->wrap($where['column']) . ' ' . $operator . ' ' . $value;
    }

    /**
     * Compile a "where in" clause.
     */
    protected function whereIn(QueryBuilderInterface $query, array $where): string
    {
        if (!empty($where['values'])) {
            return $this->wrap($where['column']) . ' in (' . $this->parameterize($where['values']) . ')';
        }

        return '0 = 1';
    }

    /**
     * Compile a "where not in" clause.
     */
    protected function whereNotIn(QueryBuilderInterface $query, array $where): string
    {
        if (!empty($where['values'])) {
            return $this->wrap($where['column']) . ' not in (' . $this->parameterize($where['values']) . ')';
        }

        return '1 = 1';
    }

    /**
     * Compile a "where null" clause.
     */
    protected function whereNull(QueryBuilderInterface $query, array $where): string
    {
        return $this->wrap($where['column']) . ' is null';
    }

    /**
     * Compile a "where not null" clause.
     */
    protected function whereNotNull(QueryBuilderInterface $query, array $where): string
    {
        return $this->wrap($where['column']) . ' is not null';
    }

    /**
     * Compile a "where between" clause.
     */
    protected function whereBetween(QueryBuilderInterface $query, array $where): string
    {
        $between = $where['not'] ? 'not between' : 'between';

        $min = $this->parameter(is_array($where['values']) ? reset($where['values']) : $where['values'][0]);
        
        $max = $this->parameter(is_array($where['values']) ? end($where['values']) : $where['values'][1]);

        return $this->wrap($where['column']) . ' ' . $between . ' ' . $min . ' and ' . $max;
    }

    /**
     * Compile a nested where clause.
     */
    protected function whereNested(QueryBuilderInterface $query, array $where): string
    {
        // Here we will calculate what portion of the string we need to remove. If this
        // is a join clause query, we need to remove the "on" portion of the SQL and
        // if it is a normal query we need to take the leading "where" of queries.
        $offset = $query instanceof JoinClause ? 3 : 6;

        return '(' . substr($this->compileWheres($where['query']), $offset) . ')';
    }

    /**
     * Compile a where clause comparing two columns.
     */
    protected function whereColumn(QueryBuilderInterface $query, array $where): string
    {
        return $this->wrap($where['first']) . ' ' . $where['operator'] . ' ' . $this->wrap($where['second']);
    }

    /**
     * Compile the "order by" portions of the query.
     */
    protected function compileOrders(QueryBuilderInterface $query, array $orders): string
    {
        if (!empty($orders)) {
            return 'order by ' . implode(', ', $this->compileOrdersToArray($query, $orders));
        }

        return '';
    }

    /**
     * Compile the query orders to an array.
     */
    protected function compileOrdersToArray(QueryBuilderInterface $query, array $orders): array
    {
        return array_map(function ($order) {
            return $order['sql'] ?? $this->wrap($order['column']) . ' ' . $order['direction'];
        }, $orders);
    }
    /**
     * Compile the "limit" portions of the query.
     */
    protected function compileLimit(QueryBuilderInterface $query, int $limit): string
    {
        return 'limit ' . (int) $limit;
    }

    /**
     * Compile the "offset" portions of the query.
     */
    protected function compileOffset(QueryBuilderInterface $query, int $offset): string
    {
        return 'offset ' . (int) $offset;
    }

    /**
     * Compile an insert statement into SQL.
     */
    public function compileInsert(QueryBuilderInterface $query, array $values): string
    {
        // Essentially we will force every insert to be treated as a batch insert which
        // simply makes creating the SQL easier for us since we can utilize the same
        // basic routine regardless of an amount of records given to us to insert.
        $table = $this->wrapTable($query->from);

        if (empty($values)) {
            return "insert into {$table} default values";
        }

        if (!is_array(reset($values))) {
            $values = [$values];
        }

        $columns = $this->columnize(array_keys(reset($values)));

        // We need to build a list of parameter place-holders of values that are bound
        // to the query. Each insert should have the exact same number of parameter
        // bindings so we will loop through the record and parameterize them all.
        $parameters = collect($values)->map(function ($record) {
            return '(' . $this->parameterize($record) . ')';
        })->implode(', ');

        return "insert into $table ($columns) values $parameters";
    }

    /**
     * Compile an insert and get ID statement into SQL.
     */
    public function compileInsertGetId(QueryBuilderInterface $query, array $values, ?string $sequence): string
    {
        return $this->compileInsert($query, $values);
    }

    /**
     * Compile an update statement into SQL.
     */
    public function compileUpdate(QueryBuilderInterface $query, array $values): string
    {
        $table = $this->wrapTable($query->from);

        $columns = $this->compileUpdateColumns($query, $values);

        $where = $this->compileWheres($query);

        return rtrim("update {$table} set $columns $where");
    }

    /**
     * Compile the columns for an update statement.
     */
    protected function compileUpdateColumns(QueryBuilderInterface $query, array $values): string
    {
        return collect($values)->map(function ($value, $key) {
            return $this->wrap($key) . ' = ' . $this->parameter($value);
        })->implode(', ');
    }

    /**
     * Compile a delete statement into SQL.
     */
    public function compileDelete(QueryBuilderInterface $query): string
    {
        $table = $this->wrapTable($query->from);

        $where = $this->compileWheres($query);

        return rtrim("delete from $table $where");
    }

    /**
     * Compile a truncate table statement into SQL.
     */
    public function compileTruncate(QueryBuilderInterface $query): array
    {
        return ['truncate table ' . $this->wrapTable($query->from) => []];
    }

    /**
     * Compile an exists statement into SQL.
     */
    public function compileExists(QueryBuilderInterface $query): string
    {
        $existsQuery = clone $query;

        $existsQuery->columns = [];

        return $this->compileSelect($existsQuery->selectRaw('1 as "exists"')->limit(1));
    }

    /**
     * Concatenate an array of segments, removing empties.
     */
    protected function concatenate(array $segments): string
    {
        return implode(' ', array_filter($segments, function ($value) {
            return (string) $value !== '';
        }));
    }

    /**
     * Remove the leading boolean from a statement.
     */
    protected function removeLeadingBoolean(string $value): string
    {
        return preg_replace('/and |or /i', '', $value, 1);
    }
    /**
     * Compile the "group by" portions of the query.
     */
    protected function compileGroups(QueryBuilderInterface $query, array $groups): string
    {
        return 'group by ' . $this->columnize($groups);
    }

    /**
     * Compile the "having" portions of the query.
     */
    protected function compileHavings(QueryBuilderInterface $query, array $havings): string
    {
        $sql = implode(' ', array_map([$this, 'compileHaving'], $havings));

        return 'having ' . $this->removeLeadingBoolean($sql);
    }

    /**
     * Compile a single having clause.
     */
    protected function compileHaving(array $having): string
    {
        // If the having clause is "raw", we can just return the clause straight away
        // without doing any more processing on it. Otherwise, we will compile the
        // clause into SQL based on the components that make it up from builder.
        if ($having['type'] === 'Raw') {
            return $having['boolean'] . ' ' . $having['sql'];
        } elseif ($having['type'] === 'between') {
            return $this->compileHavingBetween($having);
        }

        return $this->compileBasicHaving($having);
    }

    /**
     * Compile a basic having clause.
     */
    protected function compileBasicHaving(array $having): string
    {
        $column = $this->wrap($having['column']);

        $parameter = $this->parameter($having['value']);

        return $having['boolean'] . ' ' . $column . ' ' . $having['operator'] . ' ' . $parameter;
    }

    /**
     * Compile the random statement into SQL.
     */
    public function compileRandom(?string $seed): string
    {
        return 'RAND(' . $seed . ')';
    }

    /**
     * Wrap a value in keyword identifiers.
     */
    public function wrap(string $value): string
    {
        if ($this->isExpression($value)) {
            return $this->getValue($value);
        }

        // If the value being wrapped has a column alias we will need to separate out
        // the pieces so we can wrap each of the segments of the expression on its
        // own, and then join these both back together using the "as" connector.
        if (stripos($value, ' as ') !== false) {
            return $this->wrapAliasedValue($value);
        }

        return $this->wrapSegments(explode('.', $value));
    }

    /**
     * Wrap a value that has an alias.
     */
    protected function wrapAliasedValue(string $value): string
    {
        $segments = preg_split('/\s+as\s+/i', $value);

        return $this->wrap($segments[0]) . ' as ' . $this->wrapValue($segments[1]);
    }

    /**
     * Wrap the given value segments.
     */
    protected function wrapSegments(array $segments): string
    {
        return collect($segments)->map(function ($segment, $key) use ($segments) {
            return $key == 0 && count($segments) > 1
                        ? $this->wrapTable($segment)
                        : $this->wrapValue($segment);
        })->implode('.');
    }

    /**
     * Wrap a single string in keyword identifiers.
     */
    protected function wrapValue(string $value): string
    {
        if ($value !== '*') {
            return '"' . str_replace('"', '""', $value) . '"';
        }

        return $value;
    }

    /**
     * Wrap a table in keyword identifiers.
     */
    public function wrapTable(string $table): string
    {
        if (!$this->isExpression($table)) {
            return $this->wrap($this->tablePrefix . $table);
        }

        return $this->getValue($table);
    }

    /**
     * Convert an array of column names into a delimited string.
     */
    public function columnize(array $columns): string
    {
        return implode(', ', array_map([$this, 'wrap'], $columns));
    }

    /**
     * Create query parameter place-holders for an array.
     */
    public function parameterize(array $values): string
    {
        return implode(', ', array_map([$this, 'parameter'], $values));
    }

    /**
     * Get the appropriate query parameter place-holder for a value.
     */
    public function parameter(mixed $value): string
    {
        return $this->isExpression($value) ? $this->getValue($value) : '?';
    }

    /**
     * Get the value of a raw expression.
     */
    public function getValue(mixed $expression): mixed
    {
        return $expression instanceof Expression ? $expression->getValue() : $expression;
    }

    /**
     * Determine if the given value is a raw expression.
     */
    public function isExpression(mixed $value): bool
    {
        return $value instanceof Expression;
    }

    /**
     * Get the format for database stored dates.
     */
    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s';
    }

    /**
     * Get the grammar's table prefix.
     */
    public function getTablePrefix(): string
    {
        return $this->tablePrefix;
    }

    /**
     * Set the grammar's table prefix.
     */
    public function setTablePrefix(string $prefix): void
    {
        $this->tablePrefix = $prefix;
    }

    /**
     * Get the grammar's operators.
     */
    public function getOperators(): array
    {
        return $this->operators;
    }
}