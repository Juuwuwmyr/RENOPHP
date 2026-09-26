<?php

declare(strict_types=1);

namespace Reno\Database\Eloquent;

use Reno\Support\Collection as BaseCollection;

/**
 * Eloquent Model Collection
 * 
 * A collection of Eloquent models with additional functionality.
 */
class Collection extends BaseCollection
{
    /**
     * Find a model in the collection by key.
     */
    public function find($key, $default = null): ?Model
    {
        if ($key instanceof Model) {
            $key = $key->getKey();
        }

        if ($key instanceof Arrayable) {
            $key = $key->toArray();
        }

        if (is_array($key)) {
            if ($this->isEmpty()) {
                return new static;
            }

            return $this->whereIn($this->first()->getKeyName(), $key);
        }

        return $this->first(function ($model) use ($key) {
            return $model->getKey() == $key;
        }, $default);
    }

    /**
     * Load a set of relationships onto the collection.
     */
    public function load($relations): static
    {
        if ($this->isNotEmpty()) {
            if (is_string($relations)) {
                $relations = func_get_args();
            }

            $query = $this->first()->newQueryWithoutRelationships()->with($relations);

            $this->items = $query->eagerLoadRelations($this->items);
        }

        return $this;
    }

    /**
     * Load a set of relationship counts onto the collection.
     */
    public function loadCount($relations): static
    {
        if ($this->isEmpty()) {
            return $this;
        }

        $models = $this->first()->newModelQuery()
            ->whereKey($this->modelKeys())
            ->select($this->first()->getKeyName())
            ->withCount(...func_get_args())
            ->get()
            ->keyBy($this->first()->getKeyName());

        $attributes = array_except(
            array_keys($models->first()->getAttributes()), $this->first()->getKeyName()
        );

        $this->each(function ($model) use ($models, $attributes) {
            $extraAttributes = array_intersect_key(
                $models->get($model->getKey())->getAttributes(), array_flip($attributes)
            );

            $model->forceFill($extraAttributes)->syncOriginalAttributes($attributes);
        });

        return $this;
    }

    /**
     * Load a set of relationships onto the mixed relationship collection.
     */
    public function loadMorph(string $relation, array $relations): static
    {
        $this->groupBy(function ($model) {
            return get_class($model);
        })->each(function ($models, $className) use ($relation, $relations) {
            static::make($models)->load($relations[$className] ?? []);
        });

        return $this;
    }

    /**
     * Add an item to the collection.
     */
    public function add($item): static
    {
        $this->items[] = $item;

        return $this;
    }

    /**
     * Determine if a key exists in the collection.
     */
    public function contains($key, $operator = null, $value = null): bool
    {
        if (func_num_args() === 1 && $this->useAsCallable($key)) {
            $placeholder = new stdClass;

            return $this->first($key, $placeholder) !== $placeholder;
        }

        if (func_num_args() === 1) {
            if ($key instanceof Model) {
                return $this->contains(function ($model) use ($key) {
                    return $model->is($key);
                });
            }

            return in_array($key, $this->items, true);
        }

        return $this->contains($this->operatorForWhere(...func_get_args()));
    }

    /**
     * Get the array of primary keys.
     */
    public function modelKeys(): array
    {
        return array_map(function ($model) {
            return $model->getKey();
        }, $this->items);
    }

    /**
     * Merge the collection with the given items.
     */
    public function merge($items): static
    {
        $dictionary = $this->getDictionary();

        foreach ($items as $item) {
            $dictionary[$item->getKey()] = $item;
        }

        return new static(array_values($dictionary));
    }

    /**
     * Run a map over each of the items.
     */
    public function map(callable $callback): BaseCollection
    {
        $result = parent::map($callback);

        return $result->contains(function ($item) {
            return !$item instanceof Model;
        }) ? $result->toBase() : $result;
    }

    /**
     * Run an associative map over each of the items.
     */
    public function mapWithKeys(callable $callback): BaseCollection
    {
        $result = parent::mapWithKeys($callback);

        return $result->contains(function ($item) {
            return !$item instanceof Model;
        }) ? $result->toBase() : $result;
    }

    /**
     * Reload a fresh model instance from the database for all the entities.
     */
    public function fresh($with = []): static
    {
        if ($this->isEmpty()) {
            return new static;
        }

        $model = $this->first();

        $freshModels = $model->newQueryWithoutScopes()
            ->with(is_string($with) ? func_get_args() : $with)
            ->whereIn($model->getKeyName(), $this->modelKeys())
            ->get()
            ->getDictionary();

        return $this->filter(function ($model) use ($freshModels) {
            return $model->exists && isset($freshModels[$model->getKey()]);
        })->map(function ($model) use ($freshModels) {
            return $freshModels[$model->getKey()];
        });
    }

    /**
     * Diff the collection with the given items.
     */
    public function diff($items): static
    {
        $diff = new static;

        $dictionary = $this->getDictionary($items);

        foreach ($this->items as $item) {
            if (!isset($dictionary[$item->getKey()])) {
                $diff->add($item);
            }
        }

        return $diff;
    }

