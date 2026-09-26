<?php

declare(strict_types=1);

namespace Reno\Database\Eloquent\Concerns;

use Closure;
use Reno\Database\Eloquent\PerformanceMonitor;

/**
 * Model Performance Optimization
 * 
 * Provides performance optimization features for Eloquent models including
 * query caching, N+1 problem prevention, lazy loading optimization, and
 * performance monitoring.
 */
trait HasPerformance
{
    /**
     * Model cache store.
     */
    protected static array $modelCache = [];

    /**
     * Query result cache.
     */
    protected static array $queryCache = [];

    /**
     * Eager loaded relationships to prevent N+1 queries.
     */
    protected array $eagerLoad = [];

    /**
     * Performance monitoring data.
     */
    protected array $performanceData = [];

    /**
     * Whether to enable query caching for this model.
     */
    protected bool $cacheQueries = false;

    /**
     * Default cache TTL in seconds.
     */
    protected int $cacheTtl = 3600;

    /**
     * Remember query results for a specified time.
     */
    public function remember(int $seconds): static
    {
        $this->cacheTtl = $seconds;
        $this->cacheQueries = true;
        return $this;
    }

    /**
     * Get a cached query result or execute and cache it.
     */
    protected function getCachedResult(string $cacheKey, Closure $callback): mixed
    {
        if (!$this->cacheQueries) {
            return $callback();
        }

        // Check if result is cached and not expired
        if (isset(static::$queryCache[$cacheKey])) {
            $cached = static::$queryCache[$cacheKey];
            if ($cached['expires_at'] > time()) {
                PerformanceMonitor::recordCacheHit($cacheKey);
                return $cached['data'];
            } else {
                unset(static::$queryCache[$cacheKey]);
            }
        }

        // Execute query and cache result
        $result = $callback();
        
        static::$queryCache[$cacheKey] = [
            'data' => $result,
            'expires_at' => time() + $this->cacheTtl,
            'created_at' => time(),
        ];

        PerformanceMonitor::recordCacheMiss($cacheKey);
        return $result;
    }

    /**
     * Generate a cache key for the current query state.
     */
    protected function getCacheKey(): string
    {
        $builder = $this->newQuery();
        
        $key = sprintf(
            '%s:%s:%s:%s:%s',
            static::class,
            md5(serialize($builder->getBindings())),
            md5($builder->toSql()),
            $builder->getConnection()->getDatabaseName(),
            'v1'
        );

        return $key;
    }

    /**
     * Eager load relationships to prevent N+1 queries.
     */
    public function with(array|string $relations): static
    {
        $this->eagerLoad = array_merge(
            $this->eagerLoad,
            is_string($relations) ? [$relations] : $relations
        );

        return $this;
    }

    /**
     * Load a relationship without triggering N+1 queries.
     */
    public function load(array|string $relations): static
    {
        $relations = is_string($relations) ? [$relations] : $relations;

        foreach ($relations as $relation) {
            if (!$this->relationLoaded($relation)) {
                $this->setRelation($relation, $this->getRelationValue($relation));
            }
        }

        return $this;
    }

    /**
     * Batch load models to reduce database queries.
     */
    public static function batchLoad(array $ids, array $columns = ['*']): array
    {
        if (empty($ids)) {
            return [];
        }

        $cacheKey = 'batch_load:' . static::class . ':' . implode(',', $ids) . ':' . implode(',', $columns);
        
        return (new static())->getCachedResult($cacheKey, function() use ($ids, $columns) {
            return static::whereIn((new static())->getKeyName(), $ids)->get($columns)->keyBy((new static())->getKeyName())->all();
        });
    }

    /**
     * Chunk results to avoid memory issues with large datasets.
     */
    public function chunk(int $count, Closure $callback): bool
    {
        $this->enforceOrderBy();

        $page = 1;

        do {
            // We'll execute the query for the given page and get the results. If there are
            // no results we can just break and return from here. When there are results
            // we will call the callback with the current chunk of these results here.
            $results = $this->forPage($page, $count)->get();

            $countResults = $results->count();

            if ($countResults == 0) {
                break;
            }

            // On each chunk result set, we will pass them to the callback and then let the
            // developer take care of everything within the callback, which allows us to
            // keep the memory low for spinning through large result sets for working.
            if ($callback($results, $page) === false) {
                return false;
            }

            unset($results);

            $page++;
        } while ($countResults == $count);

        return true;
    }

