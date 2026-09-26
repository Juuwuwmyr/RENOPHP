<?php

declare(strict_types=1);

namespace Reno\Database\Eloquent;

use BadMethodCallException;
use Closure;
use Reno\Database\Query\QueryBuilder;
use Reno\Support\Collection;
use Reno\Support\Str;
use InvalidArgumentException;

/**
 * Eloquent Query Builder
 * 
 * Provides a fluent interface for building database queries with Eloquent models.
 */
class Builder
{
    /**
     * The base query builder instance.
     */
    protected QueryBuilder $query;

    /**
     * The model being queried.
     */
    protected Model $model;

    /**
     * The relationships that should be eager loaded.
     */
    protected array $eagerLoad = [];

    /**
     * All of the globally registered builder macros.
     */
    protected static array $macros = [];

    /**
     * All of the locally registered builder macros.
     */
    protected array $localMacros = [];

    /**
     * A replacement for the typical delete function.
     */
    protected ?Closure $onDelete = null;

    /**
     * The methods that should be returned from query builder.
     */
    protected array $passthru = [
        'insert', 'insertOrIgnore', 'insertGetId', 'insertUsing', 'getBindings',
        'toSql', 'dump', 'dd', 'exists', 'doesntExist', 'count', 'min', 'max',
        'avg', 'average', 'sum', 'getConnection',
    ];

    /**
     * Applied global scopes.
     */
    protected array $scopes = [];

    /**
     * Removed global scopes.
     */
    protected array $removedScopes = [];

    /**
     * Create a new Eloquent query builder instance.
     */
    public function __construct(QueryBuilder $query)
    {
        $this->query = $query;
    }

    /**
     * Create and return an un-saved model instance.
     */
    public function make(array $attributes = []): Model
    {
        return $this->newModelInstance($attributes);
    }

    /**
     * Register a new global scope.
     */
    public function withGlobalScope(string $identifier, $scope): static
    {
        $this->scopes[$identifier] = $scope;

        if (method_exists($scope, 'apply')) {
            $scope->apply($this, $this->getModel());
        }

        return $this;
    }

    /**
     * Remove a registered global scope.
     */
    public function withoutGlobalScope($scope): static
    {
        if (!is_string($scope)) {
            $scope = get_class($scope);
        }

        unset($this->scopes[$scope]);

        $this->removedScopes[] = $scope;

        return $this;
    }

    /**
     * Remove all or passed registered global scopes.
     */
    public function withoutGlobalScopes(?array $scopes = null): static
    {
        if (!is_array($scopes)) {
            $scopes = array_keys($this->scopes);
        }

        foreach ($scopes as $scope) {
            $this->withoutGlobalScope($scope);
        }

        return $this;
    }

    /**
     * Get an array of global scopes that were removed from the query.
     */
    public function removedScopes(): array
    {
        return $this->removedScopes;
    }

    /**
     * Add a where clause on the primary key to the query.
     */
    public function whereKey($id): static
    {
        if ($id instanceof Model) {
            $id = $id->getKey();
        }

        if (is_array($id) || $id instanceof Collection) {
            $this->query->whereIn($this->model->getQualifiedKeyName(), $id);

            return $this;
        }

        if ($id !== null && $this->model->getKeyType() === 'string') {
            $id = (string) $id;
        }

        return $this->where($this->model->getQualifiedKeyName(), '=', $id);
    }

    /**
     * Add a where clause on the primary key to the query.
     */
    public function whereKeyNot($id): static
    {
        if ($id instanceof Model) {
            $id = $id->getKey();
        }

        if (is_array($id) || $id instanceof Collection) {
            $this->query->whereNotIn($this->model->getQualifiedKeyName(), $id);

            return $this;
        }

        if ($id !== null && $this->model->getKeyType() === 'string') {
            $id = (string) $id;
        }

        return $this->where($this->model->getQualifiedKeyName(), '!=', $id);
    }

    /**
     * Add a basic where clause to the query.
     */
    public function where($column, $operator = null, $value = null, string $boolean = 'and'): static
    {
        if ($column instanceof Closure && is_null($operator)) {
            $column($query = $this->model->newQueryWithoutRelationships());

            $this->query->addNestedWhereQuery($query->getQuery(), $boolean);
        } else {
            $this->query->where(...func_get_args());
        }

        return $this;
    }

    /**
     * Add a "where in" clause to the query.
     */
    public function whereIn(string $column, $values, string $boolean = 'and', bool $not = false): static
    {
        $this->query->whereIn($column, $values, $boolean, $not);

        return $this;
    }

