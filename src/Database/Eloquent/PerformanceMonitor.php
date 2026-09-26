<?php

declare(strict_types=1);

namespace Reno\Database\Eloquent;

use Closure;

/**
 * Performance Monitor
 * 
 * Monitors and tracks ORM performance metrics including query execution times,
 * memory usage, cache hits/misses, and N+1 query detection.
 */
class PerformanceMonitor
{
    /**
     * Query execution log.
     */
    protected static array $queryLog = [];

    /**
     * Performance metrics.
     */
    protected static array $metrics = [
        'total_queries' => 0,
        'total_execution_time' => 0,
        'cache_hits' => 0,
        'cache_misses' => 0,
        'n_plus_one_detected' => 0,
        'memory_usage' => 0,
    ];

    /**
     * Slow query threshold in milliseconds.
     */
    protected static float $slowQueryThreshold = 100.0;

    /**
     * N+1 query detection patterns.
     */
    protected static array $queryPatterns = [];

    /**
     * Performance monitoring enabled flag.
     */
    protected static bool $enabled = true;

    /**
     * Start monitoring a database operation.
     */
    public static function monitor(Closure $callback, array $context = []): mixed
    {
        if (!static::$enabled) {
            return $callback();
        }

        $startTime = microtime(true);
        $startMemory = memory_get_usage();
        $startQueries = static::$metrics['total_queries'];

        try {
            $result = $callback();
            
            $endTime = microtime(true);
            $endMemory = memory_get_usage();
            $endQueries = static::$metrics['total_queries'];
            
            $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
            $memoryUsed = $endMemory - $startMemory;
            $queriesExecuted = $endQueries - $startQueries;
            
            // Log performance data
            static::logPerformance([
                'execution_time' => $executionTime,
                'memory_usage' => $memoryUsed,
                'queries_count' => $queriesExecuted,
                'context' => $context,
                'timestamp' => time(),
            ]);

            // Check for slow operations
            if ($executionTime > static::$slowQueryThreshold) {
                static::logSlowOperation($executionTime, $context);
            }

            // Detect potential N+1 queries
            if ($queriesExecuted > 10) {
                static::detectNPlusOne($context, $queriesExecuted);
            }

            return $result;
            
        } catch (\Throwable $e) {
            static::logError($e, $context);
            throw $e;
        }
    }

    /**
     * Record a database query execution.
     */
    public static function recordQuery(string $sql, array $bindings = [], float $time = 0): void
    {
        if (!static::$enabled) {
            return;
        }

        static::$metrics['total_queries']++;
        static::$metrics['total_execution_time'] += $time;

        $query = [
            'sql' => $sql,
            'bindings' => $bindings,
            'execution_time' => $time,
            'timestamp' => microtime(true),
            'memory_usage' => memory_get_usage(),
        ];

        static::$queryLog[] = $query;

        // Detect potential patterns for N+1 queries
        static::analyzeQueryPattern($sql);

        // Check for slow queries
        if ($time > static::$slowQueryThreshold) {
            static::logSlowQuery($query);
        }

        // Limit query log size to prevent memory issues
        if (count(static::$queryLog) > 1000) {
            static::$queryLog = array_slice(static::$queryLog, -500);
        }
    }

    /**
     * Record a cache hit.
     */
    public static function recordCacheHit(string $key): void
    {
        if (!static::$enabled) {
            return;
        }

        static::$metrics['cache_hits']++;
    }

    /**
     * Record a cache miss.
     */
    public static function recordCacheMiss(string $key): void
    {
        if (!static::$enabled) {
            return;
        }

        static::$metrics['cache_misses']++;
    }

    /**
     * Get current performance metrics.
     */
    public static function getMetrics(): array
    {
        $cacheHitRate = 0;
        $totalCacheRequests = static::$metrics['cache_hits'] + static::$metrics['cache_misses'];
        
        if ($totalCacheRequests > 0) {
            $cacheHitRate = (static::$metrics['cache_hits'] / $totalCacheRequests) * 100;
        }

        return array_merge(static::$metrics, [
            'cache_hit_rate' => round($cacheHitRate, 2),
            'average_query_time' => static::getAverageQueryTime(),
            'slow_queries_count' => static::getSlowQueriesCount(),
            'current_memory_usage' => memory_get_usage(true),
            'peak_memory_usage' => memory_get_peak_usage(true),
        ]);
    }

    /**
     * Get the query log.
     */
    public static function getQueryLog(): array
    {
        return static::$queryLog;
    }

    /**
     * Get slow queries from the log.
     */
    public static function getSlowQueries(): array
    {
        return array_filter(static::$queryLog, function ($query) {
            return $query['execution_time'] > static::$slowQueryThreshold;
        });
    }

    /**
     * Get duplicate queries that might indicate N+1 problems.
     */
    public static function getDuplicateQueries(): array
    {
        $sqlCounts = [];
        $duplicates = [];

        foreach (static::$queryLog as $query) {
            $normalizedSql = static::normalizeSql($query['sql']);
            
            if (!isset($sqlCounts[$normalizedSql])) {
                $sqlCounts[$normalizedSql] = [];
            }
            
            $sqlCounts[$normalizedSql][] = $query;
        }

        foreach ($sqlCounts as $sql => $queries) {
            if (count($queries) > 5) { // More than 5 similar queries
                $duplicates[$sql] = [
                    'count' => count($queries),
                    'queries' => $queries,
                    'total_time' => array_sum(array_column($queries, 'execution_time')),
                ];
            }
        }

        return $duplicates;
    }

    /**
     * Reset all monitoring data.
     */
    public static function reset(): void
    {
        static::$queryLog = [];
        static::$queryPatterns = [];
        static::$metrics = [
            'total_queries' => 0,
            'total_execution_time' => 0,
            'cache_hits' => 0,
            'cache_misses' => 0,
            'n_plus_one_detected' => 0,
            'memory_usage' => 0,
        ];
    }

