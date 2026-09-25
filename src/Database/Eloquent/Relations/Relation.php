<?php

declare(strict_types=1);

namespace Horizon\Database\Eloquent\Relations;

use Closure;
use Horizon\Database\Eloquent\Builder;
use Horizon\Database\Eloquent\Model;
use Horizon\Database\Query\QueryBuilder;
use Horizon\Support\Collection;

/**
 * Base Relation Class
 * 
 * Base class for all Eloquent relationships.
 */
abstract class Relation
{
    /**
     * The Eloquent query builder instance.
     */
    protected Builder $query;

    /**
     * The parent model instance.
     */
    protected Model $parent;

    /**
     * The related model instance.
     */
    protected Model $related;

    /**
     * Indicates whether the relation is adding constraints.
     */
    protected static bool $constraints = true;

    /**
     * An array to map class names to their morph names in database.
     */
    public static array $morphMap = [];

    /**
     * Create a new relation instance.
     */
    public function __construct(Builder $query, Model $parent)
    {
        $this->query = $query;
        $this->parent = $parent;
        $this->related = $query->getModel();

        $this->addConstraints();
    }

    /**
     * Set the base constraints on the relation query.
     */
    abstract public function addConstraints(): void;

    /**
     * Set the constraints for an eager load of the relation.
     */
    abstract public function addEagerConstraints(array $models): void;

    /**
     * Initialize the relation on a set of models.
     */
    abstract public function initRelation(array $models, string $relation): array;

    /**
     * Match the eagerly loaded results to their parents.
     */
    abstract public function match(array $models, Collection $results, string $relation): array;

    /**
     * Get the results of the relationship.
     */
    abstract public function getResults();

    /**
     * Get the relationship for eager loading.
     */
    public function getEager(): Collection
    {
        return $this->get();
    }

    /**
     * Execute the query as a "select" statement.
     */
    public function get(array $columns = ['*']): Collection
    {
        return $this->query->get($columns);
    }

    /**
     * Touch all of the related models for the relationship.
     */
    public function touch(): void
    {
        $column = $this->getRelated()->getUpdatedAtColumn();

        $this->rawUpdate([$column => $this->getRelated()->freshTimestamp()]);
    }

    /**
     * Run a raw update against the base query.
     */
    public function rawUpdate(array $attributes = []): int
    {
        return $this->query->withoutGlobalScopes()->update($attributes);
    }

    /**
     * Get the underlying query for the relation.
     */
    public function getBaseQuery(): QueryBuilder
    {
        return $this->query->getQuery();
    }

    /**
     * Get the parent model of the relation.
     */
    public function getParent(): Model
    {
        return $this->parent;
    }

    /**
     * Get the fully qualified parent key name.
     */
    public function getQualifiedParentKeyName(): string
    {
        return $this->parent->getQualifiedKeyName();
    }

    /**
     * Get the related model of the relation.
     */
    public function getRelated(): Model
    {
        return $this->related;
    }

    /**
     * Get the name of the "created at" column.
     */
    public function createdAt(): string
    {
        return $this->parent->getCreatedAtColumn();
    }

    /**
     * Get the name of the "updated at" column.
     */
    public function updatedAt(): string
    {
        return $this->parent->getUpdatedAtColumn();
    }

    /**
     * Get the name of the related model's "updated at" column.
     */
    public function relatedUpdatedAt(): string
    {
        return $this->related->getUpdatedAtColumn();
    }

    /**
     * Wrap the given value with the parent query's grammar.
     */
    public function wrap(string $value): string
    {
        return $this->parent->newQueryWithoutRelationships()->getQuery()->getGrammar()->wrap($value);
    }

    /**
     * Run a callback with constraints disabled on the relation.
     */
    public static function noConstraints(Closure $callback)
    {
        $previous = static::$constraints;

        static::$constraints = false;

        // When resetting the relation where clause, we want to shift the first element
        // off of the bindings, leaving only the constraints that the developers put
        // as "extra" on the relationships, and not original relation constraints.
        try {
            return $callback();
        } finally {
            static::$constraints = $previous;
        }
    }

    /**
     * Get all of the primary keys for an array of models.
     */
    protected function getKeys(array $models, ?string $key = null): array
    {
        return collect($models)->map(function ($value) use ($key) {
            return $key ? $value->getAttribute($key) : $value->getKey();
        })->values()->unique(null, true)->sort()->all();
    }

    /**
     * Get the query builder that will contain the relationship constraints.
     */
    protected function getRelationQuery(Builder $query, Builder $parentQuery, $columns = ['*']): Builder
    {
        return $query->select($columns);
    }

    /**
     * Get a relationship join table hash.
     */
    public function getRelationCountHash(bool $incrementJoinCount = true): string
    {
        return 'laravel_reserved_' . ($incrementJoinCount ? static::$selfJoinCount++ : static::$selfJoinCount);
    }

    /**
     * Get the morph type for the given class.
     */
    public static function getMorphedModel(string $alias): ?string
    {
        return static::$morphMap[$alias] ?? null;
    }

    /**
     * Set the morph map for polymorphic relations.
     */
    public static function morphMap(array $map, bool $merge = true): array
    {
        static::$morphMap = $merge ? array_merge(static::$morphMap, $map) : $map;

        return static::$morphMap;
    }

    /**
     * Get the model morph map.
     */
    public static function getMorphMap(): array
    {
        return static::$morphMap;
    }

    /**
     * Get the morph alias for the given class.
     */
    public static function getMorphAlias(string $className): string
    {
        $morphMap = static::getMorphMap();

        if (!empty($morphMap) && in_array($className, $morphMap)) {
            return array_search($className, $morphMap, true);
        }

        return $className;
    }

    /**
     * Determine whether close is required to chunk.
     */
    public function shouldSelect(array $columns = ['*']): array
    {
        if ($columns == ['*']) {
            $columns = [$this->related->getTable().'.*'];
        }

        return array_merge($columns, $this->query->getQuery()->columns ?? []);
    }

    /**
     * Clone the Eloquent query builder.
     */
    public function __clone()
    {
        $this->query = clone $this->query;
    }

    /**
     * Handle dynamic method calls to the relationship.
     */
    public function __call(string $method, array $parameters)
    {
        if (static::hasMacro($method)) {
            return $this->macroCall($method, $parameters);
        }

        return $this->forwardCallTo($this->query, $method, $parameters);
    }

    /**
     * Force a clone of the underlying query builder when cloning.
     */
    public function __clone()
    {
        $this->query = clone $this->query;
    }

    /**
     * The self join count to avoid collisions.
     */
    protected static int $selfJoinCount = 0;
}