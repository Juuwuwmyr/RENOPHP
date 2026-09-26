<?php

declare(strict_types=1);

namespace Horizon\Database\Eloquent;

use ArrayAccess;
use JsonSerializable;
use Horizon\Contracts\Database\ConnectionInterface;
use Horizon\Database\Query\QueryBuilder;
use Horizon\Database\Eloquent\Relations\BelongsTo;
use Horizon\Database\Eloquent\Relations\BelongsToMany;
use Horizon\Database\Eloquent\Relations\HasMany;
use Horizon\Database\Eloquent\Relations\HasOne;
use Horizon\Database\Eloquent\Relations\Relation;
use Horizon\Database\Eloquent\Concerns\HasSecurity;
use Horizon\Database\Eloquent\Concerns\HasPerformance;
use Horizon\Database\Eloquent\SecurityAuditor;
use Horizon\Support\Collection;
use Horizon\Support\Str;
use InvalidArgumentException;
use LogicException;

/**
 * Eloquent Model
 * 
 * Base class for all Eloquent models providing Active Record functionality.
 * Simple, secure, and performant ORM implementation.
 */
abstract class Model implements ArrayAccess, JsonSerializable
{
    use HasSecurity, HasPerformance;
    /**
     * The connection name for the model.
     */
    protected ?string $connection = null;

    /**
     * The table associated with the model.
     */
    protected ?string $table = null;

    /**
     * The primary key for the model.
     */
    protected string $primaryKey = 'id';

    /**
     * The "type" of the primary key ID.
     */
    protected string $keyType = 'int';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public bool $incrementing = true;

    /**
     * Indicates if the model should be timestamped.
     */
    public bool $timestamps = true;

    /**
     * The name of the "created at" column.
     */
    const CREATED_AT = 'created_at';

    /**
     * The name of the "updated at" column.
     */
    const UPDATED_AT = 'updated_at';

    /**
     * The storage format of the model's date columns.
     */
    protected string $dateFormat = 'Y-m-d H:i:s';

    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = [];

    /**
     * The attributes that aren't mass assignable.
     */
    protected array $guarded = ['*'];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected array $hidden = [];

    /**
     * The attributes that should be visible in serialization.
     */
    protected array $visible = [];

    /**
     * The accessors to append to the model's array form.
     */
    protected array $appends = [];

    /**
     * The attributes that should be cast.
     */
    protected array $casts = [];

    /**
     * The model's attributes.
     */
    protected array $attributes = [];

    /**
     * The model attribute's original state.
     */
    protected array $original = [];

    /**
     * The changed model attributes.
     */
    protected array $changes = [];

    /**
     * Indicates if the model exists.
     */
    public bool $exists = false;

    /**
     * Indicates if the model was inserted during the current request lifecycle.
     */
    public bool $wasRecentlyCreated = false;

    /**
     * The loaded relationships for the model.
     */
    protected array $relations = [];

    /**
     * The class name to be used in polymorphic relations.
     */
    protected ?string $morphClass = null;

    /**
     * Indicates if the model should be soft deleted.
     */
    protected bool $softDelete = false;

    /**
     * The name of the "deleted at" column.
     */
    const DELETED_AT = 'deleted_at';

    /**
     * Boot the soft deleting trait for a model.
     */
    public static function bootSoftDeletes(): void
    {
        static::addGlobalScope(new SoftDeletingScope);
    }

    /**
     * Determine if the model uses soft deletes.
     */
    public function usesSoftDeletes(): bool
    {
        return $this->softDelete;
    }

    /**
     * Get the name of the "deleted at" column.
     */
    public function getDeletedAtColumn(): string
    {
        return static::DELETED_AT;
    }

    /**
     * Get the fully qualified "deleted at" column.
     */
    public function getQualifiedDeletedAtColumn(): string
    {
        return $this->qualifyColumn($this->getDeletedAtColumn());
    }

    /**
     * Force a hard delete on a soft deleted model.
     */
    public function forceDelete(): ?bool
    {
        if (method_exists($this, 'isForceDeleting')) {
            $this->isForceDeleting = true;
        }

        $deleted = $this->delete();

        if (method_exists($this, 'isForceDeleting')) {
            $this->isForceDeleting = false;
        }

        return $deleted;
    }

    /**
     * Perform the actual delete query on this model instance.
     */
    protected function performDeleteOnModel(): void
    {
        if ($this->usesSoftDeletes() && !$this->isForceDeleting()) {
            $this->runSoftDelete();
        } else {
            $this->setKeysForSaveQuery($this->newModelQuery())->delete();
            $this->exists = false;
        }
    }

    /**
     * Perform a soft delete on the model.
     */
    protected function runSoftDelete(): void
    {
        $query = $this->setKeysForSaveQuery($this->newModelQuery());

        $time = $this->freshTimestamp();

        $columns = [$this->getDeletedAtColumn() => $this->fromDateTime($time)];

        $this->{$this->getDeletedAtColumn()} = $time;

        if ($this->timestamps && !is_null($this->getUpdatedAtColumn())) {
            $this->{$this->getUpdatedAtColumn()} = $time;

            $columns[$this->getUpdatedAtColumn()] = $this->fromDateTime($time);
        }

        $query->update($columns);

        $this->syncOriginalAttributes(array_keys($columns));

        $this->fireModelEvent('trashed', false);
    }

    /**
     * Restore a soft-deleted model instance.
     */
    public function restore(): bool
    {
        // If the restoring event does not return false, we will proceed with this
        // restore operation. Otherwise, we bail out so the developer will stop
        // the restore totally. We will clear the deleted timestamp and save.
        if ($this->fireModelEvent('restoring') === false) {
            return false;
        }

        $this->{$this->getDeletedAtColumn()} = null;

        // Once we have saved the model, we will fire the "restored" event so this
        // developer will do anything they need to after a restore operation is
        // totally finished. Then we will return the result of the save call.
        $this->exists = true;

        $result = $this->save();

        $this->fireModelEvent('restored', false);

        return $result;
    }

    /**
     * Determine if the model instance has been soft-deleted.
     */
    public function trashed(): bool
    {
        return !is_null($this->{$this->getDeletedAtColumn()});
    }

    /**
     * Determine if the model is currently force deleting.
     */
    public function isForceDeleting(): bool
    {
        return $this->forceDeleting ?? false;
    }

    /**
     * Get a new query builder that includes soft deleted models.
     */
    public static function withTrashed(): Builder
    {
        return static::query()->withTrashed();
    }

    /**
     * Get a new query builder that only includes soft deleted models.
     */
    public static function onlyTrashed(): Builder
    {
        return static::query()->onlyTrashed();
    }

    /**
     * Register a "restoring" model event callback.
     */
    public static function restoring(Closure $callback): void
    {
        static::registerModelEvent('restoring', $callback);
    }

    /**
     * Register a "restored" model event callback.
     */
    public static function restored(Closure $callback): void
    {
        static::registerModelEvent('restored', $callback);
    }

    /**
     * Register a "trashing" model event callback.
     */
    public static function trashing(Closure $callback): void
    {
        static::registerModelEvent('trashing', $callback);
    }

    /**
     * Add a local scope to this model.
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        foreach ($filters as $key => $value) {
            if (method_exists($this, 'scope'.ucfirst($key))) {
                $query = $this->{'scope'.ucfirst($key)}($query, $value);
            }
        }

        return $query;
    }

    /**
     * Apply all of the global scopes to an Eloquent builder.
     */
    public function registerGlobalScopes(Builder $builder): Builder
    {
        foreach ($this->getGlobalScopes() as $identifier => $scope) {
            $builder->withGlobalScope($identifier, $scope);
        }

        return $builder;
    }

    /**
     * Get the global scopes for this class.
     */
    public function getGlobalScopes(): array
    {
        return static::$globalScopes[static::class] ?? [];
    }

    /**
     * Add a global scope to this model.
     */
    public static function addGlobalScope($scope, ?Closure $implementation = null): void
    {
        if (is_string($scope) && !is_null($implementation)) {
            static::$globalScopes[static::class][$scope] = $implementation;
        } else {
            static::$globalScopes[static::class][get_class($scope)] = $scope;
        }
    }

    /**
     * Remove a global scope from this model.
     */
    public static function removeGlobalScope($scope): void
    {
        if (!is_string($scope)) {
            $scope = get_class($scope);
        }

        unset(static::$globalScopes[static::class][$scope]);
    }

    /**
     * Remove all global scopes from this model.
     */
    public static function clearGlobalScopes(): void
    {
        static::$globalScopes[static::class] = [];
    }