    /**
     * Enable or disable performance monitoring.
     */
    public static function enabled(bool $enabled = true): void
    {
        static::$enabled = $enabled;
    }

    /**
     * Set the slow query threshold.
     */
    public static function setSlowQueryThreshold(float $milliseconds): void
    {
        static::$slowQueryThreshold = $milliseconds;
    }

    /**
     * Get query count.
     */
    public static function getQueryCount(): int
    {
        return static::$metrics['total_queries'];
    }

    /**
     * Get cache hits count.
     */
    public static function getCacheHits(): int
    {
        return static::$metrics['cache_hits'];
    }

    /**
     * Get cache misses count.
     */
    public static function getCacheMisses(): int
    {
        return static::$metrics['cache_misses'];
    }

    /**
     * Get performance report.
     */
    public static function getReport(): array
    {
        $metrics = static::getMetrics();
        $slowQueries = static::getSlowQueries();
        $duplicates = static::getDuplicateQueries();

        return [
            'summary' => $metrics,
            'slow_queries' => array_slice($slowQueries, -10), // Last 10 slow queries
            'duplicate_queries' => $duplicates,
            'recommendations' => static::generateRecommendations($metrics, $duplicates),
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Generate performance recommendations.
     */
    protected static function generateRecommendations(array $metrics, array $duplicates): array
    {
        $recommendations = [];

        // Cache hit rate recommendations
        if ($metrics['cache_hit_rate'] < 70) {
            $recommendations[] = [
                'type' => 'cache',
                'priority' => 'high',
                'message' => 'Low cache hit rate (' . $metrics['cache_hit_rate'] . '%). Consider implementing more aggressive caching.',
            ];
        }

        // Slow query recommendations
        if ($metrics['slow_queries_count'] > 0) {
            $recommendations[] = [
                'type' => 'queries',
                'priority' => 'high',
                'message' => 'Found ' . $metrics['slow_queries_count'] . ' slow queries. Consider optimizing these queries or adding indexes.',
            ];
        }

        // N+1 query recommendations
        if (!empty($duplicates)) {
            $recommendations[] = [
                'type' => 'n_plus_one',
                'priority' => 'medium',
                'message' => 'Detected potential N+1 query problems. Use eager loading to reduce query count.',
            ];
        }

        // Memory usage recommendations
        if ($metrics['peak_memory_usage'] > 128 * 1024 * 1024) { // 128MB
            $recommendations[] = [
                'type' => 'memory',
                'priority' => 'medium',
                'message' => 'High memory usage detected. Consider using chunking for large datasets.',
            ];
        }

        return $recommendations;
    }

    /**
     * Log performance data.
     */
    protected static function logPerformance(array $data): void
    {
        // In a real implementation, this could log to a file or monitoring service
        // For now, we just track in memory
    }

    /**
     * Log slow operation.
     */
    protected static function logSlowOperation(float $time, array $context): void
    {
        // Log slow operations for further analysis
    }

    /**
     * Log slow query.
     */
    protected static function logSlowQuery(array $query): void
    {
        // Mark query as slow for reporting
        $query['is_slow'] = true;
    }

    /**
     * Log error during monitoring.
     */
    protected static function logError(\Throwable $e, array $context): void
    {
        // Log monitoring errors
    }

    /**
     * Analyze query pattern for N+1 detection.
     */
    protected static function analyzeQueryPattern(string $sql): void
    {
        $normalizedSql = static::normalizeSql($sql);
        
        if (!isset(static::$queryPatterns[$normalizedSql])) {
            static::$queryPatterns[$normalizedSql] = 0;
        }
        
        static::$queryPatterns[$normalizedSql]++;
    }

    /**
     * Detect N+1 query pattern.
     */
    protected static function detectNPlusOne(array $context, int $queryCount): void
    {
        static::$metrics['n_plus_one_detected']++;
        
        // In a real implementation, this could trigger alerts
    }

    /**
     * Normalize SQL for pattern matching.
     */
    protected static function normalizeSql(string $sql): string
    {
        // Remove specific values to identify patterns
        $sql = preg_replace('/\d+/', '?', $sql);
        $sql = preg_replace("/'[^']*'/", '?', $sql);
        $sql = preg_replace('/"[^"]*"/', '?', $sql);
        $sql = preg_replace('/\s+/', ' ', trim($sql));
        
        return strtolower($sql);
    }

    /**
     * Get average query execution time.
     */
    protected static function getAverageQueryTime(): float
    {
        if (static::$metrics['total_queries'] === 0) {
            return 0;
        }
        
        return round(static::$metrics['total_execution_time'] / static::$metrics['total_queries'], 2);
    }

    /**
     * Get slow queries count.
     */
    protected static function getSlowQueriesCount(): int
    {
        return count(static::getSlowQueries());
    }

    /**
     * Export monitoring data for external analysis.
     */
    public static function export(): array
    {
        return [
            'metrics' => static::getMetrics(),
            'query_log' => static::$queryLog,
            'query_patterns' => static::$queryPatterns,
            'export_timestamp' => time(),
        ];
    }

    /**
     * Import monitoring data from external source.
     */
    public static function import(array $data): void
    {
        if (isset($data['metrics'])) {
            static::$metrics = array_merge(static::$metrics, $data['metrics']);
        }
        
        if (isset($data['query_log'])) {
            static::$queryLog = array_merge(static::$queryLog, $data['query_log']);
        }
        
        if (isset($data['query_patterns'])) {
            static::$queryPatterns = array_merge(static::$queryPatterns, $data['query_patterns']);
        }
    }
}