<?php

declare(strict_types=1);

namespace Horizon\Database\Eloquent\Relations;

use Horizon\Database\Eloquent\Builder;
use Horizon\Database\Eloquent\Collection;
use Horizon\Database\Eloquent\Model;

/**
 * Belongs To Relationship
 * 
 * Represents an inverse one-to-one or one-to-many relationship
 * where the foreign key is on the parent model.
 */
class BelongsTo extends Relation
{
    /**
     * The child model instance of the relation.
     */
    protected Model $child;

    /**
     * The foreign key of the parent model.
     */
    protected string $foreignKey;

    /**
     * The associated key on the parent model.
     */
    protected string $ownerKey;

    /**
     * The name of the relationship.
     */
    protected string $relationName;

    /**
     * The count of self joins.
     */
    protected static int $selfJoinCount = 0;

    /**
     * Create a new belongs to relationship instance.
     */
    public function __construct(Builder $query, Model $child, string $foreignKey, string $ownerKey, string $relationName)
    {
        $this->ownerKey = $ownerKey;
        $this->relationName = $relationName;
        $this->foreignKey = $foreignKey;
        $this->child = $child;

        parent::__construct($query, $child);
    }

    /**
     * Get the results of the relationship.
     */
    public function getResults()
    {
        if (is_null($this->child->{$this->foreignKey})) {
            return $this->getDefaultFor($this->parent);
        }

        return $this->query->first() ?: $this->getDefaultFor($this->parent);
    }

    /**
     * Set the base constraints on the relation query.
     */
    public function addConstraints(): void
    {
        if (static::$constraints) {
            // For belongs to relationships, which are essentially the inverse of has one
            // or has many relationships, we need to actually query on the primary key
            // of the related models matching on the foreign key that's on a parent.
            $table = $this->related->getTable();

            $this->query->where($table.'.'.$this->ownerKey, '=', $this->child->{$this->foreignKey});
        }
    }

    /**
     * Set the constraints for an eager load of the relation.
     */
    public function addEagerConstraints(array $models): void
    {
        // We'll grab the primary key name of the related models since it could be set to
        // a non-standard name and not "id". We will then construct the constraint for
        // our eagerly loading query so it returns the proper models from execution.
        $key = $this->related->getTable().'.'.$this->ownerKey;

        $whereIn = $this->whereInMethod($this->related, $this->ownerKey);

        $this->query->{$whereIn}($key, $this->getEagerModelKeys($models));
    }

    /**
     * Gather the keys from an array of related models.
     */
    protected function getEagerModelKeys(array $models): array
    {
        $keys = [];

        // First we need to gather all of the keys from the parent models so we know what
        // to query for via the eager loading query. We will add them to an array then
        // execute a "where in" statement to gather up all of those related records.
        foreach ($models as $model) {
            if (!is_null($value = $model->{$this->foreignKey})) {
                $keys[] = $value;
            }
        }

        sort($keys);

        return array_values(array_unique($keys));
    }

