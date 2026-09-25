<?php

declare(strict_types=1);

namespace Horizon\Database\Eloquent\Relations;

use InvalidArgumentException;
use Horizon\Database\Eloquent\Builder;
use Horizon\Database\Eloquent\Collection;
use Horizon\Database\Eloquent\Model;
use Horizon\Support\Collection as BaseCollection;

/**
 * Belongs To Many Relationship
 * 
 * Represents a many-to-many relationship using a pivot table.
 */
class BelongsToMany extends Relation
{
    /**
     * The intermediate table for the relation.
     */
    protected string $table;

    /**
     * The foreign key of the parent model.
     */
    protected string $foreignPivotKey;

    /**
     * The associated key of the relation.
     */
    protected string $relatedPivotKey;

    /**
     * The key name of the parent model.
     */
    protected string $parentKey;

    /**
     * The key name of the related model.
     */
    protected string $relatedKey;

    /**
     * The "name" of the relationship.
     */
    protected ?string $relationName = null;

    /**
     * The pivot table columns to retrieve.
     */
    protected array $pivotColumns = [];

    /**
     * Any pivot table restrictions for where clauses.
     */
    protected array $pivotWheres = [];

    /**
     * Any pivot table restrictions for whereIn clauses.
     */
    protected array $pivotWhereIns = [];

    /**
     * Any pivot table restrictions for whereNull clauses.
     */
    protected array $pivotWhereNulls = [];

    /**
     * The default values for the pivot columns.
     */
    protected array $pivotValues = [];

    /**
     * Indicates if timestamps are available on the pivot table.
     */
    protected bool $withTimestamps = false;

    /**
     * The custom pivot table column for the created_at timestamp.
     */
    protected ?string $pivotCreatedAt = null;

    /**
     * The custom pivot table column for the updated_at timestamp.
     */
    protected ?string $pivotUpdatedAt = null;

    /**
     * The class name of the custom pivot model to use for the relationship.
     */
    protected ?string $using = null;

    /**
     * The name of the accessor to use for the "pivot" relationship.
     */
    protected string $accessor = 'pivot';

    /**
     * Create a new belongs to many relationship instance.
     */
    public function __construct(
        Builder $query, 
        Model $parent, 
        string $table, 
        string $foreignPivotKey,
        string $relatedPivotKey, 
        string $parentKey, 
        string $relatedKey, 
        ?string $relationName = null
    ) {
        $this->table = $table;
        $this->parentKey = $parentKey;
        $this->relatedKey = $relatedKey;
        $this->relationName = $relationName;
        $this->foreignPivotKey = $foreignPivotKey;
        $this->relatedPivotKey = $relatedPivotKey;

        parent::__construct($query, $parent);
    }

    /**
     * Set the base constraints on the relation query.
     */
    public function addConstraints(): void
    {
        $this->performJoin();

        if (static::$constraints) {
            $this->addWhereConstraints();
        }
    }

    /**
     * Set the join clause for the relation query.
     */
    protected function performJoin(?Builder $query = null): void
    {
        $query = $query ?: $this->query;

        // We need to join to the intermediate table on the related model's primary
        // key column with the intermediate table's foreign key for the related
        // model instance. Then we can set the "where" for the parent models.
        $baseTable = $this->related->getTable();

        $key = $baseTable.'.'.$this->relatedKey;

        $query->join($this->table, $key, '=', $this->getQualifiedRelatedPivotKeyName());
    }

    /**
     * Set the where clause for the relation query.
     */
    protected function addWhereConstraints(): void
    {
        $this->query->where(
            $this->getQualifiedForeignPivotKeyName(), '=', $this->parent->{$this->parentKey}
        );

        return $this;
    }