    /**
     * Register an observer with the Model.
     */
    public static function observe($class): void
    {
        $instance = new $class;

        $className = static::class;

        foreach (static::getObservableEvents() as $event) {
            if (method_exists($instance, $event)) {
                static::registerModelEvent($event, $className.'@'.$event);
            }
        }
    }

    /**
     * Get the observable event names.
     */
    public static function getObservableEvents(): array
    {
        return array_merge(
            [
                'retrieved', 'creating', 'created', 'updating', 'updated',
                'saving', 'saved', 'restoring', 'restored', 'replicating',
                'deleting', 'deleted', 'trashing', 'trashed', 'forceDeleted',
            ],
            static::$observables
        );
    }

    /**
     * Register a model event with the dispatcher.
     */
    protected static function registerModelEvent(string $event, $callback): void
    {
        if (isset(static::$dispatcher)) {
            $name = static::class;

            static::$dispatcher->listen("eloquent.{$event}: {$name}", $callback);
        }
    }

    /**
     * Get the model events that are subject to observations.
     */
    public function getObservableEvents(): array
    {
        return array_merge(
            [
                'retrieved', 'creating', 'created', 'updating', 'updated',
                'saving', 'saved', 'restoring', 'restored', 'replicating',
                'deleting', 'deleted', 'trashing', 'trashed', 'forceDeleted',
            ],
            $this->observables
        );
    }

    /**
     * The cached mutated attributes for each class.
     */
    protected static array $mutatorCache = [];

    /**
     * The cached accessor attributes for each class.
     */
    protected static array $accessorCache = [];

    /**
     * The attributes that should be mutated to dates.
     */
    protected array $dates = [];

    /**
     * Indicates whether attributes are snake cased on arrays.
     */
    public static bool $snakeAttributes = true;

    /**
     * Create a new Eloquent model instance.
     */
    public function __construct(array $attributes = [])
    {
        $this->syncOriginal();

        $this->fill($attributes);
    }

    /**
     * Fill the model with an array of attributes.
     */
    public function fill(array $attributes): static
    {
        $totallyGuarded = $this->totallyGuarded();

        foreach ($this->fillableFromArray($attributes) as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            } elseif ($totallyGuarded) {
                throw new InvalidArgumentException("Add [{$key}] to fillable property to allow mass assignment on [" . static::class . "].");
            }
        }

