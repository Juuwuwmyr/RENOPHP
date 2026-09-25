<?php

declare(strict_types=1);

namespace Horizon\Database\Eloquent\Relations;

use Horizon\Database\Eloquent\Builder;
use Horizon\Database\Eloquent\Model;
use Horizon\Support\Collection;

/**
 * Has One Relationship
 * 
 * Represents a one-to-one relationship where the foreign key
 * is on the related model.
 */
class HasOne extends HasOneOrMany
{
    /**
     * Get the results of the relationship.
     */
    public function getResults()
    {
        if (is_null($this->getParentKey())) {
            return $this->getDefaultFor($this->parent);
        }

        return $this->query->first() ?: $this->getDefaultFor($this->parent);
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
        return $this->matchOne($models, $results, $relation);
    }

    /**
     * Match the eagerly loaded results to their single parents.
     */
    protected function matchOne(array $models, Collection $results, string $relation): array
    {
        $dictionary = $this->buildDictionary($results);

        // Once we have the dictionary we can simply spin through the parent models to
        // link them up with their children using the keyed dictionary to make the
        // matching very convenient and easy work. Then we'll just return them.
        foreach ($models as $model) {
            if (isset($dictionary[$key = $this->getDictionaryKey($model->getAttribute($this->localKey))])) {
                $model->setRelation(
                    $relation, $this->getRelationValue($dictionary, $key, 'one')
                );
            }
        }

        return $models;
    }

    /**
     * Get the value of a relationship by one or many type.
     */
    protected function getRelationValue(array $dictionary, string $key, string $type)
    {
        $value = $dictionary[$key];

        return $type === 'one' ? reset($value) : $this->related->newCollection($value);
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
     * Make a new related instance for the given model.
     */
    protected function newRelatedInstanceFor(Model $parent): Model
    {
        return $this->related->newInstance()->setAttribute(
            $this->getForeignKeyName(), $this->getParentKey()
        );
    }
}