    /**
     * Set the constraints for an eager load of the relation.
     */
    public function addEagerConstraints(array $models): void
    {
        $whereIn = $this->whereInMethod($this->parent, $this->parentKey);

        $this->query->{$whereIn}(
            $this->getQualifiedForeignPivotKeyName(),
            $this->getKeys($models, $this->parentKey)
        );
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
        $dictionary = $this->buildDictionary($results);

        // Once we have an array dictionary of child objects we can easily match the
        // children back to their parent using the dictionary and the keys on the
        // the parent models. Then we will return the hydrated models back out.
        foreach ($models as $model) {
            $key = $this->getDictionaryKey($model->{$this->parentKey});

            if (isset($dictionary[$key])) {
                $model->setRelation(
                    $relation, $this->related->newCollection($dictionary[$key])
                );
            }
        }

        return $models;
    }

    /**
     * Build model dictionary keyed by the relation's foreign key.
     */
    protected function buildDictionary(Collection $results): array
    {
        // First we will build a dictionary of child models keyed by the foreign key
        // of the relation so that we will easily and quickly match them to their
        // parents without having a possibly slow inner loops for every models.
        $dictionary = [];

        foreach ($results as $result) {
            $key = $this->getDictionaryKey($result->{$this->accessor}->{$this->foreignPivotKey});

            $dictionary[$key][] = $result;
        }

        return $dictionary;
    }

    /**
     * Get the dictionary key attribute.
     */
    protected function getDictionaryKey($attribute): string
    {
        return $attribute;
    }

    /**
     * Get the results of the relationship.
     */
    public function getResults()
    {
        return !is_null($this->parent->{$this->parentKey})
                ? $this->get()
                : $this->related->newCollection();
    }

    /**
     * Execute the query as a "select" statement.
     */
    public function get(array $columns = ['*']): Collection
    {
        // First we'll add the proper select columns onto the query so it is run with
        // the proper columns. Then, we will get the results and hydrate out pivot
        // models with the result of those columns as a separate model relation.
        $builder = $this->query->applyScopes();

        $columns = $builder->getQuery()->columns ? [] : $columns;

        $models = $builder->addSelect(
            $this->shouldSelect($columns)
        )->getModels();

        $this->hydratePivotRelation($models);

        // If we actually found models we will also eager load any relationships that
        // have been specified as needing to be eager loaded. This will solve the
        // n + 1 query problem for the developer and also increase performance.
        if (count($models) > 0) {
            $models = $builder->eagerLoadRelations($models);
        }

        return $this->related->newCollection($models);
    }

    /**
     * Get the select columns for the relation query.
     */
    protected function shouldSelect(array $columns = ['*']): array
    {
        if ($columns == ['*']) {
            $columns = [$this->related->getTable().'.*'];
        }

        return array_merge($columns, $this->aliasedPivotColumns());
    }

    /**
     * Get the pivot columns for the relation.
     */
    protected function aliasedPivotColumns(): array
    {
        $defaults = [$this->foreignPivotKey, $this->relatedPivotKey];

        return collect(array_merge($defaults, $this->pivotColumns))->map(function ($column) {
            return $this->qualifyPivotColumn($column).' as pivot_'.$column;
        })->unique()->all();
    }

    /**
     * Hydrate the pivot table relationship on the models.
     */
    protected function hydratePivotRelation(array $models): void
    {
        // To hydrate the pivot relationship, we will just gather the pivot attributes
        // and create a new Pivot model, which is basically a dynamic model that
        // will hold the attributes for the pivot table. After creating these
        // pivot models, we will return this model's collection.
        foreach ($models as $model) {
            $model->setRelation($this->accessor, $this->newExistingPivot(
                $this->migratePivotAttributes($model)
            ));
        }
    }

    /**
     * Get the pivot attributes from a model.
     */
    protected function migratePivotAttributes(Model $model): array
    {
        $values = [];

        foreach ($model->getAttributes() as $key => $value) {
            // To get the pivots attributes we will just take any of the attributes which
            // begin with "pivot_" and add those to this arrays, as well as unsetting
            // them from the parent's models since they exist in a different table.
            if (str_starts_with($key, 'pivot_')) {
                $values[substr($key, 6)] = $value;

                unset($model->$key);
            }
        }

        return $values;
    }

