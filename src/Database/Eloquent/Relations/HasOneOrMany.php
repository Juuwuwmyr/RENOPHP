<?php

declare(strict_types=1);

namespace Reno\Database\Eloquent\Relations;

use Reno\Database\Eloquent\Builder;
use Reno\Database\Eloquent\Model;
use Reno\Support\Collection;

/**
 * Has One Or Many Base Class
 * 
 * Base class for HasOne and HasMany relationships.
 */
abstract class HasOneOrMany extends Relation
{
    /**
     * The foreign key of the parent model.
     */
    protected string $foreignKey;

    /**
     * The local key of the parent model.
     */
    protected string $localKey;

    /**
     * The count of self joins.
     */
    protected static int $selfJoinCount = 0;

    /**
     * Indicates whether the relation should be default.
     */
    protected $withDefault;

    /**
     * Create a new has one or many relationship instance.
     */
    public function __construct(Builder $query, Model $parent, string $foreignKey, string $localKey)
    {
        $this->localKey = $localKey;
        $this->foreignKey = $foreignKey;

        parent::__construct($query, $parent);
    }

    /**
     * Set the base constraints on the relation query.
     */
    public function addConstraints(): void
    {
        if (static::$constraints) {
            $this->query->where($this->foreignKey, '=', $this->getParentKey());

            $this->query->whereNotNull($this->foreignKey);
        }
    }

    /**
     * Set the constraints for an eager load of the relation.
     */
    public function addEagerConstraints(array $models): void
    {
        $whereIn = $this->whereInMethod($this->parent, $this->localKey);

        $this->query->{$whereIn}(
            $this->foreignKey, $this->getKeys($models, $this->localKey)
        );
    }

    /**
     * Get the key for comparing against the parent key in "has" query.
     */
    public function getExistenceCompareKey(): string
    {
        return $this->getQualifiedForeignKeyName();
    }

    /**
     * Get the key value of the parent's local key.
     */
    public function getParentKey()
    {
        return $this->parent->getAttribute($this->localKey);
    }

    /**
     * Get the fully qualified parent key name.
     */
    public function getQualifiedParentKeyName(): string
    {
        return $this->parent->qualifyColumn($this->localKey);
    }

    /**
     * Get the plain foreign key.
     */
    public function getForeignKeyName(): string
    {
        $segments = explode('.', $this->foreignKey);

        return end($segments);
    }

    /**
     * Get the foreign key for the relationship.
     */
    public function getQualifiedForeignKeyName(): string
    {
        return $this->foreignKey;
    }

    /**
     * Get the local key for the relationship.
     */
    public function getLocalKeyName(): string
    {
        return $this->localKey;
    }

    /**
     * Save a new model and attach it to the parent model.
     */
    public function save(Model $model): Model
    {
        $this->setForeignAttributesForCreate($model);

        return $model->save() ? $model : false;
    }

    /**
     * Save a new model and attach it to the parent model.
     */
    public function saveMany($models): Collection
    {
        foreach ($models as $model) {
            $this->save($model);
        }

        return $this->parent->{$this->getRelationName()};
    }

    /**
     * Create a new instance of the related model.
     */
    public function create(array $attributes = []): Model
    {
        return tap($this->related->newInstance($attributes), function ($instance) {
            $this->setForeignAttributesForCreate($instance);

            $instance->save();
        });
    }

    /**
     * Create a new instance of the related model. Allow mass-assignment.
     */
    public function forceCreate(array $attributes): Model
    {
        $attributes[$this->getForeignKeyName()] = $this->getParentKey();

        return $this->related->forceCreate($attributes);
    }

    /**
     * Create an array of new instances of the related model.
     */
    public function createMany(iterable $records): Collection
    {
        $instances = $this->related->newCollection();

        foreach ($records as $record) {
            $instances->push($this->create($record));
        }

        return $instances;
    }

    /**
     * Set the foreign ID for creating a related model.
     */
    protected function setForeignAttributesForCreate(Model $model): void
    {
        $model->setAttribute($this->getForeignKeyName(), $this->getParentKey());
    }

    /**
     * Add the constraints for a relationship query.
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*']): Builder
    {
        if ($query->getQuery()->from === $parentQuery->getQuery()->from) {
            return $this->getRelationExistenceQueryForSelfRelation($query, $parentQuery, $columns);
        }

        return parent::getRelationQuery($query, $parentQuery, $columns)->where(
            $this->getQualifiedForeignKeyName(), '=', $this->getQualifiedParentKeyName()
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

        return $query->select($columns)->from($from)
                     ->where($hash.'.'.$this->getForeignKeyName(), '=', $this->getQualifiedParentKeyName());
    }

    /**
     * Get a relationship join table hash.
     */
    public function getRelationCountHash(bool $incrementJoinCount = true): string
    {
        return 'laravel_reserved_'.($incrementJoinCount ? static::$selfJoinCount++ : static::$selfJoinCount);
    }

    /**
     * Make a new related instance for the given model.
     */
    protected function newRelatedInstanceFor(Model $parent): Model
    {
        return $this->related->newInstance();
    }

    /**
     * Build model dictionary keyed by the relation's foreign key.
     */
    protected function buildDictionary(Collection $results): array
    {
        $foreign = $this->getForeignKeyName();

        return $results->mapToDictionary(function ($result) use ($foreign) {
            return [$this->getDictionaryKey($result->getAttribute($foreign)) => $result];
        })->all();
    }

    /**
     * Get the dictionary key attribute.
     */
    protected function getDictionaryKey($attribute): string
    {
        return $attribute;
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

    /**
     * Return a new model instance in case the relationship does not exist.
     */
    public function withDefault($callback = true): static
    {
        $this->withDefault = $callback;

        return $this;
    }

    /**
     * Get the name of the "where in" method for eager loading.
     */
    protected function whereInMethod(Model $model, string $key): string
    {
        return $model->getKeyName() === last(explode('.', $key))
                    && in_array($model->getKeyType(), ['int', 'integer'])
                        ? 'whereIntegerInRaw'
                        : 'whereIn';
    }

    /**
     * Get the relationship name of the relationship.
     */
    protected function getRelationName(): string
    {
        return debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4)[3]['function'];
    }
}