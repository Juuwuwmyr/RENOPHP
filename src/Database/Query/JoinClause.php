<?php

declare(strict_types=1);

namespace Reno\Database\Query;

use Closure;

/**
 * Join Clause
 * 
 * Represents a join clause in a database query.
 */
class JoinClause extends QueryBuilder
{
    /**
     * The type of join being performed.
     */
    public string $type;

    /**
     * The table the join clause is joining to.
     */
    public string $table;

    /**
     * The parent query builder instance.
     */
    protected QueryBuilder $parentQuery;

    /**
     * Create a new join clause instance.
     */
    public function __construct(QueryBuilder $parentQuery, string $type, string $table)
    {
        $this->type = $type;
        $this->table = $table;
        $this->parentQuery = $parentQuery;

        parent::__construct(
            $parentQuery->getConnection(),
            $parentQuery->getGrammar(),
            $parentQuery->getProcessor()
        );
    }

    /**
     * Add an "on" clause to the join.
     */
    public function on(string $first, string $operator = null, string $second = null, string $boolean = 'and'): static
    {
        if ($first instanceof Closure) {
            return $this->whereNested($first, $boolean);
        }

        return $this->whereColumn($first, $operator, $second, $boolean);
    }

    /**
     * Add an "or on" clause to the join.
     */
    public function orOn(string $first, string $operator = null, string $second = null): static
    {
        return $this->on($first, $operator, $second, 'or');
    }

    /**
     * Get the parent query builder instance.
     */
    public function getParentQuery(): QueryBuilder
    {
        return $this->parentQuery;
    }
}