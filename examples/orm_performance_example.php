<?php

declare(strict_types=1);

/**
 * ORM Performance Optimizations Example
 * 
 * Demonstrates the Horizon Framework's comprehensive ORM performance features
 * including query caching, eager loading, N+1 prevention, connection pooling,
 * and performance monitoring.
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "Horizon Framework - ORM Performance Optimizations Example\n";
echo "=========================================================\n\n";

use Horizon\Database\Eloquent\Model;
use Horizon\Database\Eloquent\PerformanceMonitor;
use Horizon\Database\Eloquent\Concerns\HasPerformance;
use Horizon\Database\ConnectionPool;
use Horizon\Support\Collection;

// Mock optimized User model for demonstration
class OptimizedUser extends Model
{
    use HasPerformance;
    
    protected ?string $table = 'users';
    
    protected array $fillable = [
        'name', 'email', 'bio', 'status'
    ];
    
    // Enable query caching
    protected bool $cacheQueries = true;
    protected int $cacheTtl = 3600;
    
    // Optimize eager loading
    protected array $with = ['profile'];
    
    public function posts()
    {
        return $this->hasMany(OptimizedPost::class, 'user_id');
    }
    
    public function profile()
    {
        return $this->hasOne(UserProfile::class, 'user_id');
    }
    
    public function comments()
    {
        return $this->hasMany(Comment::class, 'user_id');
    }
}

class OptimizedPost extends Model
{
    use HasPerformance;
    
    protected ?string $table = 'posts';
    protected bool $cacheQueries = true;
    
    public function user()
    {
        return $this->belongsTo(OptimizedUser::class, 'user_id');
    }
    
    public function comments()
    {
        return $this->hasMany(Comment::class, 'post_id');
    }
    
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'post_tags', 'post_id', 'tag_id');
    }
}

class UserProfile extends Model
{
    protected ?string $table = 'user_profiles';
    
    public function user()
    {
        return $this->belongsTo(OptimizedUser::class, 'user_id');
    }
}

class Comment extends Model
{
    protected ?string $table = 'comments';
    
    public function user()
    {
        return $this->belongsTo(OptimizedUser::class, 'user_id');
    }
    
    public function post()
    {
        return $this->belongsTo(OptimizedPost::class, 'post_id');
    }
}

class Tag extends Model
{
    protected ?string $table = 'tags';
    
    public function posts()
    {
        return $this->belongsToMany(OptimizedPost::class, 'post_tags', 'tag_id', 'post_id');
    }
}

try {
    echo "1. PERFORMANCE MONITORING\n";
    echo "-------------------------\n";

    // Enable performance monitoring
    PerformanceMonitor::enabled(true);
    PerformanceMonitor::setSlowQueryThreshold(50.0); // 50ms threshold

    // Reset metrics for clean demonstration
    PerformanceMonitor::reset();

    echo "✓ Performance monitoring enabled\n";
    echo "✓ Slow query threshold: 50ms\n\n";

    echo "2. QUERY CACHING DEMONSTRATION\n";
    echo "------------------------------\n";

    $user = new OptimizedUser();

    // Simulate first query (cache miss)
    echo "First query execution (cache miss):\n";
    $result1 = $user->profile(function() {
        PerformanceMonitor::recordQuery(
            'SELECT * FROM users WHERE status = ? LIMIT 10',
            ['active'],
            25.5
        );
        
        // Simulate query result
        return collect([
            ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com'],
            ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com'],
        ]);
    });
    
    echo "  Execution time: 25.5ms\n";
    echo "  Result: " . count($result1) . " records\n\n";

    // Simulate second query (cache hit)
    echo "Second identical query (cache hit):\n";
    $cacheKey = 'users:active:limit:10';
    PerformanceMonitor::recordCacheHit($cacheKey);
    
    echo "  Execution time: 0.1ms (from cache)\n";
    echo "  Result: Same 2 records (cached)\n\n";

    echo "Cache statistics:\n";
    $stats = PerformanceMonitor::getMetrics();
    echo "  Cache hits: " . $stats['cache_hits'] . "\n";
    echo "  Cache misses: " . $stats['cache_misses'] . "\n";
    echo "  Hit rate: " . $stats['cache_hit_rate'] . "%\n\n";

    echo "3. EAGER LOADING vs N+1 QUERIES\n";
    echo "--------------------------------\n";

    echo "❌ BAD: N+1 Query Problem\n";
    
    // Simulate N+1 queries
    echo "Loading 5 users and their posts individually:\n";
    for ($i = 1; $i <= 5; $i++) {
        PerformanceMonitor::recordQuery(
            'SELECT * FROM users WHERE id = ?',
            [$i],
            15.0
        );
        
        PerformanceMonitor::recordQuery(
            'SELECT * FROM posts WHERE user_id = ?',
            [$i],
            12.0
        );
        
        echo "  User {$i}: 2 queries (27ms total)\n";
    }
    
    $badTotalTime = (15.0 + 12.0) * 5;
    echo "  Total: 10 queries, {$badTotalTime}ms\n\n";

    echo "✅ GOOD: Eager Loading\n";
    
    // Simulate eager loading
    echo "Loading 5 users with posts using eager loading:\n";
    PerformanceMonitor::recordQuery(
        'SELECT * FROM users WHERE id IN (?, ?, ?, ?, ?)',
        [1, 2, 3, 4, 5],
        20.0
    );
    
    PerformanceMonitor::recordQuery(
        'SELECT * FROM posts WHERE user_id IN (?, ?, ?, ?, ?)',
        [1, 2, 3, 4, 5],
        18.0
    );
    
    $goodTotalTime = 20.0 + 18.0;
    echo "  Total: 2 queries, {$goodTotalTime}ms\n";
    echo "  Performance gain: " . round(($badTotalTime - $goodTotalTime) / $badTotalTime * 100, 1) . "%\n\n";

    echo "4. BATCH OPERATIONS\n";
    echo "-------------------\n";

    echo "Batch loading multiple users:\n";
    
    // Simulate batch loading
    $userIds = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
    echo "Loading " . count($userIds) . " users in batch:\n";
    
    PerformanceMonitor::recordQuery(
        'SELECT * FROM users WHERE id IN (' . str_repeat('?,', count($userIds) - 1) . '?)',
        $userIds,
        30.0
    );
    
    echo "  Single query: 30ms\n";
    echo "  vs Individual queries: " . (15.0 * count($userIds)) . "ms\n";
    echo "  Efficiency: " . round((1 - 30.0 / (15.0 * count($userIds))) * 100, 1) . "% faster\n\n";

    echo "Batch updates:\n";
    $updateData = [
        ['id' => 1, 'status' => 'active', 'updated_at' => date('Y-m-d H:i:s')],
        ['id' => 2, 'status' => 'inactive', 'updated_at' => date('Y-m-d H:i:s')],
        ['id' => 3, 'status' => 'pending', 'updated_at' => date('Y-m-d H:i:s')],
    ];
    
    // Simulate batch update
    PerformanceMonitor::recordQuery(
        'UPDATE users SET status = CASE id WHEN ? THEN ? WHEN ? THEN ? WHEN ? THEN ? END WHERE id IN (?,?,?)',
        [1, 'active', 2, 'inactive', 3, 'pending', 1, 2, 3],
        25.0
    );
    
    echo "  Batch update: 1 query, 25ms\n";
    echo "  vs Individual updates: 3 queries, " . (8.0 * 3) . "ms\n\n";

    echo "5. CHUNKING LARGE DATASETS\n";
    echo "--------------------------\n";

    echo "Processing 10,000 records with chunking:\n";
    
    $totalRecords = 10000;
    $chunkSize = 1000;
    $chunks = ceil($totalRecords / $chunkSize);
    
    $totalTime = 0;
    for ($i = 0; $i < $chunks; $i++) {
        $offset = $i * $chunkSize;
        $queryTime = 45.0; // Simulate consistent chunk processing time
        
        PerformanceMonitor::recordQuery(
            'SELECT * FROM users LIMIT ? OFFSET ?',
            [$chunkSize, $offset],
            $queryTime
        );
        
        $totalTime += $queryTime;
        
        echo "  Chunk " . ($i + 1) . "/{$chunks}: {$queryTime}ms\n";
    }
    
    echo "  Total time: {$totalTime}ms\n";
    echo "  Memory usage: Consistent (chunked loading)\n";
    echo "  vs Loading all at once: ~500ms + high memory usage\n\n";

    echo "6. CONNECTION POOLING\n";
    echo "---------------------\n";

    // Simulate connection pool
    $poolConfig = [
        'max_connections' => 5,
        'min_connections' => 2,
        'timeout' => 30,
        'connections' => [
            'default' => [
                'driver' => 'mysql',
                'host' => 'localhost',
                'database' => 'horizon_app',
                'username' => 'user',
                'password' => 'password',
            ]
        ]
    ];
    
    $pool = new ConnectionPool($poolConfig);
    
    echo "Connection pool configuration:\n";
    echo "  Max connections: 5\n";
    echo "  Min connections: 2\n";
    echo "  Timeout: 30 seconds\n\n";
    
    echo "Simulating connection usage:\n";
    
    // Warm up the pool
    try {
        $pool->warmUp();
        echo "  ✓ Pool warmed up with minimum connections\n";
    } catch (Exception $e) {
        echo "  ⚠️  Pool warmup simulated (connections would be created)\n";
    }
    
    $poolStats = $pool->getStats();
    echo "  Connections created: " . $poolStats['created'] . "\n";
    echo "  Connection efficiency: " . $poolStats['efficiency'] . "%\n\n";

    echo "7. QUERY OPTIMIZATION\n";
    echo "---------------------\n";

    echo "Optimized vs Unoptimized queries:\n\n";

    echo "❌ Unoptimized query:\n";
    PerformanceMonitor::recordQuery(
        'SELECT * FROM users u LEFT JOIN posts p ON u.id = p.user_id WHERE u.status = ? ORDER BY u.created_at',
        ['active'],
        150.0
    );
    echo "  Query: SELECT * FROM users (all columns)\n";
    echo "  Time: 150ms (slow - no index on status)\n\n";

    echo "✅ Optimized query:\n";
    PerformanceMonitor::recordQuery(
        'SELECT u.id, u.name, u.email FROM users u WHERE u.status = ? ORDER BY u.id LIMIT 20',
        ['active'],
        12.0
    );
    echo "  Query: SELECT specific columns with indexed WHERE\n";
    echo "  Time: 12ms (92% faster)\n\n";

    echo "Query optimization techniques applied:\n";
    echo "  ✓ SELECT only needed columns\n";
    echo "  ✓ Use indexed columns in WHERE clauses\n";
    echo "  ✓ Add LIMIT to prevent large result sets\n";
    echo "  ✓ Optimize ORDER BY with indexed columns\n\n";

    echo "8. PERFORMANCE ANALYSIS\n";
    echo "-----------------------\n";

    // Generate some slow queries for analysis
    PerformanceMonitor::recordQuery(
        'SELECT * FROM posts p LEFT JOIN comments c ON p.id = c.post_id WHERE p.created_at > ?',
        ['2024-01-01'],
        125.0 // Slow query
    );

    PerformanceMonitor::recordQuery(
        'UPDATE users SET last_seen_at = ? WHERE id = ?',
        [date('Y-m-d H:i:s'), 1],
        80.0 // Another slow query
    );

    $report = PerformanceMonitor::getReport();
    
    echo "Performance Report:\n\n";
    
    echo "Summary:\n";
    echo "  Total queries: " . $report['summary']['total_queries'] . "\n";
    echo "  Average query time: " . $report['summary']['average_query_time'] . "ms\n";
    echo "  Slow queries: " . $report['summary']['slow_queries_count'] . "\n";
    echo "  Cache hit rate: " . $report['summary']['cache_hit_rate'] . "%\n";
    echo "  Memory usage: " . round($report['summary']['current_memory_usage'] / 1024 / 1024, 2) . "MB\n\n";

    if (!empty($report['slow_queries'])) {
        echo "Slow Queries (>" . PerformanceMonitor::getSlowQueryThreshold() . "ms):\n";
        foreach (array_slice($report['slow_queries'], -3) as $i => $query) {
            echo "  " . ($i + 1) . ". " . substr($query['sql'], 0, 60) . "...\n";
            echo "     Time: " . $query['execution_time'] . "ms\n";
        }
        echo "\n";
    }

    if (!empty($report['duplicate_queries'])) {
        echo "Potential N+1 Queries:\n";
        foreach ($report['duplicate_queries'] as $sql => $info) {
            echo "  Pattern: " . substr($sql, 0, 60) . "...\n";
            echo "  Count: " . $info['count'] . " executions\n";
            echo "  Total time: " . $info['total_time'] . "ms\n";
        }
        echo "\n";
    }

    if (!empty($report['recommendations'])) {
        echo "Performance Recommendations:\n";
        foreach ($report['recommendations'] as $rec) {
            $icon = $rec['priority'] === 'high' ? '🔴' : ($rec['priority'] === 'medium' ? '🟡' : '🟢');
            echo "  {$icon} {$rec['message']}\n";
        }
        echo "\n";
    }

    echo "9. MEMORY OPTIMIZATION\n";
    echo "----------------------\n";

    echo "Memory usage strategies:\n\n";

    echo "✓ Lazy Loading (on-demand):\n";
    echo "  Load relationships only when accessed\n";
    echo "  Memory: Low, Queries: Variable\n\n";

    echo "✓ Eager Loading (upfront):\n";
    echo "  Load relationships with initial query\n";
    echo "  Memory: Higher, Queries: Fewer\n\n";

    echo "✓ Chunking (large datasets):\n";
    echo "  Process data in small batches\n";
    echo "  Memory: Constant, Time: Longer\n\n";

    echo "✓ Select Optimization:\n";
    echo "  Fetch only required columns\n";
    echo "  Memory: Reduced, Speed: Faster\n\n";

    $memoryStats = [
        'current' => memory_get_usage(true),
        'peak' => memory_get_peak_usage(true),
    ];
    
    echo "Current Memory Statistics:\n";
    echo "  Current usage: " . round($memoryStats['current'] / 1024 / 1024, 2) . "MB\n";
    echo "  Peak usage: " . round($memoryStats['peak'] / 1024 / 1024, 2) . "MB\n\n";

    echo "10. BEST PRACTICES SUMMARY\n";
    echo "--------------------------\n";

    echo "🔹 QUERY OPTIMIZATION\n";
    echo "   • Use SELECT with specific columns\n";
    echo "   • Add proper database indexes\n";
    echo "   • Implement query result caching\n";
    echo "   • Use LIMIT for large result sets\n\n";

    echo "🔹 RELATIONSHIP LOADING\n";
    echo "   • Use eager loading to prevent N+1 queries\n";
    echo "   • Implement lazy loading for optional data\n";
    echo "   • Batch load related models when possible\n";
    echo "   • Cache frequently accessed relationships\n\n";

    echo "🔹 MEMORY MANAGEMENT\n";
    echo "   • Use chunking for large datasets\n";
    echo "   • Clear model caches periodically\n";
    echo "   • Optimize SELECT statements\n";
    echo "   • Use generators for iteration\n\n";

    echo "🔹 CONNECTION MANAGEMENT\n";
    echo "   • Implement connection pooling\n";
    echo "   • Monitor connection health\n";
    echo "   • Balance load across databases\n";
    echo "   • Set appropriate timeouts\n\n";

    echo "🔹 MONITORING & ANALYSIS\n";
    echo "   • Track query performance metrics\n";
    echo "   • Monitor cache hit rates\n";
    echo "   • Identify slow and duplicate queries\n";
    echo "   • Set up performance alerts\n\n";

    $finalStats = PerformanceMonitor::getMetrics();
    echo "Final Performance Summary:\n";
    echo "  Queries executed: " . $finalStats['total_queries'] . "\n";
    echo "  Total execution time: " . round($finalStats['total_execution_time'], 2) . "ms\n";
    echo "  Cache efficiency: " . $finalStats['cache_hit_rate'] . "%\n";
    echo "  Performance optimizations: Active ✅\n\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "====================================================\n";
echo "✅ ORM PERFORMANCE OPTIMIZATIONS EXAMPLE COMPLETE!\n";
echo "====================================================\n\n";

echo "The Performance system provides:\n\n";

echo "🔹 INTELLIGENT CACHING\n";
echo "   Automatic query result caching with TTL\n\n";

echo "🔹 N+1 QUERY PREVENTION\n";
echo "   Smart eager loading and batch operations\n\n";

echo "🔹 CONNECTION OPTIMIZATION\n";
echo "   Efficient connection pooling and reuse\n\n";

echo "🔹 MEMORY EFFICIENCY\n";
echo "   Chunking and lazy loading strategies\n\n";

echo "🔹 PERFORMANCE MONITORING\n";
echo "   Comprehensive metrics and recommendations\n\n";

echo "Your ORM is now optimized for maximum performance! 🚀\n";