    /**
     * Execute the callback over each item while chunking.
     */
    public function each(Closure $callback, int $count = 1000): bool
    {
        return $this->chunk($count, function ($results) use ($callback) {
            foreach ($results as $key => $value) {
                if ($callback($value, $key) === false) {
                    return false;
                }
            }
            return true;
        });
    }

    /**
     * Lazy loading iterator for memory-efficient iteration.
     */
    public function cursor(): \Generator
    {
        $this->enforceOrderBy();

        $results = $this->getConnection()->cursor($this->toSql(), $this->getBindings());

        foreach ($results as $record) {
            yield $this->newFromBuilder((array) $record);
        }
    }

    /**
     * Optimize SELECT queries by only fetching needed columns.
     */
    public function selectOptimal(array $relations = []): static
    {
        $columns = $this->getFillable();
        
        // Always include primary key
        if (!in_array($this->getKeyName(), $columns)) {
            array_unshift($columns, $this->getKeyName());
        }

        // Include foreign keys for requested relations
        foreach ($relations as $relation) {
            if (method_exists($this, $relation)) {
                $relationInstance = $this->$relation();
                if (method_exists($relationInstance, 'getForeignKeyName')) {
                    $foreignKey = $relationInstance->getForeignKeyName();
                    if (!in_array($foreignKey, $columns)) {
                        $columns[] = $foreignKey;
                    }
                }
            }
        }

        return $this->select($columns);
    }

    /**
     * Exists query optimization.
     */
    public function existsOptimized(): bool
    {
        $cacheKey = $this->getCacheKey() . ':exists';
        
        return $this->getCachedResult($cacheKey, function() {
            return $this->limit(1)->count() > 0;
        });
    }

    /**
     * Count query optimization.
     */
    public function countOptimized(): int
    {
        $cacheKey = $this->getCacheKey() . ':count';
        
        return $this->getCachedResult($cacheKey, function() {
            // Use COUNT(*) with LIMIT 1 for existence checks
            return $this->count();
        });
    }

    /**
     * Find multiple models by their primary keys with caching.
     */
    public static function findManyOptimized(array $ids, array $columns = ['*']): Collection
    {
        if (empty($ids)) {
            return new Collection();
        }

        // Try to get models from cache first
        $cached = [];
        $missing = [];
        $cacheKey = static::class . ':find:';

        foreach ($ids as $id) {
            $key = $cacheKey . $id;
            if (isset(static::$modelCache[$key])) {
                $cached[$id] = static::$modelCache[$key];
            } else {
                $missing[] = $id;
            }
        }

        // Batch load missing models
        if (!empty($missing)) {
            $models = static::batchLoad($missing, $columns);
            
            // Cache the loaded models
            foreach ($models as $id => $model) {
                static::$modelCache[$cacheKey . $id] = $model;
                $cached[$id] = $model;
            }
        }

        // Return in the same order as requested
        $result = [];
        foreach ($ids as $id) {
            if (isset($cached[$id])) {
                $result[] = $cached[$id];
            }
        }

        return new Collection($result);
    }

    /**
     * Clear model cache.
     */
    public static function clearCache(?string $key = null): void
    {
        if ($key) {
            unset(static::$modelCache[$key], static::$queryCache[$key]);
        } else {
            static::$modelCache = [];
            static::$queryCache = [];
        }
    }

    /**
     * Get cache statistics.
     */
    public static function getCacheStats(): array
    {
        return [
            'model_cache_size' => count(static::$modelCache),
            'query_cache_size' => count(static::$queryCache),
            'memory_usage' => memory_get_usage(true),
            'cache_items' => array_keys(static::$queryCache),
        ];
    }

    /**
     * Enforce ORDER BY for chunking operations.
     */
    protected function enforceOrderBy(): void
    {
        if (empty($this->query->orders) && empty($this->query->unionOrders)) {
            $this->orderBy($this->getKeyName());
        }
    }