    /**
     * Create a new pivot model instance.
     */
    public function newPivot(array $attributes = [], bool $exists = false): Pivot
    {
        $pivot = $this->related->newPivot(
            $this->parent, $attributes, $this->table, $exists, $this->using
        );

        return $pivot->setPivotKeys($this->foreignPivotKey, $this->relatedPivotKey);
    }

    /**
     * Create a new existing pivot model instance.
     */
    public function newExistingPivot(array $attributes = []): Pivot
    {
        return $this->newPivot($attributes, true);
    }

    /**
     * Qualify the given column name by the pivot table.
     */
    public function qualifyPivotColumn(string $column): string
    {
        return str_contains($column, '.') ? $column : $this->table.'.'.$column;
    }

    /**
     * Get the foreign key for the relation.
     */
    public function getQualifiedForeignPivotKeyName(): string
    {
        return $this->qualifyPivotColumn($this->foreignPivotKey);
    }

    /**
     * Get the "related key" for the relation.
     */
    public function getQualifiedRelatedPivotKeyName(): string
    {
        return $this->qualifyPivotColumn($this->relatedPivotKey);
    }

    /**
     * Get the intermediate table for the relationship.
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Get the relationship name of the relationship.
     */
    public function getRelationName(): string
    {
        return $this->relationName;
    }

    /**
     * Get the name of the pivot accessor for this relationship.
     */
    public function getPivotAccessor(): string
    {
        return $this->accessor;
    }

    /**
     * Specify that the pivot table has creation and update timestamps.
     */
    public function withTimestamps($createdAt = null, $updatedAt = null): static
    {
        $this->withTimestamps = true;

        $this->pivotCreatedAt = $createdAt;

        $this->pivotUpdatedAt = $updatedAt;

        return $this->withPivot($this->createdAt(), $this->updatedAt());
    }

    /**
     * Specify that the pivot table has creation timestamps.
     */
    public function withPivot(...$columns): static
    {
        $this->pivotColumns = array_merge(
            $this->pivotColumns, is_array($columns[0]) ? $columns[0] : $columns
        );

        return $this;
    }

    /**
     * Attach a model to the parent.
     */
    public function attach($id, array $attributes = [], bool $touch = true): void
    {
        $this->newPivotQuery()->insert($this->formatAttachRecords(
            $this->parseIds($id), $attributes
        ));

        if ($touch) {
            $this->touchIfTouching();
        }
    }

    /**
     * Detach models from the relationship.
     */
    public function detach($ids = null, bool $touch = true): int
    {
        $query = $this->newPivotQuery();

        // If associated IDs were passed to the method we will only delete those
        // associations, otherwise all of the association ties will be broken.
        // We'll return the numbers of affected rows when we do the deletes.
        if (!is_null($ids)) {
            $ids = $this->parseIds($ids);

            if (empty($ids)) {
                return 0;
            }

            $query->whereIn($this->relatedPivotKey, (array) $ids);
        }

        // Once we have all of the conditions set on the statement, we are ready
        // to run the delete on the pivot table. Then, if the touch parameter
        // is true, we will go ahead and touch all related models to sync.
        $results = $query->delete();

        if ($touch) {
            $this->touchIfTouching();
        }

        return $results;
    }