    /**
     * Add a "where not in" clause to the query.
     */
    public function whereNotIn(string $column, $values, string $boolean = 'and'): static
    {
        return $this->whereIn($column, $values, $boolean, true);
    }

    /**
     * Add an "or where" clause to the query.
     */
    public function orWhere($column, $operator = null, $value = null): static
    {
        [$value, $operator] = $this->query->prepareValueAndOperator(
            $value, $operator, func_num_args() === 2
        );

        return $this->where($column, $operator, $value, 'or');
    }

    /**
     * Add an "order by" clause to the query.
     */
    public function orderBy($column, string $direction = 'asc'): static
    {
        $this->query->orderBy($column, $direction);

        return $this;
    }

    /**
     * Add a descending "order by" clause to the query.
     */
    public function orderByDesc($column): static
    {
        return $this->orderBy($column, 'desc');
    }

    /**
     * Add an "order by" clause for a timestamp to the query.
     */
    public function latest(string $column = null): static
    {
        if (is_null($column)) {
            $column = $this->model->getCreatedAtColumn() ?? 'created_at';
        }

        $this->query->latest($column);

        return $this;
    }

    /**
     * Add an "order by" clause for a timestamp to the query.
     */
    public function oldest(string $column = null): static
    {
        if (is_null($column)) {
            $column = $this->model->getCreatedAtColumn() ?? 'created_at';
        }

        $this->query->oldest($column);

        return $this;
    }

    /**
     * Set the "limit" value of the query.
     */
    public function limit(int $value): static
    {
        $this->query->limit($value);

        return $this;
    }

    /**
     * Alias to set the "limit" value of the query.
     */
    public function take(int $value): static
    {
        return $this->limit($value);
    }

    /**
     * Set the "offset" value of the query.
     */
    public function offset(int $value): static
    {
        $this->query->offset($value);

        return $this;
    }

    /**
     * Alias to set the "offset" value of the query.
     */
    public function skip(int $value): static
    {
        return $this->offset($value);
    }

    /**
     * Set the relationships that should be eager loaded.
     */
    public function with($relations): static
    {
        $eagerLoad = $this->parseWithRelations(is_string($relations) ? func_get_args() : $relations);

        $this->eagerLoad = array_merge($this->eagerLoad, $eagerLoad);

        return $this;
    }

    /**
     * Prevent the specified relations from being eager loaded.
     */
    public function without($relations): static
    {
        $this->eagerLoad = array_diff_key(
            $this->eagerLoad,
            array_flip(is_string($relations) ? func_get_args() : $relations)
        );

        return $this;
    }

    /**
     * Create a collection of models from plain arrays.
     */
    public function hydrate(array $items): Collection
    {
        $instance = $this->newModelInstance();

        return $instance->newCollection(array_map(function ($item) use ($instance) {
            return $instance->newFromBuilder($item);
        }, $items));
    }

    /**
     * Create a collection of models from a raw query.
     */
    public function fromQuery(string $query, array $bindings = []): Collection
    {
        return $this->hydrate(
            $this->query->getConnection()->select($query, $bindings)
        );
    }

    /**
     * Find a model by its primary key.
     */
    public function find($id, array $columns = ['*'])
    {
        if (is_array($id) || $id instanceof Collection) {
            return $this->findMany($id, $columns);
        }

        return $this->whereKey($id)->first($columns);
    }

    /**
     * Find multiple models by their primary keys.
     */
    public function findMany($ids, array $columns = ['*']): Collection
    {
        $ids = $ids instanceof Collection ? $ids->modelKeys() : $ids;

        if (empty($ids)) {
            return $this->model->newCollection();
        }

        return $this->whereKey($ids)->get($columns);
    }

