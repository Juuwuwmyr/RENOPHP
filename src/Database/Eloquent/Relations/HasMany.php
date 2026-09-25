<?php

declare(strict_types=1);

namespace Horizon\Database\Eloquent\Relations;

use Horizon\Database\Eloquent\Collection;
use Horizon\Database\Eloquent\Model;

/**
 * Has Many Relationship
 * 
 * Represents a one-to-many relationship where the foreign key
 * is on the related model.
 */
class HasMany extends HasOneOrMany
{
    /**
     * Get the results of the relationship.
     */
    public function getResults()
    {
        return !is_null($this->getParentKey())
                ? $this->query->get()
                : $this->related->newCollection();
    }

    /**
     * Initialize the relation on a set of models.
     */
    public function initRelation(array $models, string $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->related->newCollection());
        }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents.
     */
    public function match(array $models, Collection $results, string $relation): array
    {
        return $this->matchMany($models, $results, $relation);
    }

    /**
     * Match the eagerly loaded results to their many parents.
     */
    protected function matchMany(array $models, Collection $results, string $relation): array
    {
        $dictionary = $this->buildDictionary($results);

        // Once we have the dictionary we can simply spin through the parent models to
        // link them up with their children using the keyed dictionary to make the
        // matching very convenient and easy work. Then we'll just return them.
        foreach ($models as $model) {
            if (isset($dictionary[$key = $this->getDictionaryKey($model->getAttribute($this->localKey))])) {
                $model->setRelation(
                    $relation, $this->related->newCollection($dictionary[$key])
                );
            }
        }

        return $models;
    }

    /**
     * Get the relationship for eager loading.
     */
    public function getEager(): Collection
    {
        return $this->get();
    }
}