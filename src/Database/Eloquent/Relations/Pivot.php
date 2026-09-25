<?php

declare(strict_types=1);

namespace Horizon\Database\Eloquent\Relations;

use Horizon\Database\Eloquent\Model;

/**
 * Pivot Model
 * 
 * Represents a pivot table record in a many-to-many relationship.
 */
class Pivot extends Model
{
    /**
     * The parent model of the relationship.
     */
    public Model $pivotParent;

    /**
     * The name of the foreign key column.
     */
    protected string $foreignKey;

    /**
     * The name of the "other key" column.
     */
    protected string $relatedKey;

    /**
     * The attributes that aren't mass assignable.
     */
    protected array $guarded = [];

    /**
     * Indicates if the model should be timestamped.
     */
    public bool $timestamps = false;

    /**
     * Create a new pivot model instance.
     */
    public function __construct(Model $parent, array $attributes, string $table, bool $exists = false, ?string $using = null)
    {
        $this->setRawAttributes($attributes, true);

        $this->setTable($table);

        $this->setConnection($parent->getConnectionName());

        $this->pivotParent = $parent;

        $this->exists = $exists;

        $this->timestamps = $this->hasTimestampAttributes();

        $this->syncOriginal();
    }

    /**
     * Set the keys for a save update query.
     */
    protected function setKeysForSaveQuery($query)
    {
        if (isset($this->attributes[$this->foreignKey])) {
            $query->where($this->foreignKey, $this->attributes[$this->foreignKey]);
        }

        if (isset($this->attributes[$this->relatedKey])) {
            return $query->where($this->relatedKey, $this->attributes[$this->relatedKey]);
        }

        return $query;
    }

    /**
     * Delete the pivot model record from the database.
     */
    public function delete(): ?bool
    {
        if (isset($this->attributes[$this->foreignKey]) &&
            isset($this->attributes[$this->relatedKey])) {
            return (bool) $this->getDeleteQuery()->delete();
        }

        return parent::delete();
    }

    /**
     * Get the query builder for a delete operation on the pivot.
     */
    protected function getDeleteQuery()
    {
        return $this->newQueryWithoutRelationships()->where([
            $this->foreignKey => $this->attributes[$this->foreignKey],
            $this->relatedKey => $this->attributes[$this->relatedKey],
        ]);
    }

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        if (!isset($this->table)) {
            $this->setTable(str_replace(
                '\\', '', Str::snake(Str::singular(class_basename($this)))
            ));
        }

        return $this->table;
    }

    /**
     * Get the foreign key column name.
     */
    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    /**
     * Get the "related key" column name.
     */
    public function getRelatedKey(): string
    {
        return $this->relatedKey;
    }

    /**
     * Get the "other key" column name.
     */
    public function getOtherKey(): string
    {
        return $this->getRelatedKey();
    }

    /**
     * Set the key names for the pivot model instance.
     */
    public function setPivotKeys(string $foreignKey, string $relatedKey): static
    {
        $this->foreignKey = $foreignKey;

        $this->relatedKey = $relatedKey;

        return $this;
    }

    /**
     * Determine if the pivot model or given attributes has timestamp attributes.
     */
    public function hasTimestampAttributes(?array $attributes = null): bool
    {
        return array_key_exists($this->getCreatedAtColumn(), $attributes ?? $this->attributes);
    }

    /**
     * Get the name of the "created at" column.
     */
    public function getCreatedAtColumn(): string
    {
        return $this->pivotParent->getCreatedAtColumn();
    }

    /**
     * Get the name of the "updated at" column.
     */
    public function getUpdatedAtColumn(): string
    {
        return $this->pivotParent->getUpdatedAtColumn();
    }

    /**
     * Get the queueable identity for the entity.
     */
    public function getQueueableId()
    {
        if (isset($this->attributes[$this->foreignKey]) &&
            isset($this->attributes[$this->relatedKey])) {
            return sprintf(
                '%s:%s:%s:%s:%s:%s',
                $this->foreignKey, $this->attributes[$this->foreignKey],
                $this->relatedKey, $this->attributes[$this->relatedKey],
                $this->getTable(), $this->getConnectionName()
            );
        }

        return parent::getQueueableId();
    }

    /**
     * Get a new query to restore one or more models by their queueable IDs.
     */
    public function newQueryForRestoration($ids): Builder
    {
        if (is_array($ids)) {
            return $this->newQueryForCollectionRestoration($ids);
        }

        if (!str_contains($ids, ':')) {
            return parent::newQueryForRestoration($ids);
        }

        $segments = explode(':', $ids);

        return $this->newQueryWithoutScopes()
                    ->where($segments[0], $segments[1])
                    ->where($segments[2], $segments[3]);
    }

    /**
     * Get a new query to restore multiple models by their queueable IDs.
     */
    protected function newQueryForCollectionRestoration(array $ids): Builder
    {
        if (!str_contains($ids[0], ':')) {
            return parent::newQueryForRestoration($ids);
        }

        $query = $this->newQueryWithoutScopes();

        foreach ($ids as $id) {
            $segments = explode(':', $id);

            $query->orWhere(function ($query) use ($segments) {
                return $query->where($segments[0], $segments[1])
                            ->where($segments[2], $segments[3]);
            });
        }

        return $query;
    }

    /**
     * Unset all the loaded relations for the instance.
     */
    public function unsetRelations(): static
    {
        $this->pivotParent = null;

        return parent::unsetRelations();
    }
}