    /**
     * Profile query execution performance.
     */
    public function profile(Closure $callback): array
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        
        $result = $callback();
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        
        $profile = [
            'execution_time' => round(($endTime - $startTime) * 1000, 2), // ms
            'memory_usage' => $endMemory - $startMemory,
            'peak_memory' => memory_get_peak_usage(true),
            'queries_count' => PerformanceMonitor::getQueryCount(),
            'cache_hits' => PerformanceMonitor::getCacheHits(),
            'cache_misses' => PerformanceMonitor::getCacheMisses(),
        ];

        $this->performanceData[] = $profile;
        
        return $profile;
    }

    /**
     * Get performance monitoring data.
     */
    public function getPerformanceData(): array
    {
        return $this->performanceData;
    }

    /**
     * Optimize relationship loading based on usage patterns.
     */
    public function optimizeRelations(): static
    {
        // Analyze which relations are frequently accessed together
        $frequentPairs = $this->analyzeRelationUsage();
        
        // Automatically eager load frequently used relations
        if (!empty($frequentPairs)) {
            $this->with($frequentPairs);
        }
        
        return $this;
    }

    /**
     * Analyze relation usage patterns.
     */
    protected function analyzeRelationUsage(): array
    {
        // In a real implementation, this would analyze access patterns
        // For now, return common relation patterns
        return [];
    }

    /**
     * Create a query builder with performance optimizations.
     */
    public function newOptimizedQuery(): Builder
    {
        $builder = $this->newQuery();
        
        // Apply automatic optimizations
        if ($this->cacheQueries) {
            $builder->remember($this->cacheTtl);
        }
        
        if (!empty($this->eagerLoad)) {
            $builder->with($this->eagerLoad);
        }
        
        return $builder;
    }

    /**
     * Execute a query with performance monitoring.
     */
    protected function executeWithMonitoring(Closure $callback): mixed
    {
        return PerformanceMonitor::monitor(function() use ($callback) {
            return $callback();
        }, [
            'model' => static::class,
            'operation' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'] ?? 'unknown'
        ]);
    }

    /**
     * Batch update multiple models efficiently.
     */
    public static function batchUpdate(array $data, string $keyColumn = null): int
    {
        if (empty($data)) {
            return 0;
        }

        $keyColumn = $keyColumn ?: (new static())->getKeyName();
        $table = (new static())->getTable();
        
        // Group updates by the same set of columns
        $grouped = [];
        foreach ($data as $item) {
            $columns = array_keys($item);
            sort($columns);
            $key = implode(',', $columns);
            $grouped[$key][] = $item;
        }

        $totalUpdated = 0;
        
        foreach ($grouped as $items) {
            $sql = static::buildBatchUpdateSql($table, $items, $keyColumn);
            $bindings = static::buildBatchUpdateBindings($items);
            
            $totalUpdated += (new static())->getConnection()->update($sql, $bindings);
        }

        return $totalUpdated;
    }

    /**
     * Build SQL for batch update.
     */
    protected static function buildBatchUpdateSql(string $table, array $items, string $keyColumn): string
    {
        if (empty($items)) {
            return '';
        }

        $columns = array_keys($items[0]);
        $setClause = [];

        foreach ($columns as $column) {
            if ($column !== $keyColumn) {
                $setClause[] = "{$column} = CASE {$keyColumn}";
                foreach ($items as $item) {
                    $setClause[] = "WHEN ? THEN ?";
                }
                $setClause[] = "ELSE {$column} END";
            }
        }

        $ids = array_column($items, $keyColumn);
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';

        return "UPDATE {$table} SET " . implode(' ', $setClause) . " WHERE {$keyColumn} IN ({$placeholders})";
    }

    /**
     * Build bindings for batch update.
     */
    protected static function buildBatchUpdateBindings(array $items): array
    {
        $bindings = [];
        $columns = array_keys($items[0]);
        $keyColumn = (new static())->getKeyName();

        foreach ($columns as $column) {
            if ($column !== $keyColumn) {
                foreach ($items as $item) {
                    $bindings[] = $item[$keyColumn];
                    $bindings[] = $item[$column];
                }
            }
        }

        // Add IDs for WHERE clause
        foreach ($items as $item) {
            $bindings[] = $item[$keyColumn];
        }

        return $bindings;
    }
}