    /**
     * Find a model by its primary key or throw an exception.
     */
    public function findOrFail($id, array $columns = ['*'])
    {
        $result = $this->find($id, $columns);

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
     * Find a model by its primary key or return fresh model instance.
     */
    public function findOrNew($id, array $columns = ['*']): Model
    {
        if (!is_null($model = $this->find($id, $columns))) {
            return $model;
        }

        return $this->newModelInstance();
    }

    /**
     * Execute the query and get the first result.
     */
    public function first(array $columns = ['*']): ?Model
    {
        return $this->take(1)->get($columns)->first();
    }

    /**
     * Execute the query and get the first result or throw an exception.
     */
    public function firstOrFail(array $columns = ['*']): Model
    {
        if (!is_null($model = $this->first($columns))) {
            return $model;
        }

        throw new ModelNotFoundException;
    }

    /**
     * Execute the query and get the first result or call a callback.
     */
    public function firstOr($columns = ['*'], ?Closure $callback = null)
    {
        if ($columns instanceof Closure) {
            $callback = $columns;

            $columns = ['*'];
        }

        if (!is_null($model = $this->first($columns))) {
            return $model;
        }

        return $callback();
    }

    /**
     * Get a single column's value from the first result of a query.
     */
    public function value(string $column)
    {
        if ($result = $this->first([$column])) {
            return $result->{$column};
        }
    }

    /**
     * Execute the query as a "select" statement.
     */
    public function get(array $columns = ['*']): Collection
    {
        $builder = $this->applyScopes();

        // If we actually found models we will also eager load any relationships that
        // have been specified as needing to be eager loaded, which will solve the
        // n+1 query issue for the developers to avoid running a lot of queries.
        if (count($models = $builder->getModels($columns)) > 0) {
            $models = $builder->eagerLoadRelations($models);
        }

        return $builder->getModel()->newCollection($models);
    }

    /**
     * Get the hydrated models without eager loading.
     */
    public function getModels(array $columns = ['*']): array
    {
        return $this->model->hydrate(
            $this->query->get($columns)->all()
        )->all();
    }

    /**
     * Eager load the relationships for the models.
     */
    public function eagerLoadRelations(array $models): array
    {
        foreach ($this->eagerLoad as $name => $constraints) {
            // For nested eager loads we'll skip loading them here and they will be set as an
            // eager load on the query to retrieve the relation so that they will be eager
            // loaded on that query, because that is where they get hydrated as models.
            if (!str_contains($name, '.')) {
                $models = $this->eagerLoadRelation($models, $name, $constraints);
            }
        }

        return $models;
    }

    /**
     * Eagerly load the relationship on a set of models.
     */
    protected function eagerLoadRelation(array $models, string $name, Closure $constraints): array
    {
        // First we will "back up" the existing where conditions on the query so we can
        // add our eager constraints. Then we will merge the wheres that were on the
        // query back to it in order that any where conditions might be specified.
        $relation = $this->getRelation($name);

        $relation->addEagerConstraints($models);

        $constraints($relation);

        // Once we have the results, we just match those back up to their parent models
        // using the relationship instance. Then we just return the finished arrays
        // of models which have been eagerly hydrated and are readied for return.
        return $relation->match(
            $relation->initRelation($models, $name),
            $relation->getEager(), $name
        );
    }

    /**
     * Get the relation instance for the given relation name.
     */
    public function getRelation(string $name)
    {
        // We want to run a relationship query without any constrains so that we will
        // not have to remove these where clauses manually which gets really hacky
        // and error prone. We don't want constraints because we add eager ones.
        $relation = Relation::noConstraints(function () use ($name) {
            try {
                return $this->getModel()->newInstance()->$name();
            } catch (BadMethodCallException $e) {
                throw RelationNotFoundException::make($this->getModel(), $name);
            }
        });

        $nested = $this->relationsNestedUnder($name);

        // If there are nested relationships set on the query, we will put those onto
        // the query instances so that they can be handled after this relationship
        // is loaded. In this way they will all trickle down as they are loaded.
        if (count($nested) > 0) {
            $relation->getQuery()->with($nested);
        }

        return $relation;
    }

    /**
     * Get the deeply nested relations for a given top-level relation.
     */
    protected function relationsNestedUnder(string $relation): array
    {
        $nested = [];

        // We are basically looking for any relationships that are nested deeper than
        // the given top-level relationship. We will just check for any relations
        // that start with the given top relations and add them to our arrays.
        foreach ($this->eagerLoad as $name => $constraints) {
            if ($this->isNestedUnder($relation, $name)) {
                $nested[substr($name, strlen($relation) + 1)] = $constraints;
            }
        }

        return $nested;
    }

    /**
     * Determine if the relationship is nested.
     */
    protected function isNestedUnder(string $relation, string $name): bool
    {
        return str_contains($name, '.') && Str::startsWith($name, $relation.'.');
    }

    /**
     * Get a paginator for the "select" statement.
     */
    public function paginate(?int $perPage = null, array $columns = ['*'], string $pageName = 'page', ?int $page = null): LengthAwarePaginator
    {
        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        $perPage = $perPage ?: $this->model->getPerPage();

        $results = ($total = $this->toBase()->getCountForPagination())
            ? $this->forPage($page, $perPage)->get($columns)
            : $this->model->newCollection();

        return $this->paginator($results, $total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ]);
    }