    /**
     * Sync the intermediate tables with a list of IDs or collection of models.
     */
    public function sync($ids, bool $detaching = true): array
    {
        $changes = [
            'attached' => [], 'detached' => [], 'updated' => [],
        ];

        // First we need to attach any of the associated models that are not currently
        // in this joining table. We'll spin through the given IDs, checking to see
        // if they exist in the array of current ones, and if not we will insert.
        $current = $this->getCurrentlyAttachedPivots()
                        ->pluck($this->relatedPivotKey)->all();

        $detach = array_diff($current, array_keys(
            $records = $this->formatRecordsList($this->parseIds($ids))
        ));

        // Next, we will take the differences of the currents and given IDs and detach
        // all of the entities that exist in the "current" array but are not in the
        // array of the new IDs given to the method which will complete the sync.
        if ($detaching && count($detach) > 0) {
            $this->detach($detach);

            $changes['detached'] = $this->castKeys($detach);
        }

        // Now we are finally ready to attach the new records. Note that we'll disable
        // touching until after the entire operation is complete so we don't fire a
        // ton of touch operations until we are totally done syncing the records.
        $changes = array_merge(
            $changes, $this->attachNew($records, $current, false)
        );

        // Once we have finished attaching or detaching the records, we will see if we
        // have done any attaching or detaching, and if we have we will touch these
        // models. After touching, we should return the array of changes made here.
        if (count($changes['attached']) ||
            count($changes['updated'])) {
            $this->touchIfTouching();
        }

        return $changes;
    }

    /**
     * Sync the intermediate tables with a list of IDs without detaching.
     */
    public function syncWithoutDetaching($ids): array
    {
        return $this->sync($ids, false);
    }

    /**
     * Toggle the IDs in the intermediate table.
     */
    public function toggle($ids, bool $touch = true): array
    {
        $changes = [
            'attached' => [], 'detached' => [],
        ];

        $records = $this->formatRecordsList($this->parseIds($ids));

        // Next, we will figure out which IDs are already present in the junction
        // table and which ones are not. We will need to remove all of the IDs
        // that are present, and add all of the ones that are not present.
        $detach = array_values(array_intersect(
            $this->getCurrentlyAttachedPivots()->pluck($this->relatedPivotKey)->all(),
            array_keys($records)
        ));

        if (count($detach) > 0) {
            $this->detach($detach, false);

            $changes['detached'] = $this->castKeys($detach);
        }

        // Finally, for all of the records which were not "detached", we'll attach the
        // records into the intermediate table. Then, we will add those attaches to
        // this change list and get ready to return these results to the callers.
        $attach = array_diff_key($records, array_flip($detach));

        if (count($attach) > 0) {
            $this->attach(array_keys($attach), array_values($attach), false);

            $changes['attached'] = array_keys($attach);
        }

        // Once we have finished attaching or detaching the records, we will see if we
        // have done any attaching or detaching, and if we have we will touch these
        // models. After touching, we should return the array of changes made here.
        if ($touch && (count($changes['attached']) ||
                       count($changes['detached']))) {
            $this->touchIfTouching();
        }

        return $changes;
    }

    /**
     * Get a new plain query builder for the pivot table.
     */
    public function newPivotQuery(): Builder
    {
        $query = $this->query->getQuery()->newQuery()->from($this->table);

        return $query->where($this->foreignPivotKey, $this->parent->{$this->parentKey});
    }

    /**
     * Get the currently attached pivots.
     */
    protected function getCurrentlyAttachedPivots(): BaseCollection
    {
        return $this->newPivotQuery()->get();
    }

    /**
     * Format the sync / toggle record list.
     */
    protected function formatRecordsList(array $records): array
    {
        return collect($records)->mapWithKeys(function ($attributes, $id) {
            if (!is_array($attributes)) {
                [$id, $attributes] = [$attributes, []];
            }

            return [$id => $attributes];
        })->all();
    }

    /**
     * Convert the given IDs to the correct format.
     */
    protected function parseIds($value): array
    {
        if ($value instanceof Model) {
            return [$value->{$this->relatedKey}];
        }

        if ($value instanceof Collection) {
            return $value->pluck($this->relatedKey)->all();
        }

        if ($value instanceof BaseCollection) {
            return $value->toArray();
        }

        return (array) $value;
    }

    /**
     * Cast the given keys to integers if they are numeric and string otherwise.
     */
    protected function castKeys(array $keys): array
    {
        return (array) array_map(function ($v) {
            return $this->castKey($v);
        }, $keys);
    }

    /**
     * Cast the given key to an integer if it is numeric.
     */
    protected function castKey($key)
    {
        return is_numeric($key) ? (int) $key : (string) $key;
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