    /**
     * Initialize the relation on a set of models.
     */
    public function initRelation(array $models, string $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->getDefaultFor($model));
        }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents.
     */
    public function match(array $models, Collection $results, string $relation): array
    {
        $foreign = $this->foreignKey;

        $owner = $this->ownerKey;

        // First we will get to build a dictionary of the child models by their primary
        // key of the relationship, then we can easily match the children back onto
        // the parents using that dictionary and the primary key of the children.
        $dictionary = [];

        foreach ($results as $result) {
            $attribute = $result->getAttribute($owner);

            $dictionary[$attribute] = $result;
        }

        // Once we have the dictionary constructed, we can loop through all the parents
        // and match back onto their children using these keys of the dictionary and
        // the primary key of the children to map them onto the correct instances.
        foreach ($models as $model) {
            $attribute = $model->getAttribute($foreign);

            if (isset($dictionary[$attribute])) {
                $model->setRelation($relation, $dictionary[$attribute]);
            }
        }

        return $models;
    }

    /**
     * Update the parent model on the relationship.
     */
    public function update(array $attributes): int
    {
        return $this->getResults()->fill($attributes)->save();
    }

    /**
     * Associate the model instance to the given parent.
     */
    public function associate($model): Model
    {
        $ownerKey = $model instanceof Model ? $model->getAttribute($this->ownerKey) : $model;

        $this->child->setAttribute($this->foreignKey, $ownerKey);

        if ($model instanceof Model) {
            $this->child->setRelation($this->relationName, $model);
        } elseif ($this->child->isDirty($this->foreignKey)) {
            $this->child->unsetRelation($this->relationName);
        }

        return $this->child;
    }

    /**
     * Dissociate previously associated model from the given parent.
     */
    public function dissociate(): Model
    {
        $this->child->setAttribute($this->foreignKey, null);

        return $this->child->setRelation($this->relationName, null);
    }

    /**
     * Add the constraints for a relationship query.
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*']): Builder
    {
        if ($parentQuery->getQuery()->from === $query->getQuery()->from) {
            return $this->getRelationExistenceQueryForSelfRelation($query, $parentQuery, $columns);
        }

        return $query->select($columns)->whereColumn(
            $this->getQualifiedForeignKeyName(), '=', $query->qualifyColumn($this->ownerKey)
        );
    }

    /**
     * Add the constraints for a relationship query on the same table.
     */
    public function getRelationExistenceQueryForSelfRelation(Builder $query, Builder $parentQuery, $columns = ['*']): Builder
    {
        $table = $this->related->getTable();

        $from = $table.' as '.$hash = $this->getRelationCountHash();

        $query->getModel()->setTable($hash);

        return $query->select($columns)->from($from)->whereColumn(
            $this->getQualifiedForeignKeyName(), '=', $hash.'.'.$this->ownerKey
        );
    }

    /**
     * Get a relationship join table hash.
     */
    public function getRelationCountHash(bool $incrementJoinCount = true): string
    {
        return 'laravel_reserved_'.($incrementJoinCount ? static::$selfJoinCount++ : static::$selfJoinCount);
    }

    /**
     * Determine if the related model has an auto-incrementing ID.
     */
    protected function relationHasIncrementingId(): bool
    {
        return $this->related->getIncrementing() && 
               in_array($this->related->getKeyType(), ['int', 'integer']);
    }

    /**
     * Make a new related instance for the given model.
     */
    protected function newRelatedInstanceFor(Model $parent): Model
    {
        return $this->related->newInstance();
    }

    /**
     * Get the child of the relationship.
     */
    public function getChild(): Model
    {
        return $this->child;
    }

    /**
     * Get the foreign key of the relationship.
     */
    public function getForeignKeyName(): string
    {
        return $this->foreignKey;
    }

    /**
     * Get the fully qualified foreign key of the relationship.
     */
    public function getQualifiedForeignKeyName(): string
    {
        return $this->child->qualifyColumn($this->foreignKey);
    }

    /**
     * Get the associated key of the relationship.
     */
    public function getOwnerKeyName(): string
    {
        return $this->ownerKey;
    }

    /**
     * Get the fully qualified associated key of the relationship.
     */
    public function getQualifiedOwnerKeyName(): string
    {
        return $this->related->qualifyColumn($this->ownerKey);
    }

    /**
     * Get the name of the relationship.
     */
    public function getRelationName(): string
    {
        return $this->relationName;
    }

    /**
     * Get the default value for this relation.
     */
    protected function getDefaultFor(Model $model): ?Model
    {
        if (!$this->withDefault) {
            return null;
        }

        $instance = $this->newRelatedInstanceFor($model);

        if (is_callable($this->withDefault)) {
            return ($this->withDefault)($instance, $model) ?: $instance;
        }

        if (is_array($this->withDefault)) {
            $instance->forceFill($this->withDefault);
        }

        return $instance;
    }

    /**
     * Return a new model instance in case the relationship does not exist.
     */
    public function withDefault($callback = true): static
    {
        $this->withDefault = $callback;

        return $this;
    }

    /**
     * Determine which key to use for the "whereIn" method.
     */
    protected function whereInMethod(Model $model, string $key): string
    {
        return $model->getKeyName() === last(explode('.', $key))
                    && in_array($model->getKeyType(), ['int', 'integer'])
                        ? 'whereIntegerInRaw'
                        : 'whereIn';
    }
}