    /**
     * Paginate the given query into a simple paginator.
     */
    public function simplePaginate(?int $perPage = null, array $columns = ['*'], string $pageName = 'page', ?int $page = null): Paginator
    {
        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        $perPage = $perPage ?: $this->model->getPerPage();

        // Next we will set the limit and offset for this query so that when we get the
        // results we get the proper section of results. Then, we'll create the full
        // paginator instances for these results with the given page and per page.
        $this->offset(($page - 1) * $perPage)->limit($perPage + 1);

        return $this->simplePaginator($this->get($columns), $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ]);
    }

    /**
     * Save a new model and return the instance.
     */
    public function create(array $attributes = []): Model
    {
        return tap($this->newModelInstance($attributes), function ($instance) {
            $instance->save();
        });
    }

    /**
     * Create or update a record matching the attributes, and fill it with values.
     */
    public function updateOrCreate(array $attributes, array $values = []): Model
    {
        return tap($this->firstOrNew($attributes), function ($instance) use ($values) {
            $instance->fill($values)->save();
        });
    }

    /**
     * Get the first record matching the attributes or create it.
     */
    public function firstOrCreate(array $attributes = [], array $values = []): Model
    {
        if (!is_null($instance = $this->where($attributes)->first())) {
            return $instance;
        }

        return $this->newModelInstance(array_merge($attributes, $values))->save();
    }

    /**
     * Get the first record matching the attributes or instantiate it.
     */
    public function firstOrNew(array $attributes = [], array $values = []): Model
    {
        if (!is_null($instance = $this->where($attributes)->first())) {
            return $instance;
        }

        return $this->newModelInstance(array_merge($attributes, $values));
    }

    /**
     * Create a new instance of the model being queried.
     */
    public function newModelInstance(array $attributes = []): Model
    {
        return $this->model->newInstance($attributes)->setConnection(
            $this->query->getConnection()->getName()
        );
    }

    /**
     * Apply the scopes to the Eloquent builder instance and return it.
     */
    public function applyScopes(): static
    {
        if (!$this->scopes) {
            return $this;
        }

        $builder = clone $this;

        foreach ($this->scopes as $identifier => $scope) {
            if (!isset($builder->scopes[$identifier])) {
                continue;
            }

            $builder->callScope(function (Builder $builder) use ($scope) {
                // If the scope is a Closure we will call the scope with the builder instance
                // and our model instance so it is able to run any logic that is needed.
                // This allows the developers to do really complex logic inside here.
                if ($scope instanceof Closure) {
                    $scope($builder, $this->getModel());
                }
            });
        }

        return $builder;
    }

    /**
     * Apply the given scope on the current builder instance.
     */
    protected function callScope(callable $scope, array $parameters = []): mixed
    {
        array_unshift($parameters, $this);

        $query = $this->getQuery();

        // We will keep track of how many wheres are on the query before running the
        // scope so that we can properly group the added scope constraints in the
        // query as their own isolated nested where statement and avoid issues.
        $originalWhereCount = is_null($query->wheres)
            ? 0 : count($query->wheres);

        $result = $scope(...$parameters) ?? $this;

        if (count((array) $query->wheres) > $originalWhereCount) {
            $this->addNewWheresWithinGroup($query, $originalWhereCount);
        }

        return $result;
    }

    /**
     * Nest where conditions by adding grouping Closure.
     */
    protected function addNewWheresWithinGroup(QueryBuilder $query, int $originalWhereCount): void
    {
        // Here, we totally remove the new where clauses so we can put them in a
        // proper group and re-add them to the query as a nested where clause.
        // This allows proper boolean logic with nested orWhere combinations.
        $allWheres = $query->wheres;

        $query->wheres = [];

        $this->groupWhereSliceForScope(
            $query, array_slice($allWheres, 0, $originalWhereCount)
        );

        $this->groupWhereSliceForScope(
            $query, array_slice($allWheres, $originalWhereCount)
        );
    }

    /**
     * Slice the where conditions and add them to the query as a nested where group.
     */
    protected function groupWhereSliceForScope(QueryBuilder $query, array $whereSlice): void
    {
        $whereCount = count($whereSlice);

        if ($whereCount === 1 && $whereSlice[0]['type'] === 'Nested') {
            $query->addNestedWhereQuery($whereSlice[0]['query'], $whereSlice[0]['boolean']);
        } elseif ($whereCount > 0) {
            $query->where(function ($query) use ($whereSlice) {
                foreach ($whereSlice as $where) {
                    $query->wheres[] = $where;
                }
            });
        }
    }

    /**
     * Parse a list of relations into individuals.
     */
    protected function parseWithRelations(array $relations): array
    {
        $results = [];

        foreach ($relations as $name => $constraints) {
            // If the "constraints" are actually just a string, we can assume that
            // no constraints have been specified. We'll just put an empty Closure
            // there, so that we can treat all the relations the same way.
            if (is_numeric($name)) {
                $name = $constraints;

                [$name, $constraints] = Str::contains($name, ':')
                    ? $this->createSelectWithConstraint($name)
                    : [$name, static function () {
                        //
                    }];
            }

            // We need to separate out any nested includes, which allows the developers
            // to load deep relationships using "dots" without stating each level of
            // the relationship with a closure, which is much more convenient.
            $results = $this->addNestedWiths($name, $results);

            $results[$name] = $constraints;
        }

        return $results;
    }

    /**
     * Create a constraint to select the given columns for the relation.
     */
    protected function createSelectWithConstraint(string $name): array
    {
        [$name, $columns] = explode(':', $name, 2);

        return [$name, static function ($query) use ($columns) {
            $query->select(array_map('trim', explode(',', $columns)));
        }];
    }

    /**
     * Parse the nested relationships in a relation.
     */
    protected function addNestedWiths(string $name, array $results): array
    {
        $progress = [];

        // If the relation has already been set on the result array, we will not set it
        // again, since that would override any constraints that were already placed
        // on the relationships. We will only set the ones that are not specified.
        foreach (explode('.', $name) as $segment) {
            $progress[] = $segment;

            if (!isset($results[$last = implode('.', $progress)])) {
                $results[$last] = static function () {
                    //
                };
            }
        }

        return $results;
    }

    /**
     * Get the underlying query builder instance.
     */
    public function getQuery(): QueryBuilder
    {
        return $this->query;
    }

    /**
     * Set the underlying query builder instance.
     */
    public function setQuery(QueryBuilder $query): static
    {
        $this->query = $query;

        return $this;
    }

    /**
     * Get a base query builder instance.
     */
    public function toBase(): QueryBuilder
    {
        return $this->applyScopes()->getQuery();
    }

    /**
     * Get the model instance being queried.
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * Set a model instance for the model being queried.
     */
    public function setModel(Model $model): static
    {
        $this->model = $model;

        $this->query->from($model->getTable());

        return $this;
    }

    /**
     * Get the given macro by name.
     */
    public function getMacro(string $name): Closure
    {
        return $this->localMacros[$name];
    }

    /**
     * Checks if a macro is registered.
     */
    public function hasMacro(string $name): bool
    {
        return isset($this->localMacros[$name]) || isset(static::$macros[$name]);
    }

    /**
     * Dynamically handle calls into the query instance.
     */
    public function __call(string $method, array $parameters)
    {
        if ($method === 'macro') {
            $this->localMacros[$parameters[0]] = $parameters[1];

            return;
        }

        if (isset($this->localMacros[$method])) {
            array_unshift($parameters, $this);

            return $this->localMacros[$method](...$parameters);
        }

        if (isset(static::$macros[$method])) {
            if (static::$macros[$method] instanceof Closure) {
                return call_user_func_array(static::$macros[$method]->bindTo($this, static::class), $parameters);
            }

            return static::$macros[$method](...$parameters);
        }

        if (method_exists($this->model, $scope = 'scope' . ucfirst($method))) {
            return $this->callScope([$this->model, $scope], $parameters);
        }

        if (in_array($method, $this->passthru)) {
            return $this->toBase()->{$method}(...$parameters);
        }

        $this->forwardCallTo($this->query, $method, $parameters);

        return $this;
    }

    /**
     * Dynamically handle calls into the query instance.
     */
    public static function __callStatic(string $method, array $parameters)
    {
        if ($method === 'macro') {
            static::$macros[$parameters[0]] = $parameters[1];

            return;
        }

        if (!isset(static::$macros[$method])) {
            throw new BadMethodCallException(sprintf(
                'Method %s::%s does not exist.', static::class, $method
            ));
        }

        if (static::$macros[$method] instanceof Closure) {
            return call_user_func_array(Closure::bind(static::$macros[$method], null, static::class), $parameters);
        }

        return static::$macros[$method](...$parameters);
    }

    /**
     * Force a clone of the underlying query builder when cloning.
     */
    public function __clone()
    {
        $this->query = clone $this->query;
    }
}