        return $this;
    }

    /**
     * Get the fillable attributes of a given array.
     */
    protected function fillableFromArray(array $attributes): array
    {
        if (count($this->getFillable()) > 0 && !static::$unguarded) {
            return array_intersect_key($attributes, array_flip($this->getFillable()));
        }

        return $attributes;
    }

    /**
     * Create a new instance of the given model.
     */
    public function newInstance(array $attributes = [], bool $exists = false): static
    {
        $model = new static($attributes);

        $model->exists = $exists;

        $model->setConnection($this->getConnectionName());

        $model->setTable($this->getTable());

        return $model;
    }

    /**
     * Create a new model instance that is existing.
     */
    public function newFromBuilder(array $attributes = [], ?string $connection = null): static
    {
        $model = $this->newInstance([], true);

        $model->setRawAttributes($attributes, true);

        $model->setConnection($connection ?: $this->getConnectionName());

        $model->fireModelEvent('retrieved', false);

        return $model;
    }

    /**
     * Begin querying the model.
     */
    public static function query(): QueryBuilder
    {
        return (new static)->newQuery();
    }

    /**
     * Get a new query builder for the model's table.
     */
    public function newQuery(): QueryBuilder
    {
        return $this->registerGlobalScopes($this->newQueryWithoutScopes());
    }

    /**
     * Get a new query builder that doesn't have any global scopes or eager loading.
     */
    public function newQueryWithoutScopes(): QueryBuilder
    {
        return $this->newModelQuery()->setModel($this);
    }

    /**
     * Get a new query builder instance for the connection.
     */
    protected function newModelQuery(): QueryBuilder
    {
        return $this->getConnection()->table($this->getTable());
    }

    /**
     * Create a new Eloquent query builder for the model.
     */
    public function newEloquentBuilder(QueryBuilder $query): Builder
    {
        return new Builder($query);
    }

    /**
     * Register the global scopes for this builder instance.
     */
    public function registerGlobalScopes(QueryBuilder $builder): QueryBuilder
    {
        // Global scopes would be registered here
        return $builder;
    }

    /**
     * Find a model by its primary key.
     */
    public static function find($id, array $columns = ['*']): ?static
    {
        if (is_array($id) || $id instanceof Collection) {
            return static::findMany($id, $columns);
        }

        return static::query()->where((new static)->getKeyName(), '=', $id)->first($columns);
    }

    /**
     * Find multiple models by their primary keys.
     */
    public static function findMany($ids, array $columns = ['*']): Collection
    {
        $ids = $ids instanceof Collection ? $ids->modelKeys() : $ids;

        if (empty($ids)) {
            return new Collection;
        }

        return static::query()->whereIn((new static)->getKeyName(), $ids)->get($columns);
    }

    /**
     * Find a model by its primary key or throw an exception.
     */
    public static function findOrFail($id, array $columns = ['*']): static
    {
        $result = static::find($id, $columns);

        $id = $id instanceof Collection ? $id->modelKeys() : $id;

        if (is_array($id)) {
            if (count($result) !== count(array_unique($id))) {
                throw new ModelNotFoundException;
            }

            return $result;
        }

        if (is_null($result)) {
            throw new ModelNotFoundException;
        }

        return $result;
    }

    /**
     * Execute the query and get the first result.
     */
    public static function first(array $columns = ['*']): ?static
    {
        return static::query()->first($columns);
    }

    /**
     * Execute the query and get the first result or throw an exception.
     */
    public static function firstOrFail(array $columns = ['*']): static
    {
        if (!is_null($model = static::first($columns))) {
            return $model;
        }

        throw new ModelNotFoundException;
    }

    /**
     * Get all of the models from the database.
     */
    public static function all(array $columns = ['*']): Collection
    {
        return static::query()->get($columns);
    }

    /**
     * Create a new model and save it to the database.
     */
    public static function create(array $attributes = []): static
    {
        $model = new static($attributes);

        $model->save();

        return $model;
    }

    /**
     * Save the model to the database.
     */
    public function save(array $options = []): bool
    {
        $this->mergeAttributesFromClassCasts();

        $query = $this->newModelQuery();

        // If the "saving" event returns false we'll bail out of the save and return
        // false, indicating that the save failed. This provides a chance for any
        // listeners to cancel save operations if validations fail or whatever.
        if ($this->fireModelEvent('saving') === false) {
            return false;
        }

        // If the model already exists in the database we can just update our record
        // that is already in this database using the current IDs in this "where"
        // clause to only update this model. Otherwise, we'll just insert them.
        if ($this->exists) {
            $saved = $this->isDirty() ? $this->performUpdate($query) : true;
        }

        // If the model is brand new, we'll insert it into our database and set the
        // ID attribute on the model to the value of the newly inserted row's ID
        // which is typically an auto-increment value managed by the database.
        else {
            $saved = $this->performInsert($query);

            if (!$this->getConnectionName() && $connection = $query->getConnection()) {
                $this->setConnection($connection->getName());
            }
        }

        // If the model is successfully saved, we need to do a few more things once
        // that is done. We will call the "saved" method so developers can hook
        // into post-save operations. We will also set the recent flag on this.
        if ($saved) {
            $this->finishSave($options);
        }

        return $saved;
    }

    /**
     * Save the model to the database using transaction.
     */
    public function saveOrFail(array $options = []): bool
    {
        return $this->getConnection()->transaction(function () use ($options) {
            return $this->save($options);
        });
    }

    /**
     * Perform a model update operation.
     */
    protected function performUpdate(QueryBuilder $query): bool
    {
        // If the updating event returns false, we will cancel the update operation so
        // developers can hook Validation systems into their models and cancel this
        // operation if the model does not pass validation. Otherwise, we update.
        if ($this->fireModelEvent('updating') === false) {
            return false;
        }

        // First we need to create a fresh query instance and touch the creation and
        // update timestamp on the model which are maintained by us for developer
        // convenience. Then we will just continue saving the model instances.
        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }

        // Once we have run the update operation, we will fire the "updated" event for
        // this model instance. This will allow developers to hook into these after
        // models are updated, giving them a chance to do any special processing.
        $dirty = $this->getDirty();

        if (count($dirty) > 0) {
            $this->setKeysForSaveQuery($query)->update($dirty);

            $this->syncChanges();

            $this->fireModelEvent('updated', false);
        }

        return true;
    }

    /**
     * Perform a model insert operation.
     */
    protected function performInsert(QueryBuilder $query): bool
    {
        if ($this->fireModelEvent('creating') === false) {
            return false;
        }

        // First we'll need to create a fresh query instance and touch the creation and
        // update timestamps on this model, which are maintained by us for developer
        // convenience. After, we will just continue saving these model instances.
        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }

        // If the model has an incrementing key, we can use the "insertGetId" method on
        // the query builder, which will give us back the final insert IDs for this
        // table. Not all tables have to be incrementing though so we'll check.
        $attributes = $this->getAttributesForInsert();

        if ($this->getIncrementing()) {
            $this->insertAndSetId($query, $attributes);
        }

        // If the table isn't incrementing we'll simply insert these attributes as they
        // are. These attribute arrays must contain an "id" column previously placed
        // there by the developer as the manually determined key for these models.
        else {
            if (empty($attributes)) {
                return true;
            }

            $query->insert($attributes);
        }

        // We will go ahead and set the exists property to true, so that it is set when
        // the created event is fired, just in case the developer tries to update it
        // during the event. This will allow them to do so and run an update here.
        $this->exists = true;

        $this->wasRecentlyCreated = true;

        $this->fireModelEvent('created', false);

        return true;
    }

    /**
     * Insert the given attributes and set the ID on the model.
     */
    protected function insertAndSetId(QueryBuilder $query, array $attributes): void
    {
        $id = $query->insertGetId($attributes, $keyName = $this->getKeyName());

        $this->setAttribute($keyName, $id);
    }

    /**
     * Finish processing on a successful save operation.
     */
    protected function finishSave(array $options): void
    {
        $this->fireModelEvent('saved', false);

        if ($this->isDirty() && ($options['touch'] ?? true)) {
            $this->touchOwners();
        }

        $this->syncOriginal();
    }

    /**
     * Update the model in the database.
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        if (!$this->exists) {
            return false;
        }

        return $this->fill($attributes)->save($options);
    }

    /**
     * Delete the model from the database.
     */
    public function delete(): ?bool
    {
        if (is_null($this->getKeyName())) {
            throw new LogicException('No primary key defined on model.');
        }

        // If the model doesn't exist, there is nothing to delete so we'll just return
        // immediately and not do anything else. Otherwise, we will continue with a
        // deletion process on the model, firing the proper events, and so forth.
        if (!$this->exists) {
            return null;
        }

        if ($this->fireModelEvent('deleting') === false) {
            return false;
        }

        // Here, we'll touch the owning models, verifying these timestamps get updated
        // for the models. This will allow any caching to get broken on the parents
        // by the timestamp. Then we will go ahead and delete the model instance.
        $this->touchOwners();

        $this->performDeleteOnModel();

        // Once the model has been deleted, we will fire off the deleted event so that
        // the developers may hook into post-delete operations. We will then return
        // a boolean true as the delete is presumably successful on the database.
        $this->fireModelEvent('deleted', false);

        return true;
    }

    /**
     * Perform the actual delete query on this model instance.
     */
    protected function performDeleteOnModel(): void
    {
        $this->setKeysForSaveQuery($this->newModelQuery())->delete();

        $this->exists = false;
    }

    /**
     * Set the keys for a save update query.
     */
    protected function setKeysForSaveQuery(QueryBuilder $query): QueryBuilder
    {
        $query->where($this->getKeyName(), '=', $this->getKeyForSaveQuery());

        return $query;
    }

    /**
     * Get the primary key value for a save query.
     */
    protected function getKeyForSaveQuery()
    {
        return $this->original[$this->getKeyName()] ?? $this->getKey();
    }

    /**
     * Get the value of the model's primary key.
     */
    public function getKey()
    {
        return $this->getAttribute($this->getKeyName());
    }

    /**
     * Get the queueable identity for the entity.
     */
    public function getQueueableId()
    {
        return $this->getKey();
    }

    /**
     * Get the queueable relationships for the entity.
     */
    public function getQueueableRelations(): array
    {
        $relations = [];

        foreach ($this->getRelations() as $key => $relation) {
            if (method_exists($this, $key)) {
                $relations[] = $key;
            }

            if ($relation instanceof QueueableCollection) {
                foreach ($relation->getQueueableIds() as $id) {
                    $relations[] = $key.':'.$id;
                }
            }

            if ($relation instanceof QueueableEntity) {
                $relations[] = $key.':'.$relation->getQueueableId();
            }
        }

        return array_unique($relations);
    }

    /**
     * Get the queueable connection for the entity.
     */
    public function getQueueableConnection(): ?string
    {
        return $this->getConnectionName();
    }

    /**
     * Retrieve the model for a bound value.
     */
    public function resolveRouteBinding($value, ?string $field = null): ?Model
    {
        return $this->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return $this->getKeyName();
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKey()
    {
        return $this->getAttribute($this->getRouteKeyName());
    }

    /**
     * Determine if the model uses timestamps.
     */
    public function usesTimestamps(): bool
    {
        return $this->timestamps;
    }

    /**
     * Get the name of the "created at" column.
     */
    public function getCreatedAtColumn(): string
    {
        return static::CREATED_AT;
    }

    /**
     * Get the name of the "updated at" column.
     */
    public function getUpdatedAtColumn(): string
    {
        return static::UPDATED_AT;
    }

    /**
     * Update the creation and update timestamps.
     */
    protected function updateTimestamps(): void
    {
        $time = $this->freshTimestamp();

        if (!is_null($this->getUpdatedAtColumn()) && !$this->isDirty($this->getUpdatedAtColumn())) {
            $this->setUpdatedAt($time);
        }

        if (!$this->exists && !is_null($this->getCreatedAtColumn()) &&
            !$this->isDirty($this->getCreatedAtColumn())) {
            $this->setCreatedAt($time);
        }
    }

    /**
     * Set the value of the "created at" attribute.
     */
    public function setCreatedAt($value): static
    {
        $this->{$this->getCreatedAtColumn()} = $value;

        return $this;
    }

    /**
     * Set the value of the "updated at" attribute.
     */
    public function setUpdatedAt($value): static
    {
        $this->{$this->getUpdatedAtColumn()} = $value;

        return $this;
    }

    /**
     * Get a fresh timestamp for the model.
     */
    public function freshTimestamp()
    {
        return date($this->getDateFormat());
    }

    /**
     * Get the format for database stored dates.
     */
    public function getDateFormat(): string
    {
        return $this->dateFormat;
    }

    // Continue with additional methods...
}
    /**
     * Get the attributes that should be converted to dates.
     */
    public function getDates(): array
    {
        return $this->usesTimestamps()
            ? [$this->getCreatedAtColumn(), $this->getUpdatedAtColumn()]
            : [];
    }

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return $this->table ?? Str::snake(Str::pluralStudly(class_basename($this)));
    }

    /**
     * Set the table associated with the model.
     */
    public function setTable(string $table): static
    {
        $this->table = $table;

        return $this;
    }

    /**
     * Get the primary key for the model.
     */
    public function getKeyName(): string
    {
        return $this->primaryKey;
    }

    /**
     * Set the primary key for the model.
     */
    public function setKeyName(string $key): static
    {
        $this->primaryKey = $key;

        return $this;
    }

    /**
     * Get the auto-incrementing key type.
     */
    public function getKeyType(): string
    {
        return $this->keyType;
    }

    /**
     * Set the data type for the primary key.
     */
    public function setKeyType(string $type): static
    {
        $this->keyType = $type;

        return $this;
    }

    /**
     * Get the value indicating whether the IDs are incrementing.
     */
    public function getIncrementing(): bool
    {
        return $this->incrementing;
    }

    /**
     * Set whether IDs are incrementing.
     */
    public function setIncrementing(bool $value): static
    {
        $this->incrementing = $value;

        return $this;
    }

    /**
     * Get the database connection for the model.
     */
    public function getConnection(): ConnectionInterface
    {
        return static::resolveConnection($this->getConnectionName());
    }

    /**
     * Get the current connection name for the model.
     */
    public function getConnectionName(): ?string
    {
        return $this->connection;
    }

    /**
     * Set the connection associated with the model.
     */
    public function setConnection(?string $name): static
    {
        $this->connection = $name;

        return $this;
    }

    /**
     * Resolve a connection instance.
     */
    public static function resolveConnection(?string $connection = null): ConnectionInterface
    {
        return static::$resolver->connection($connection);
    }

    /**
     * Get the connection resolver instance.
     */
    public static function getConnectionResolver()
    {
        return static::$resolver;
    }

    /**
     * Set the connection resolver instance.
     */
    public static function setConnectionResolver($resolver): void
    {
        static::$resolver = $resolver;
    }

    /**
     * Unset the connection resolver for models.
     */
    public static function unsetConnectionResolver(): void
    {
        static::$resolver = null;
    }

    /**
     * Get all of the current attributes on the model.
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Set the array of model attributes. No checking is done.
     */
    public function setRawAttributes(array $attributes, bool $sync = false): static
    {
        $this->attributes = $attributes;

        if ($sync) {
            $this->syncOriginal();
        }

        return $this;
    }

    /**
     * Get the model's original attribute values.
     */
    public function getOriginal(?string $key = null, $default = null)
    {
        return data_get($this->original, $key, $default);
    }

    /**
     * Get the model's raw original attribute values.
     */
    public function getRawOriginal(?string $key = null, $default = null)
    {
        return data_get($this->original, $key, $default);
    }

    /**
     * Sync the original attributes with the current.
     */
    public function syncOriginal(): static
    {
        $this->original = $this->getAttributes();

        return $this;
    }

    /**
     * Sync a single original attribute with its current value.
     */
    public function syncOriginalAttribute(string $attribute): static
    {
        return $this->syncOriginalAttributes($attribute);
    }

    /**
     * Sync multiple original attribute with their current values.
     */
    public function syncOriginalAttributes($attributes): static
    {
        $attributes = is_array($attributes) ? $attributes : func_get_args();

        $modelAttributes = $this->getAttributes();

        foreach ($attributes as $attribute) {
            $this->original[$attribute] = $modelAttributes[$attribute] ?? null;
        }

        return $this;
    }

    /**
     * Sync the changed attributes.
     */
    public function syncChanges(): static
    {
        $this->changes = $this->getDirty();

        return $this;
    }

    /**
     * Determine if the model or any of the given attribute(s) have been modified.
     */
    public function isDirty($attributes = null): bool
    {
        return $this->hasChanges(
            $this->getDirty(), is_array($attributes) ? $attributes : func_get_args()
        );
    }

    /**
     * Determine if the model or all the given attribute(s) have remained the same.
     */
    public function isClean($attributes = null): bool
    {
        return !$this->isDirty(...func_get_args());
    }

    /**
     * Determine if the model or any of the given attribute(s) were changed.
     */
    public function wasChanged($attributes = null): bool
    {
        return $this->hasChanges(
            $this->getChanges(), is_array($attributes) ? $attributes : func_get_args()
        );
    }

    /**
     * Determine if any of the given attributes were changed.
     */
    protected function hasChanges(array $changes, array $attributes = null): bool
    {
        // If no specific attributes were provided, we will just see if the dirty array
        // already contains any attributes. If it does we will just return that this
        // count is greater than zero. Else, we need to check specific attributes.
        if (empty($attributes)) {
            return count($changes) > 0;
        }

        // Here we will spin through every attribute and see if this is in the array of
        // dirty attributes. If it is, we will return true and if we make it through
        // all of the attributes for the entire array we will return false at end.
        foreach ($attributes as $attribute) {
            if (array_key_exists($attribute, $changes)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the attributes that have been changed since the last sync.
     */
    public function getDirty(): array
    {
        $dirty = [];

        foreach ($this->getAttributes() as $key => $value) {
            if (!$this->originalIsEquivalent($key)) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    /**
     * Get the attributes that were changed.
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    /**
     * Determine if the new and old values for a given key are equivalent.
     */
    public function originalIsEquivalent(string $key): bool
    {
        if (!array_key_exists($key, $this->original)) {
            return false;
        }

        $attribute = $this->getAttributeValue($key);
        $original = $this->getOriginal($key);

        if ($attribute === $original) {
            return true;
        } elseif (is_null($attribute)) {
            return false;
        } elseif ($this->isDateAttribute($key)) {
            return $this->fromDateTime($attribute) ===
                   $this->fromDateTime($original);
        } elseif ($this->hasCast($key, static::$primitiveCastTypes)) {
            return $this->castAttribute($key, $attribute) ===
                   $this->castAttribute($key, $original);
        }

        return is_numeric($attribute) && is_numeric($original)
               && strcmp((string) $attribute, (string) $original) === 0;
    }

    /**
     * Append attributes to query when building a query.
     */
    protected function appendAttributesToQuery(QueryBuilder $query, array $attributes = ['*']): void
    {
        // Implementation for appending attributes to query
    }

    /**
     * Get an attribute from the model.
     */
    public function getAttribute(string $key)
    {
        if (!$key) {
            return;
        }

        // If the attribute exists in the attribute array or has a "get" mutator we will
        // get the attribute's value. Otherwise, we will proceed as if the developers
        // are asking for a relationship, which we will determine from the methods.
        if (array_key_exists($key, $this->attributes) ||
            array_key_exists($key, $this->casts) ||
            $this->hasGetMutator($key) ||
            $this->isClassCastable($key)) {
            return $this->getAttributeValue($key);
        }

        // Here we will determine if the model base class itself contains this given key
        // since we don't want to treat any of those methods as relationships because
        // they are all intended as helper methods and none of these are relations.
        if (method_exists(self::class, $key)) {
            return;
        }

        return $this->getRelationValue($key);
    }

    /**
     * Get a plain attribute (not a relationship).
     */
    public function getAttributeValue(string $key)
    {
        return $this->transformModelValue($key, $this->getAttributeFromArray($key));
    }

    /**
     * Get an attribute from the $attributes array.
     */
    protected function getAttributeFromArray(string $key)
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * Get a relationship.
     */
    public function getRelationValue(string $key)
    {
        // If the key already exists in the relationships array, it just means the
        // relationship has already been loaded, so we'll just return it out of
        // here because there is no need to query within the relations twice.
        if ($this->relationLoaded($key)) {
            return $this->relations[$key];
        }

        // If the "attribute" exists as a method on the model, we will just assume
        // it is a relationship and will load and return results from the query
        // and hydrate the relationship's result on the "relationships" array.
        if (method_exists($this, $key)) {
            return $this->getRelationshipFromMethod($key);
        }
    }

    /**
     * Get a relationship value from a method.
     */
    protected function getRelationshipFromMethod(string $method)
    {
        $relation = $this->$method();

        if (!$relation instanceof Relation) {
            if (is_null($relation)) {
                throw new LogicException(sprintf(
                    '%s::%s must return a relationship instance, but "null" was returned. Was the "return" keyword used?', static::class, $method
                ));
            }

            throw new LogicException(sprintf(
                '%s::%s must return a relationship instance.', static::class, $method
            ));
        }

        return tap($relation->getResults(), function ($results) use ($method) {
            $this->setRelation($method, $results);
        });
    }

    /**
     * Determine if the given attribute exists.
     */
    public function hasAttribute(string $key): bool
    {
        return array_key_exists($key, $this->attributes);
    }

    /**
     * Set a given attribute on the model.
     */
    public function setAttribute(string $key, $value): static
    {
        // First we will check for the presence of a mutator for the set operation
        // which simply lets the developers tweak the attribute as it is set on
        // this model, such as "json_encoding" a listing of data for storage.
        if ($this->hasSetMutator($key)) {
            return $this->setMutatedAttributeValue($key, $value);
        }

        // If an attribute is listed as a "date", we'll convert it from a DateTime
        // instance into a form proper for storage on the database tables using
        // the connection grammar's date format. We will auto set the values.
        elseif ($value && $this->isDateAttribute($key)) {
            $value = $this->fromDateTime($value);
        }

        if ($this->isClassCastable($key)) {
            $this->setClassCastableAttribute($key, $value);

            return $this;
        }

        if (!is_null($value) && $this->isJsonCastable($key)) {
            $value = $this->castAttributeAsJson($key, $value);
        }

        // If this attribute contains a JSON ->, we'll set the proper value in the
        // attribute's underlying array. This takes care of properly nesting an
        // attribute in the array's value in the case of deeply nested items.
        if (str_contains($key, '->')) {
            return $this->fillJsonAttribute($key, $value);
        }

        $this->attributes[$key] = $value;

        return $this;
    }
    /**
     * Transform a raw model value using mutators, casts, etc.
     */
    protected function transformModelValue(string $key, $value)
    {
        // If the attribute has a get mutator, we will call that then return what
        // it returns as the value, which is useful for transforming values on
        // retrieval from the model to a form that is more useful for usage.
        if ($this->hasGetMutator($key)) {
            return $this->mutateAttribute($key, $value);
        }

        // If the attribute exists within the cast array, we will convert it to
        // an appropriate native PHP type dependant upon the associated value
        // given with the key in the pair. Dayle made this comment line up.
        if ($this->hasCast($key)) {
            return $this->castAttribute($key, $value);
        }

        // If the attribute is listed as a date, we will convert it to a DateTime
        // instance on retrieval, which makes it quite convenient to work with
        // date fields without having to create a mutator for each property.
        if ($value !== null
            && \in_array($key, $this->getDates(), true)) {
            return $this->asDateTime($value);
        }

        return $value;
    }

    /**
     * Determine if a get mutator exists for an attribute.
     */
    public function hasGetMutator(string $key): bool
    {
        if (! isset(static::$mutatorCache[static::class])) {
            static::cacheMutatedAttributes(static::class);
        }

        return isset(static::$mutatorCache[static::class][$key]);
    }

    /**
     * Determine if a set mutator exists for an attribute.
     */
    public function hasSetMutator(string $key): bool
    {
        if (! isset(static::$mutatorCache[static::class])) {
            static::cacheMutatedAttributes(static::class);
        }

        return isset(static::$mutatorCache[static::class][$key]);
    }

    /**
     * Get the value of an attribute using its mutator.
     */
    protected function mutateAttribute(string $key, $value)
    {
        return $this->{'get'.Str::studly($key).'Attribute'}($value);
    }

    /**
     * Set the value of an attribute using its mutator.
     */
    protected function setMutatedAttributeValue(string $key, $value)
    {
        return $this->{'set'.Str::studly($key).'Attribute'}($value);
    }

    /**
     * Cache the mutated attributes for a class.
     */
    public static function cacheMutatedAttributes(string $class): void
    {
        static::$mutatorCache[$class] = collect(static::getMutatedAttributes($class))->map(function ($match) {
            return lcfirst(static::$snakeAttributes ? Str::snake($match) : $match);
        })->flip()->all();
    }

    /**
     * Get all of the attribute mutator methods.
     */
    protected static function getMutatedAttributes(string $class): array
    {
        preg_match_all('/(?<=^|;)get([^;]+?)Attribute(;|$)/', implode(';', get_class_methods($class)), $matches);

        return $matches[1];
    }

    /**
     * Determine if the given attribute is a date or date castable.
     */
    protected function isDateAttribute(string $key): bool
    {
        return in_array($key, $this->getDates(), true) ||
               $this->isDateCastable($key);
    }

    /**
     * Determine whether an attribute should be cast to a native type.
     */
    public function hasCast(string $key, ?array $types = null): bool
    {
        if (array_key_exists($key, $this->getCasts())) {
            return $types ? in_array($this->getCastType($key), (array) $types, true) : true;
        }

        return false;
    }

    /**
     * Get the casts array.
     */
    public function getCasts(): array
    {
        if ($this->getIncrementing()) {
            return array_merge([$this->getKeyName() => $this->getKeyType()], $this->casts);
        }

        return $this->casts;
    }

    /**
     * Determine whether a value is Date / DateTime castable for inbound manipulation.
     */
    protected function isDateCastable(string $key): bool
    {
        return $this->hasCast($key, ['date', 'datetime', 'immutable_date', 'immutable_datetime']);
    }

    /**
     * Determine whether a value is JSON castable for inbound manipulation.
     */
    protected function isJsonCastable(string $key): bool
    {
        return $this->hasCast($key, ['array', 'json', 'object', 'collection']);
    }

    /**
     * Determine if the given key is cast using a custom class.
     */
    protected function isClassCastable(string $key): bool
    {
        $casts = $this->getCasts();

        if (!array_key_exists($key, $casts)) {
            return false;
        }

        $castType = $this->parseCasterClass($casts[$key]);

        if (in_array($castType, static::$primitiveCastTypes)) {
            return false;
        }

        if (class_exists($castType)) {
            return true;
        }

        throw new InvalidArgumentException("Cast to [{$castType}] failed: class does not exist.");
    }

    /**
     * Cast an attribute to a native PHP type.
     */
    protected function castAttribute(string $key, $value)
    {
        $castType = $this->getCastType($key);

        if (is_null($value) && in_array($castType, static::$primitiveCastTypes)) {
            return $value;
        }

        // Here we will call the cast method on the Castable class so developers
        // can implement custom logic for retrieving the cast result. If the
        // class does not exist we'll just call the built-in cast methods.
        if ($this->isClassCastable($key)) {
            return $this->getClassCastableAttributeValue($key, $value);
        }

        return $this->castAttributeValue($castType, $value);
    }

    /**
     * Cast the given attribute using a custom cast class.
     */
    protected function getClassCastableAttributeValue(string $key, $value)
    {
        $caster = $this->resolveCasterClass($key);

        $objectCached = is_object($value);

        if ($objectCached && $value instanceof $caster) {
            return $value;
        }

        $value = $caster::get($this, $key, $value, $this->attributes);

        if ($caster::$withoutObjectCaching ||
            (is_object($value) && $this->preventsAccessorCaching($caster, $value))) {
            return $value;
        }

        return $value;
    }

    /**
     * Cast an attribute value using built-in cast types.
     */
    protected function castAttributeValue(string $castType, $value)
    {
        switch ($castType) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'real':
            case 'float':
            case 'double':
                return $this->fromFloat($value);
            case 'decimal':
                return $this->asDecimal($value, explode(':', $this->getCasts()[$key], 2)[1]);
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'object':
                return $this->fromJson($value, true);
            case 'array':
            case 'json':
                return $this->fromJson($value);
            case 'collection':
                return new Collection($this->fromJson($value));
            case 'date':
                return $this->asDate($value);
            case 'datetime':
            case 'custom_datetime':
                return $this->asDateTime($value);
            case 'immutable_date':
                return $this->asDate($value)->toImmutable();
            case 'immutable_custom_datetime':
            case 'immutable_datetime':
                return $this->asDateTime($value)->toImmutable();
            case 'timestamp':
                return $this->asTimestamp($value);
        }

        return $value;
    }

    /**
     * Get the type of cast for a model attribute.
     */
    protected function getCastType(string $key): string
    {
        $castType = $this->getCasts()[$key];

        if (isset(static::$castTypeCache[$castType])) {
            return static::$castTypeCache[$castType];
        }

        if ($this->isCustomDateTimeCast($castType)) {
            $convertedCastType = 'custom_datetime';
        } elseif ($this->isImmutableCustomDateTimeCast($castType)) {
            $convertedCastType = 'immutable_custom_datetime';
        } elseif ($this->isDecimalCast($castType)) {
            $convertedCastType = 'decimal';
        } else {
            $convertedCastType = trim(strtolower($castType));
        }

        if (!in_array($convertedCastType, static::$primitiveCastTypes)) {
            $convertedCastType = $castType;
        }

        return static::$castTypeCache[$castType] = $convertedCastType;
    }

    /**
     * Determine if the cast type is a custom date time cast.
     */
    protected function isCustomDateTimeCast(string $cast): bool
    {
        return strncmp($cast, 'date:', 5) === 0 ||
               strncmp($cast, 'datetime:', 9) === 0;
    }

    /**
     * Determine if the cast type is an immutable custom date time cast.
     */
    protected function isImmutableCustomDateTimeCast(string $cast): bool
    {
        return strncmp($cast, 'immutable_date:', 15) === 0 ||
               strncmp($cast, 'immutable_datetime:', 19) === 0;
    }

    /**
     * Determine if the cast type is a decimal cast.
     */
    protected function isDecimalCast(string $cast): bool
    {
        return strncmp($cast, 'decimal:', 8) === 0;
    }

    /**
     * Set the value of a class castable attribute.
     */
    protected function setClassCastableAttribute(string $key, $value): void
    {
        $caster = $this->resolveCasterClass($key);

        $this->attributes = array_merge(
            $this->attributes,
            $caster::set($this, $key, $value, $this->attributes)
        );
    }

    /**
     * Resolve the custom caster class for a given key.
     */
    protected function resolveCasterClass(string $key): string
    {
        $castType = $this->getCasts()[$key];

        $arguments = [];

        if (is_string($castType) && str_contains($castType, ':')) {
            $segments = explode(':', $castType, 2);

            $castType = $segments[0];
            $arguments = explode(',', $segments[1]);
        }

        if (is_subclass_of($castType, Castable::class)) {
            $castType = $castType::castUsing($arguments);
        }

        if (is_callable($castType)) {
            $castType = $castType($arguments);
        }

        return $castType;
    }

    /**
     * Parse the given caster class, removing any arguments.
     */
    protected function parseCasterClass(string $class): string
    {
        return str_contains($class, ':')
            ? explode(':', $class, 2)[0]
            : $class;
    }

    /**
     * Determine if the given attribute may be mass assigned.
     */
    public function isFillable(string $key): bool
    {
        if (static::$unguarded) {
            return true;
        }

        // If the key is in the "fillable" array, we can of course assume that it's
        // a fillable attribute. Otherwise, we will check the guarded array when
        // we need to determine if the attribute is black-listed on the model.
        if (in_array($key, $this->getFillable())) {
            return true;
        }

        // If the attribute is explicitly listed in the "guarded" array then we can
        // return false immediately. This means this attribute is definitely not
        // fillable and there is no point checking anything else in this method.
        if ($this->isGuarded($key)) {
            return false;
        }

        return empty($this->getFillable()) && !Str::startsWith($key, '_');
    }

    /**
     * Determine if the given key is guarded.
     */
    public function isGuarded(string $key): bool
    {
        return in_array($key, $this->getGuarded()) || $this->getGuarded() == ['*'];
    }

    /**
     * Determine if the model is totally guarded.
     */
    public function totallyGuarded(): bool
    {
        return count($this->getFillable()) === 0 && $this->getGuarded() == ['*'];
    }

    /**
     * Get the fillable attributes for the model.
     */
    public function getFillable(): array
    {
        return $this->fillable;
    }

    /**
     * Set the fillable attributes for the model.
     */
    public function fillable(array $fillable): static
    {
        $this->fillable = $fillable;

        return $this;
    }

    /**
     * Get the guarded attributes for the model.
     */
    public function getGuarded(): array
    {
        return $this->guarded;
    }

    /**
     * Set the guarded attributes for the model.
     */
    public function guard(array $guarded): static
    {
        $this->guarded = $guarded;

        return $this;
    }

    /**
     * Disable all mass assignable restrictions.
     */
    public static function unguard(bool $state = true): void
    {
        static::$unguarded = $state;
    }

    /**
     * Enable the mass assignment restrictions.
     */
    public static function reguard(): void
    {
        static::$unguarded = false;
    }

    /**
     * Determine if current state is "unguarded".
     */
    public static function isUnguarded(): bool
    {
        return static::$unguarded;
    }

    /**
     * Run the given callable while being unguarded.
     */
    public static function unguarded(callable $callback)
    {
        if (static::$unguarded) {
            return $callback();
        }

        static::unguard();

        try {
            return $callback();
        } finally {
            static::reguard();
        }
    }

    /**
     * Get the hidden attributes for the model.
     */
    public function getHidden(): array
    {
        return $this->hidden;
    }

    /**
     * Set the hidden attributes for the model.
     */
    public function setHidden(array $hidden): static
    {
        $this->hidden = $hidden;

        return $this;
    }

    /**
     * Get the visible attributes for the model.
     */
    public function getVisible(): array
    {
        return $this->visible;
    }

    /**
     * Set the visible attributes for the model.
     */
    public function setVisible(array $visible): static
    {
        $this->visible = $visible;

        return $this;
    }

    /**
     * Make the given, typically hidden, attributes visible.
     */
    public function makeVisible($attributes): static
    {
        $attributes = is_array($attributes) ? $attributes : func_get_args();

        $this->hidden = array_diff($this->hidden, $attributes);

        if (!empty($this->visible)) {
            $this->visible = array_merge($this->visible, $attributes);
        }

        return $this;
    }

    /**
     * Make the given, typically visible, attributes hidden.
     */
    public function makeHidden($attributes): static
    {
        $this->hidden = array_merge(
            $this->hidden, is_array($attributes) ? $attributes : func_get_args()
        );

        return $this;
    }

    /**
     * Get the relationships that are touched on save.
     */
    public function getTouchedRelations(): array
    {
        return $this->touches ?? [];
    }

    /**
     * Set the relationships that are touched on save.
     */
    public function setTouchedRelations(array $touches): static
    {
        $this->touches = $touches;

        return $this;
    }

    /**
     * Touch the owning relations of the model.
     */
    public function touchOwners(): void
    {
        foreach ($this->getTouchedRelations() as $relation) {
            $this->$relation()?->touch();
        }
    }

    /**
     * Update the model's update timestamp.
     */
    public function touch(): bool
    {
        if (!$this->usesTimestamps()) {
            return false;
        }

        $this->updateTimestamps();

        return $this->save();
    }

    /**
     * Get all of the loaded relations for the instance.
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * Get a specified relationship.
     */
    public function getRelation(string $relation)
    {
        return $this->relations[$relation];
    }

    /**
     * Determine if the given relation is loaded.
     */
    public function relationLoaded(string $key): bool
    {
        return array_key_exists($key, $this->relations);
    }

    /**
     * Set the given relationship on the model.
     */
    public function setRelation(string $relation, $value): static
    {
        $this->relations[$relation] = $value;

        return $this;
    }

    /**
     * Unset a loaded relationship.
     */
    public function unsetRelation(string $relation): static
    {
        unset($this->relations[$relation]);

        return $this;
    }

    /**
     * Set the entire relations array on the model.
     */
    public function setRelations(array $relations): static
    {
        $this->relations = $relations;

        return $this;
    }

    /**
     * Duplicate the instance and unset relations on the clone.
     */
    public function duplicate(array $except = null): static
    {
        $defaults = [
            $this->getKeyName(),
            $this->getCreatedAtColumn(),
            $this->getUpdatedAtColumn(),
        ];

        $except = $except ? array_unique(array_merge($except, $defaults)) : $defaults;

        $attributes = array_diff_key($this->getAttributes(), array_flip($except));

        return tap(new static, function ($instance) use ($attributes) {
            $instance->setRawAttributes($attributes);

            $instance->setRelations($this->relations);
        });
    }

    /**
     * Clone the model into a new, non-existing instance.
     */
    public function replicate(array $except = null): static
    {
        $instance = $this->duplicate($except);

        $instance->exists = false;

        $instance->setConnection($this->getConnectionName());

        return $instance;
    }

    /**
     * Determine if two models have the same ID and belong to the same table.
     */
    public function is(?Model $model): bool
    {
        return !is_null($model) &&
               $this->getKey() === $model->getKey() &&
               $this->getTable() === $model->getTable() &&
               $this->getConnectionName() === $model->getConnectionName();
    }

    /**
     * Determine if two models are not the same.
     */
    public function isNot(?Model $model): bool
    {
        return !$this->is($model);
    }

    /**
     * Get the database connection for the model.
     */
    public function getConnectionName(): ?string
    {
        return $this->connection;
    }

    /**
     * Fire the given event for the model.
     */
    protected function fireModelEvent(string $event, bool $halt = true)
    {
        if (!isset(static::$dispatcher)) {
            return true;
        }

        // First, we will get the proper method to call on the event dispatcher, and then we
        // will attempt to fire a custom, object based event for the given event. If that
        // returns a result we can return that result, or we'll call the string events.
        $method = $halt ? 'until' : 'dispatch';

        $result = $this->filterModelEventResults(
            $this->fireCustomModelEvent($event, $method)
        );

        if ($result === false) {
            return false;
        }

        return !empty($result) ? $result : static::$dispatcher->{$method}(
            "eloquent.{$event}: ".static::class, $this
        );
    }

    /**
     * Fire a custom model event for the given event.
     */
    protected function fireCustomModelEvent(string $event, string $method)
    {
        if (!isset($this->dispatchesEvents[$event])) {
            return;
        }

        $result = static::$dispatcher->$method(new $this->dispatchesEvents[$event]($this));

        if (!is_null($result)) {
            return $result;
        }
    }

    /**
     * Filter the model event results.
     */
    protected function filterModelEventResults($result)
    {
        if (is_array($result)) {
            $result = array_filter($result, function ($response) {
                return !is_null($response);
            });
        }

        return $result;
    }

    /**
     * Convert the model instance to an array.
     */
    public function toArray(): array
    {
        return array_merge($this->attributesToArray(), $this->relationsToArray());
    }

    /**
     * Convert the model's attributes to an array.
     */
    public function attributesToArray(): array
    {
        // If an attribute is a date, we will cast it to a string after converting it
        // to a DateTime / Carbon instance. This is so we will get some consistent
        // formatting while accessing attributes vs. arraying / JSONing a model.
        $attributes = $this->addDateAttributesToArray(
            $attributes = $this->getArrayableAttributes()
        );

        $attributes = $this->addMutatedAttributesToArray(
            $attributes, $mutatedAttributes = $this->getMutatedAttributes()
        );

        // Next we will handle any casts that have been setup for this model and cast
        // the values to their appropriate type. If the attribute has a mutator we
        // will not perform the cast on those attributes to avoid any confusion.
        $attributes = $this->addCastAttributesToArray(
            $attributes, $mutatedAttributes
        );

        // Here we will grab all of the appended, calculated attributes to this model
        // as these attributes are not really in the attributes array, but are run
        // when we need to array or JSON the model for convenience to the coder.
        foreach ($this->getArrayableAppends() as $key) {
            $attributes[$key] = $this->mutateAttributeForArray($key, null);
        }

        return $attributes;
    }

    /**
     * Get an attribute array of all arrayable attributes.
     */
    protected function getArrayableAttributes(): array
    {
        return $this->getArrayableItems($this->getAttributes());
    }

    /**
     * Get all of the appendable values that are arrayable.
     */
    protected function getArrayableAppends(): array
    {
        if (!count($this->appends)) {
            return [];
        }

        return $this->getArrayableItems(
            array_combine($this->appends, $this->appends)
        );
    }

    /**
     * Get the model's relationships in array form.
     */
    public function relationsToArray(): array
    {
        $attributes = [];

        foreach ($this->getArrayableRelations() as $key => $value) {
            // If the values implements the Arrayable interface we can just call this
            // toArray method on the instances which will convert both models and
            // collections to their proper array form and we'll set the values.
            if ($value instanceof Arrayable) {
                $relation = $value->toArray();
            }

            // If the value is null, we'll still go ahead and set it in this list of
            // attributes since null is used to represent empty relationships if
            // it a has one or belongs to type relationships on the models.
            elseif (is_null($value)) {
                $relation = $value;
            }

            // If the relationships snake-casing is enabled, we will snake case this
            // key so that the relation attribute is snake cased in this returned
            // array to the developers, making this consistent with attributes.
            if (static::$snakeAttributes) {
                $key = Str::snake($key);
            }

            // If the relation value has been set, we will set it on this attributes
            // list for returning. If it was not arrayable or null, we'll not set
            // the value on the array because it is some type of invalid value.
            if (isset($relation) || is_null($value)) {
                $attributes[$key] = $relation;
            }

            unset($relation);
        }

        return $attributes;
    }

    /**
     * Convert the model instance to JSON.
     */
    public function toJson(int $options = 0): string
    {
        $json = json_encode($this->jsonSerialize(), $options);

        if (JSON_ERROR_NONE !== json_last_error()) {
            throw JsonEncodingException::forModel($this, json_last_error_msg());
        }

        return $json;
    }

    /**
     * Convert the object into something JSON serializable.
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * Reload a fresh model instance from the database.
     */
    public function fresh($with = []): ?static
    {
        if (!$this->exists) {
            return;
        }

        return $this->setKeysForSaveQuery($this->newQueryWithoutScopes())
                    ->with(is_string($with) ? func_get_args() : $with)
                    ->first();
    }

    /**
     * Reload the current model instance with fresh attributes from the database.
     */
    public function refresh(): static
    {
        if (!$this->exists) {
            return $this;
        }

        $this->setRawAttributes(
            $this->setKeysForSaveQuery($this->newQueryWithoutScopes())->firstOrFail()->attributes
        );

        $this->load(collect($this->relations)->except('pivot')->keys()->toArray());

        $this->syncOriginal();

        return $this;
    }

    /**
     * Determine if the model exists in the database.
     */
    public function exists(): bool
    {
        return $this->exists;
    }

    /**
     * Determine if the model was recently created.
     */
    public function wasRecentlyCreated(): bool
    {
        return $this->wasRecentlyCreated;
    }

    /**
     * Get the connection resolver instance.
     */
    public static function getConnectionResolver()
    {
        return static::$resolver;
    }

    /**
     * Set the connection resolver instance.
     */
    public static function setConnectionResolver($resolver): void
    {
        static::$resolver = $resolver;
    }

    /**
     * Get the event dispatcher instance.
     */
    public static function getEventDispatcher()
    {
        return static::$dispatcher;
    }

    /**
     * Set the event dispatcher instance.
     */
    public static function setEventDispatcher($dispatcher): void
    {
        static::$dispatcher = $dispatcher;
    }

    /**
     * Unset the event dispatcher for models.
     */
    public static function unsetEventDispatcher(): void
    {
        static::$dispatcher = null;
    }

    /**
     * Define a one-to-one relationship.
     */
    public function hasOne(string $related, ?string $foreignKey = null, ?string $localKey = null): HasOne
    {
        $instance = $this->newRelatedInstance($related);

        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $localKey = $localKey ?: $this->getKeyName();

        return $this->newHasOne($instance->newQuery(), $this, $instance->getTable().'.'.$foreignKey, $localKey);
    }

    /**
     * Create a new has one relationship instance.
     */
    protected function newHasOne(Builder $query, Model $parent, string $foreignKey, string $localKey): HasOne
    {
        return new HasOne($query, $parent, $foreignKey, $localKey);
    }

    /**
     * Define a one-to-many relationship.
     */
    public function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): HasMany
    {
        $instance = $this->newRelatedInstance($related);

        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $localKey = $localKey ?: $this->getKeyName();

        return $this->newHasMany(
            $instance->newQuery(), $this, $instance->getTable().'.'.$foreignKey, $localKey
        );
    }

    /**
     * Create a new has many relationship instance.
     */
    protected function newHasMany(Builder $query, Model $parent, string $foreignKey, string $localKey): HasMany
    {
        return new HasMany($query, $parent, $foreignKey, $localKey);
    }

    /**
     * Define an inverse one-to-one or many relationship.
     */
    public function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null, ?string $relation = null): BelongsTo
    {
        // If no relation name was given, we will use this debug backtrace to extract
        // the calling method's name and use that as the relationship name as most
        // of the time this will be what we desire to use for the relationships.
        if (is_null($relation)) {
            $relation = $this->guessBelongsToRelation();
        }

        $instance = $this->newRelatedInstance($related);

        // If no foreign key was supplied, we can use a backtrace to guess the proper
        // foreign key name by using the name of the relationship function, which
        // when combined with an "_id" should conventionally match the columns.
        if (is_null($foreignKey)) {
            $foreignKey = Str::snake($relation).'_'.$instance->getKeyName();
        }

        // Once we have the foreign key names, we'll just create a new Eloquent query
        // for the related models and returns the relationship instance which will
        // actually be responsible for retrieving and hydrating every relations.
        $ownerKey = $ownerKey ?: $instance->getKeyName();

        return $this->newBelongsTo(
            $instance->newQuery(), $this, $foreignKey, $ownerKey, $relation
        );
    }

    /**
     * Create a new belongs to relationship instance.
     */
    protected function newBelongsTo(Builder $query, Model $child, string $foreignKey, string $ownerKey, string $relation): BelongsTo
    {
        return new BelongsTo($query, $child, $foreignKey, $ownerKey, $relation);
    }

    /**
     * Define a many-to-many relationship.
     */
    public function belongsToMany(
        string $related,
        ?string $table = null,
        ?string $foreignPivotKey = null,
        ?string $relatedPivotKey = null,
        ?string $parentKey = null,
        ?string $relatedKey = null,
        ?string $relation = null
    ): BelongsToMany {
        // If no relationship name was passed, we will pull backtraces to get the
        // name of the calling function. We will use that function name as the
        // title of this relation since that is a great convention to apply.
        if (is_null($relation)) {
            $relation = $this->guessBelongsToManyRelation();
        }

        // First, we'll need to determine the foreign key and "other key" for the
        // relationship. Once we have determined the keys we'll make the query
        // instances as well as the relationship instances we need for this.
        $instance = $this->newRelatedInstance($related);

        $foreignPivotKey = $foreignPivotKey ?: $this->getForeignKey();

        $relatedPivotKey = $relatedPivotKey ?: $instance->getForeignKey();

        // If no table name was provided, we can guess it by concatenating the two
        // models using underscores in alphabetical order. The two model names
        // are transformed to snake case from their default CamelCase also.
        if (is_null($table)) {
            $table = $this->joiningTable($related, $instance);
        }

        return $this->newBelongsToMany(
            $instance->newQuery(), $this, $table, $foreignPivotKey,
            $relatedPivotKey, $parentKey ?: $this->getKeyName(),
            $relatedKey ?: $instance->getKeyName(), $relation
        );
    }

    /**
     * Create a new belongs to many relationship instance.
     */
    protected function newBelongsToMany(
        Builder $query,
        Model $parent,
        string $table,
        string $foreignPivotKey,
        string $relatedPivotKey,
        string $parentKey,
        string $relatedKey,
        ?string $relationName = null
    ): BelongsToMany {
        return new BelongsToMany(
            $query, $parent, $table, $foreignPivotKey,
            $relatedPivotKey, $parentKey, $relatedKey, $relationName
        );
    }

    /**
     * Get the joining table name for a many-to-many relation.
     */
    public function joiningTable(string $related, ?Model $instance = null): string
    {
        // The joining table name, by convention, is simply the snake cased models
        // sorted alphabetically and concatenated with an underscore, so we can
        // just sort the models and join them together to get the table name.
        $models = [
            Str::snake(class_basename($this)),
            Str::snake(class_basename($instance ?: $related)),
        ];

        // Now that we have the model names in an array we can just sort them and
        // use the implode function to join them together with an underscores,
        // which is typically the naming convention for junction table names.
        sort($models);

        return strtolower(implode('_', $models));
    }

    /**
     * Get the default foreign key name for the model.
     */
    public function getForeignKey(): string
    {
        return Str::snake(class_basename($this)).'_'.$this->getKeyName();
    }

    /**
     * Create a new model instance for a related model.
     */
    protected function newRelatedInstance(string $class): Model
    {
        return tap(new $class, function ($instance) {
            if (!$instance->getConnectionName()) {
                $instance->setConnection($this->connection);
            }
        });
    }

    /**
     * Guess the "belongs to" relationship name.
     */
    protected function guessBelongsToRelation(): string
    {
        [$one, $two, $caller] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);

        return $caller['function'];
    }

    /**
     * Guess the "belongs to many" relationship name.
     */
    protected function guessBelongsToManyRelation(): string
    {
        $caller = Arr::first(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), function ($trace) {
            return !in_array($trace['function'], Model::$manyMethods);
        });

        return !is_null($caller) ? $caller['function'] : null;
    }

    /**
     * Define a polymorphic one-to-one relationship.
     */
    public function morphOne(string $related, string $name, ?string $type = null, ?string $id = null, ?string $localKey = null): MorphOne
    {
        $instance = $this->newRelatedInstance($related);

        [$type, $id] = $this->getMorphs($name, $type, $id);

        $table = $instance->getTable();

        $localKey = $localKey ?: $this->getKeyName();

        return $this->newMorphOne($instance->newQuery(), $this, $table.'.'.$type, $table.'.'.$id, $localKey);
    }

    /**
     * Define a polymorphic one-to-many relationship.
     */
    public function morphMany(string $related, string $name, ?string $type = null, ?string $id = null, ?string $localKey = null): MorphMany
    {
        $instance = $this->newRelatedInstance($related);

        [$type, $id] = $this->getMorphs($name, $type, $id);

        $table = $instance->getTable();

        $localKey = $localKey ?: $this->getKeyName();

        return $this->newMorphMany($instance->newQuery(), $this, $table.'.'.$type, $table.'.'.$id, $localKey);
    }

    /**
     * Define a polymorphic, inverse one-to-one or many relationship.
     */
    public function morphTo(?string $name = null, ?string $type = null, ?string $id = null, ?string $ownerKey = null): MorphTo
    {
        // If no name is provided, we will use the backtrace to get the function name
        // since that is most likely the name of the polymorphic interface. We can
        // use that to get both the class and foreign key that will be utilized.
        $name = $name ?: $this->guessBelongsToRelation();

        [$type, $id] = $this->getMorphs(
            Str::snake($name), $type, $id
        );

        // If the type value is null it is probably safe to assume we're eager loading
        // the relationship. In this case we'll just pass in a dummy query as there
        // is no need to group the results they will be populated by the hydrate.
        return empty($class = $this->{$type})
                    ? $this->morphEagerTo($name, $type, $id, $ownerKey)
                    : $this->morphInstanceTo($class, $name, $type, $id, $ownerKey);
    }

    /**
     * Get the polymorphic relationship columns.
     */
    protected function getMorphs(string $name, ?string $type, ?string $id): array
    {
        return [$type ?: $name.'_type', $id ?: $name.'_id'];
    }

    /**
     * Get the class name for polymorphic relations.
     */
    public function getMorphClass(): string
    {
        $morphMap = Relation::getMorphMap();

        if (!empty($morphMap) && in_array(static::class, $morphMap)) {
            return array_search(static::class, $morphMap, true);
        }

        return static::class;
    }

    /**
     * Create a new pivot model instance.
     */
    public function newPivot(Model $parent, array $attributes, string $table, bool $exists, ?string $using = null): Pivot
    {
        return $using ? $using::fromRawAttributes($parent, $attributes, $table, $exists)
                      : Pivot::fromAttributes($parent, $attributes, $table, $exists);
    }

    /**
     * Determine if the model has a given relationship.
     */
    public function hasRelation(string $key): bool
    {
        return method_exists($this, $key) ||
               (static::$relationResolvers[get_class($this)][$key] ?? null);
    }

    /**
     * Get a specified relationship.
     */
    public function getRelation(string $relation)
    {
        return $this->relations[$relation];
    }

    /**
     * Determine if the given relation is loaded.
     */
    public function relationLoaded(string $key): bool
    {
        return array_key_exists($key, $this->relations);
    }

    /**
     * Set the given relationship on the model.
     */
    public function setRelation(string $relation, $value): static
    {
        $this->relations[$relation] = $value;

        return $this;
    }

    /**
     * Unset a loaded relationship.
     */
    public function unsetRelation(string $relation): static
    {
        unset($this->relations[$relation]);

        return $this;
    }

    /**
     * Get all the loaded relations for the instance.
     */
    public function getRelations(): array
    {
        return $this->relations;
    }

    /**
     * Set the entire relations array on the model.
     */
    public function setRelations(array $relations): static
    {
        $this->relations = $relations;

        return $this;
    }

    /**
     * Handle dynamic method calls into the model.
     */
    public function __call(string $method, array $parameters)
    {
        if (in_array($method, ['increment', 'decrement'])) {
            return $this->$method(...$parameters);
        }

        if ($resolver = (static::$relationResolvers[get_class($this)][$method] ?? null)) {
            return $resolver($this);
        }

        return $this->forwardCallTo($this->newQuery(), $method, $parameters);
    }

    /**
     * Handle dynamic static method calls into the model.
     */
    public static function __callStatic(string $method, array $parameters)
    {
        return (new static)->$method(...$parameters);
    }

    /**
     * Convert the model to its string representation.
     */
    public function __toString(): string
    {
        return $this->toJson();
    }

    /**
     * Prepare the object for serialization.
     */
    public function __sleep(): array
    {
        $this->mergeAttributesFromClassCasts();

        return array_keys(get_object_vars($this));
    }

    /**
     * When a model is being unserialized, check if it needs to be booted.
     */
    public function __wakeup(): void
    {
        $this->bootIfNotBooted();
    }

    // ArrayAccess interface methods
    public function offsetExists($offset): bool
    {
        return !is_null($this->getAttribute($offset));
    }

    public function offsetGet($offset): mixed
    {
        return $this->getAttribute($offset);
    }

    public function offsetSet($offset, $value): void
    {
        $this->setAttribute($offset, $value);
    }

    public function offsetUnset($offset): void
    {
        unset($this->attributes[$offset], $this->relations[$offset]);
    }

    /**
     * Dynamically retrieve attributes on the model.
     */
    public function __get(string $key)
    {
        return $this->getAttribute($key);
    }

    /**
     * Dynamically set attributes on the model.
     */
    public function __set(string $key, $value): void
    {
        $this->setAttribute($key, $value);
    }

    /**
     * Determine if an attribute or relation exists on the model.
     */
    public function __isset(string $key): bool
    {
        return $this->offsetExists($key);
    }

    /**
     * Unset an attribute on the model.
     */
    public function __unset(string $key): void
    {
        $this->offsetUnset($key);
    }

    // Static properties for global model behavior
    protected static $resolver;
    protected static $dispatcher;
    protected static $unguarded = false;
    protected static $snakeAttributes = true;
    protected static $relationResolvers = [];
    protected static $primitiveCastTypes = [
        'array', 'bool', 'boolean', 'collection', 'custom_datetime',
        'date', 'datetime', 'decimal', 'double', 'encrypted', 'encrypted:array', 
        'encrypted:collection', 'encrypted:json', 'encrypted:object', 'float', 'hashed',
        'int', 'integer', 'json', 'object', 'real', 'string', 'timestamp'
    ];
}