    /**
     * Intersect the collection with the given items.
     */
    public function intersect($items): static
    {
        $intersect = new static;

        if (empty($items)) {
            return $intersect;
        }

        $dictionary = $this->getDictionary($items);

        foreach ($this->items as $item) {
            if (isset($dictionary[$item->getKey()])) {
                $intersect->add($item);
            }
        }

        return $intersect;
    }

    /**
     * Return only unique items from the collection.
     */
    public function unique($key = null, bool $strict = false): static
    {
        if (!is_null($key)) {
            return parent::unique($key, $strict);
        }

        return new static(array_values($this->getDictionary()));
    }

    /**
     * Returns only the models from the collection with the specified keys.
     */
    public function only($keys): static
    {
        if (is_null($keys)) {
            return new static($this->items);
        }

        $dictionary = array_flip($keys);

        return $this->filter(function ($model) use ($dictionary) {
            return isset($dictionary[$model->getKey()]);
        });
    }

    /**
     * Returns all models in the collection except the models with specified keys.
     */
    public function except($keys): static
    {
        $dictionary = array_flip($keys);

        return $this->filter(function ($model) use ($dictionary) {
            return !isset($dictionary[$model->getKey()]);
        });
    }

    /**
     * Make the given, typically hidden, attributes visible across the entire collection.
     */
    public function makeVisible($attributes): static
    {
        return $this->each(function ($model) use ($attributes) {
            $model->makeVisible(is_array($attributes) ? $attributes : func_get_args());
        });
    }

    /**
     * Make the given, typically visible, attributes hidden across the entire collection.
     */
    public function makeHidden($attributes): static
    {
        return $this->each(function ($model) use ($attributes) {
            $model->makeHidden(is_array($attributes) ? $attributes : func_get_args());
        });
    }

    /**
     * Indicate that the given attributes should be appended for serialization.
     */
    public function append($attributes): static
    {
        return $this->each(function ($model) use ($attributes) {
            $model->append(is_array($attributes) ? $attributes : func_get_args());
        });
    }

    /**
     * Get a dictionary keyed by the collection models' keys.
     */
    public function getDictionary($items = null): array
    {
        $items = is_null($items) ? $this->items : $items;

        $dictionary = [];

        foreach ($items as $value) {
            $dictionary[$value->getKey()] = $value;
        }

        return $dictionary;
    }

    /**
     * The following methods are intercepted to always return base collections.
     */

    /**
     * Get an array with the values of a given key.
     */
    public function pluck($value, $key = null): BaseCollection
    {
        return $this->toBase()->pluck($value, $key);
    }

    /**
     * Get the keys of the collection items.
     */
    public function keys(): BaseCollection
    {
        return $this->toBase()->keys();
    }

    /**
     * Zip the collection together with one or more arrays.
     */
    public function zip($items): BaseCollection
    {
        return $this->toBase()->zip(...func_get_args());
    }

    /**
     * Collapse the collection of items into a single array.
     */
    public function collapse(): BaseCollection
    {
        return $this->toBase()->collapse();
    }

    /**
     * Get a flattened array of the items in the collection.
     */
    public function flatten(int $depth = INF): BaseCollection
    {
        return $this->toBase()->flatten($depth);
    }

    /**
     * Flip the items in the collection.
     */
    public function flip(): BaseCollection
    {
        return $this->toBase()->flip();
    }

    /**
     * Pad collection to the specified length with a value.
     */
    public function pad(int $size, $value): BaseCollection
    {
        return $this->toBase()->pad($size, $value);
    }

    /**
     * Get the comparison function to detect duplicates.
     */
    protected function duplicateComparator(bool $strict): callable
    {
        if ($strict) {
            return function ($a, $b) {
                return $a === $b;
            };
        }

        return function ($a, $b) {
            return $a == $b;
        };
    }

    /**
     * Get the type of the entities being queued.
     */
    public function getQueueableClass(): ?string
    {
        if ($this->isEmpty()) {
            return;
        }

        $class = get_class($this->first());

        $this->each(function ($model) use ($class) {
            if (get_class($model) !== $class) {
                throw new LogicException('Queueing collections with multiple model types is not supported.');
            }
        });

        return $class;
    }

    /**
     * Get the identifiers for all of the entities.
     */
    public function getQueueableIds(): array
    {
        if ($this->isEmpty()) {
            return [];
        }

        return $this->first() instanceof QueueableEntity
            ? $this->map->getQueueableId()->all()
            : $this->modelKeys();
    }

    /**
     * Get the relationships for all of the entities.
     */
    public function getQueueableRelations(): array
    {
        if ($this->isEmpty()) {
            return [];
        }

        $queueable = $this->first()->getQueueableRelations();

        if (count($queueable) === 0) {
            return [];
        }

        $this->each(function ($model) use ($queueable) {
            if ($queueable !== $model->getQueueableRelations()) {
                throw new LogicException('Queueing collections with multiple model relation sets is not supported.');
            }
        });

        return $queueable;
    }

    /**
     * Get the connection of the entity.
     */
    public function getQueueableConnection(): ?string
    {
        if ($this->isEmpty()) {
            return;
        }

        $connection = $this->first()->getQueueableConnection();

        $this->each(function ($model) use ($connection) {
            if ($model->getQueueableConnection() !== $connection) {
                throw new LogicException('Queueing collections with multiple model connections is not supported.');
            }
        });

        return $connection;
    }
}