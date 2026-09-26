<?php

declare(strict_types=1);

/**
 * Routing Performance Optimizations Example
 * 
 * Demonstrates the Horizon Framework's high-performance routing capabilities
 * with caching, compilation optimizations, fast lookups, and performance
 * monitoring for production applications.
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "Horizon Framework - Routing Performance Optimizations Example\n";
echo "=============================================================\n\n";

use Horizon\Routing\Router;
use Horizon\Routing\RouteCollection;
use Horizon\Routing\RouteCache;
use Horizon\Routing\FastRouter;
use Horizon\Http\Request;
use Horizon\Http\Response;

try {
    echo "1. SETTING UP PERFORMANCE TEST ENVIRONMENT\n";
    echo "===========================================\n\n";

    // Create temporary cache directory
    $cacheDir = sys_get_temp_dir() . '/horizon_cache_test';
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    $cacheFile = $cacheDir . '/routes.cache';

    echo "✅ Test environment setup:\n";
    echo "   Cache directory: {$cacheDir}\n";
    echo "   Cache file: {$cacheFile}\n\n";

    echo "2. CREATING LARGE ROUTE COLLECTION\n";
    echo "==================================\n\n";

    // Create a large route collection for performance testing
    $routes = new RouteCollection();
    
    // Add static routes (fast O(1) lookup)
    $staticRoutes = [
        '/',
        '/about',
        '/contact',
        '/pricing',
        '/features',
        '/documentation',
        '/api/status',
        '/api/health',
        '/api/version',
        '/admin/dashboard',
    ];

    foreach ($staticRoutes as $uri) {
        $route = new \Horizon\Routing\Route(['GET'], $uri, function () use ($uri) {
            return new Response("Static route: {$uri}");
        });
        $route->name(str_replace(['/', '-'], ['', '_'], trim($uri, '/')) ?: 'home');
        $routes->add($route);
    }

    // Add dynamic routes with parameters (requires pattern matching)
    $dynamicPatterns = [
        '/users/{id}',
        '/users/{id}/posts',
        '/users/{id}/posts/{post}',
        '/categories/{category}',
        '/categories/{category}/posts/{post}',
        '/api/users/{id}',
        '/api/posts/{id}',
        '/api/comments/{id}',
        '/blog/{year}/{month}/{slug}',
        '/products/{category}/{subcategory}/{id}',
        '/files/{path}/{filename}',
        '/admin/users/{id}',
        '/admin/posts/{id}/edit',
        '/api/v1/resources/{resource}/items/{item}',
        '/api/v2/namespaces/{namespace}/objects/{object}',
    ];

    foreach ($dynamicPatterns as $pattern) {
        $route = new \Horizon\Routing\Route(['GET'], $pattern, function () use ($pattern) {
            return new Response("Dynamic route: {$pattern}");
        });
        
        // Add constraints to some routes
        if (str_contains($pattern, '{id}')) {
            $route->whereNumber('id');
        }
        if (str_contains($pattern, '{year}')) {
            $route->where('year', '[0-9]{4}');
        }
        if (str_contains($pattern, '{month}')) {
            $route->where('month', '[0-9]{1,2}');
        }
        
        $routes->add($route);
    }

    // Add some complex routes with multiple parameters and constraints
    $complexRoutes = [
        '/api/v{version}/users/{user}/posts/{post}/comments/{comment}',
        '/admin/reports/{type}/{year}/{month}/{day}',
        '/search/{query}/category/{category}/page/{page}',
        '/files/{bucket}/{region}/{path}/{filename}',
        '/webhooks/{service}/{event}/{signature}',
    ];

    foreach ($complexRoutes as $pattern) {
        $route = new \Horizon\Routing\Route(['GET', 'POST'], $pattern, function () use ($pattern) {
            return new Response("Complex route: {$pattern}");
        });
        $routes->add($route);
    }

    echo "✅ Created route collection:\n";
    echo "   Static routes: " . count($staticRoutes) . "\n";
    echo "   Dynamic routes: " . count($dynamicPatterns) . "\n";
    echo "   Complex routes: " . count($complexRoutes) . "\n";
    echo "   Total routes: " . count($routes) . "\n\n";

    echo "3. ROUTE CACHING PERFORMANCE\n";
    echo "============================\n\n";

    // Test route caching
    $cache = new RouteCache($cacheFile, null, [
        'compression' => true,
        'ttl' => 3600,
    ]);

    echo "🚀 Testing route compilation and caching:\n\n";

    // Measure compilation time
    $startTime = microtime(true);
    $success = $cache->cache($routes);
    $compilationTime = (microtime(true) - $startTime) * 1000;

    if ($success) {
        echo "   ✅ Routes compiled and cached successfully\n";
        echo "   ⏱️  Compilation time: " . round($compilationTime, 2) . "ms\n";
        
        $cacheStats = $cache->getStats();
        echo "   📦 Cache file size: " . round($cacheStats['file_size'] / 1024, 2) . "KB\n";
        echo "   🗜️  Compression: " . ($cache->getConfig()['compression'] ? 'enabled' : 'disabled') . "\n";
    } else {
        echo "   ❌ Route caching failed\n";
    }
    echo "\n";

    // Test cache loading performance
    echo "🔄 Testing cache loading performance:\n\n";
    
    $loadTests = 100;
    $totalLoadTime = 0;
    
    for ($i = 0; $i < $loadTests; $i++) {
        $startTime = microtime(true);
        $cached = $cache->load();
        $totalLoadTime += (microtime(true) - $startTime) * 1000;
    }
    
    $avgLoadTime = $totalLoadTime / $loadTests;
    
    echo "   ✅ Cache loading test completed\n";
    echo "   📊 {$loadTests} load operations\n";
    echo "   ⏱️  Average load time: " . round($avgLoadTime, 4) . "ms\n";
    echo "   🎯 Cache hit rate: " . $cache->getHitRate() . "%\n\n";

    echo "4. FAST ROUTER PERFORMANCE\n";
    echo "===========================\n\n";

    // Create FastRouter with cache
    $fastRouter = new FastRouter($routes, $cache, [
        'cache_enabled' => true,
        'performance_monitoring' => true,
        'precompile_routes' => true,
    ]);

    echo "✅ FastRouter initialized with optimizations:\n";
    echo "   Cache: enabled\n";
    echo "   Precompilation: enabled\n";
    echo "   Performance monitoring: enabled\n\n";

    echo "5. ROUTE MATCHING PERFORMANCE TESTS\n";
    echo "====================================\n\n";

    // Test requests for performance comparison
    $testRequests = [
        // Static routes (should be fastest)
        ['GET', '/'],
        ['GET', '/about'],
        ['GET', '/api/status'],
        
        // Simple dynamic routes
        ['GET', '/users/123'],
        ['GET', '/categories/technology'],
        ['GET', '/api/users/456'],
        
        // Complex dynamic routes
        ['GET', '/users/123/posts/456'],
        ['GET', '/blog/2024/03/my-awesome-post'],
        ['GET', '/products/electronics/smartphones/789'],
        
        // Very complex routes
        ['GET', '/api/v1/resources/posts/items/123'],
        ['GET', '/admin/reports/sales/2024/03/15'],
        ['GET', '/search/horizon-framework/category/software/page/1'],
    ];

    echo "🏁 Performance comparison: FastRouter vs Standard Router\n\n";

    // Create standard router for comparison
    $standardRouter = new Router();
    foreach ($routes as $route) {
        $standardRouter->getRoutes()->add($route);
    }

    // Test FastRouter performance
    echo "   🚀 FastRouter Performance:\n";
    $fastRouterTime = 0;
    $fastRouterTests = 0;
    
    foreach ($testRequests as [$method, $uri]) {
        $request = Request::create($uri, $method);
        
        $startTime = microtime(true);
        try {
            $route = $fastRouter->match($request);
            $duration = (microtime(true) - $startTime) * 1000;
            $fastRouterTime += $duration;
            $fastRouterTests++;
            
            echo "     ✅ {$method} {$uri}: " . round($duration, 4) . "ms\n";
        } catch (Exception $e) {
            echo "     ❌ {$method} {$uri}: {$e->getMessage()}\n";
        }
    }
    
    $avgFastTime = $fastRouterTests > 0 ? $fastRouterTime / $fastRouterTests : 0;
    echo "     📊 Average: " . round($avgFastTime, 4) . "ms\n\n";

    // Test standard router performance
    echo "   🐌 Standard Router Performance:\n";
    $standardRouterTime = 0;
    $standardRouterTests = 0;
    
    foreach ($testRequests as [$method, $uri]) {
        $request = Request::create($uri, $method);
        
        $startTime = microtime(true);
        try {
            $route = $standardRouter->matchRequest($request);
            $duration = (microtime(true) - $startTime) * 1000;
            $standardRouterTime += $duration;
            $standardRouterTests++;
            
            echo "     ✅ {$method} {$uri}: " . round($duration, 4) . "ms\n";
        } catch (Exception $e) {
            echo "     ❌ {$method} {$uri}: {$e->getMessage()}\n";
        }
    }
    
    $avgStandardTime = $standardRouterTests > 0 ? $standardRouterTime / $standardRouterTests : 0;
    echo "     📊 Average: " . round($avgStandardTime, 4) . "ms\n\n";

    // Performance comparison
    if ($avgFastTime > 0 && $avgStandardTime > 0) {
        $improvement = (($avgStandardTime - $avgFastTime) / $avgStandardTime) * 100;
        echo "   ⚡ Performance improvement: " . round($improvement, 1) . "%\n";
        echo "   🏃 Speed multiplier: " . round($avgStandardTime / $avgFastTime, 2) . "x faster\n\n";
    }

    echo "6. BULK PERFORMANCE TEST\n";
    echo "========================\n\n";

    echo "🔥 High-volume routing test (1000 requests):\n\n";
    
    $bulkTestRequests = [];
    $requestPool = [
        ['GET', '/'],
        ['GET', '/users/123'],
        ['GET', '/api/status'],
        ['GET', '/categories/technology'],
        ['GET', '/blog/2024/03/performance-test'],
        ['GET', '/users/456/posts/789'],
    ];
    
    // Generate 1000 random requests
    for ($i = 0; $i < 1000; $i++) {
        $bulkTestRequests[] = $requestPool[array_rand($requestPool)];
    }

    // Test FastRouter bulk performance
    echo "   🚀 FastRouter bulk test:\n";
    $startTime = microtime(true);
    $successCount = 0;
    
    foreach ($bulkTestRequests as [$method, $uri]) {
        try {
            $request = Request::create($uri, $method);
            $fastRouter->match($request);
            $successCount++;
        } catch (Exception $e) {
            // Count failures but continue
        }
    }
    
    $fastBulkTime = (microtime(true) - $startTime) * 1000;
    $fastAvgPerRequest = $fastBulkTime / 1000;
    
    echo "     ✅ Completed: {$successCount}/1000 requests\n";
    echo "     ⏱️  Total time: " . round($fastBulkTime, 2) . "ms\n";
    echo "     📊 Average per request: " . round($fastAvgPerRequest, 4) . "ms\n";
    echo "     🔥 Requests per second: " . round(1000 / ($fastBulkTime / 1000), 0) . "\n\n";

    echo "7. PERFORMANCE METRICS ANALYSIS\n";
    echo "===============================\n\n";

    $metrics = $fastRouter->getMetrics();
    echo "📈 FastRouter Performance Metrics:\n\n";
    
    echo "   🎯 Routing Statistics:\n";
    echo "     Total lookups: {$metrics['total_lookups']}\n";
    echo "     Cache hits: {$metrics['cache_hits']}\n";
    echo "     Cache misses: {$metrics['cache_misses']}\n";
    echo "     Static matches: {$metrics['static_matches']}\n";
    echo "     Dynamic matches: {$metrics['dynamic_matches']}\n\n";
    
    echo "   ⚡ Performance Rates:\n";
    $cacheHitRate = $metrics['total_lookups'] > 0 
        ? round(($metrics['cache_hits'] / $metrics['total_lookups']) * 100, 1) 
        : 0;
    $staticMatchRate = ($metrics['static_matches'] + $metrics['dynamic_matches']) > 0
        ? round(($metrics['static_matches'] / ($metrics['static_matches'] + $metrics['dynamic_matches'])) * 100, 1)
        : 0;
    
    echo "     Cache hit rate: {$cacheHitRate}%\n";
    echo "     Static match rate: {$staticMatchRate}%\n";
    echo "     Average lookup time: " . round($metrics['avg_lookup_time'] * 1000, 4) . "ms\n\n";

    echo "   🏗️  Route Structure:\n";
    echo "     Static routes: {$metrics['static_routes_count']}\n";
    echo "     Dynamic routes: {$metrics['dynamic_routes_count']}\n";
    echo "     Named routes: {$metrics['named_routes_count']}\n";
    echo "     Cache enabled: " . ($metrics['cache_enabled'] ? 'Yes' : 'No') . "\n\n";

    echo "8. DIAGNOSTIC INFORMATION\n";
    echo "=========================\n\n";

    $diagnostics = $fastRouter->getDiagnostics();
    echo "🔍 Router Diagnostics:\n\n";
    
    echo "   ⚡ Performance Metrics:\n";
    foreach ($diagnostics['performance'] as $key => $value) {
        echo "     {$key}: {$value}\n";
    }
    echo "\n";
    
    echo "   🏗️  Route Structure:\n";
    foreach ($diagnostics['structure'] as $key => $value) {
        if (is_array($value)) {
            echo "     {$key}: " . implode(', ', $value) . "\n";
        } else {
            echo "     {$key}: {$value}\n";
        }
    }
    echo "\n";
    
    echo "   ⚙️  Optimization Settings:\n";
    foreach ($diagnostics['optimization'] as $key => $value) {
        echo "     {$key}: " . ($value ? 'enabled' : 'disabled') . "\n";
    }
    echo "\n";

    echo "9. CACHE STATISTICS\n";
    echo "===================\n\n";

    if ($cache) {
        $cacheStats = $cache->getStats();
        echo "💾 Cache Performance:\n\n";
        
        foreach ($cacheStats as $key => $value) {
            if (is_numeric($value)) {
                if ($key === 'file_size') {
                    echo "   {$key}: " . round($value / 1024, 2) . " KB\n";
                } elseif ($key === 'file_age') {
                    echo "   {$key}: {$value} seconds\n";
                } elseif (str_contains($key, 'time')) {
                    echo "   {$key}: " . date('Y-m-d H:i:s', $value) . "\n";
                } else {
                    echo "   {$key}: {$value}\n";
                }
            } else {
                echo "   {$key}: {$value}\n";
            }
        }
        echo "\n";
    }

    echo "10. MEMORY USAGE ANALYSIS\n";
    echo "=========================\n\n";

    echo "💾 Memory Usage:\n";
    echo "   Current usage: " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB\n";
    echo "   Peak usage: " . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . " MB\n";
    echo "   Cache file size: " . (file_exists($cacheFile) ? round(filesize($cacheFile) / 1024, 2) . " KB" : "N/A") . "\n\n";

    echo "11. OPTIMIZATION RECOMMENDATIONS\n";
    echo "================================\n\n";

    echo "🎯 Performance Optimization Tips:\n\n";
    
    $recommendations = [];
    
    if ($cacheHitRate < 80) {
        $recommendations[] = "Consider increasing cache TTL to improve hit rate";
    }
    
    if ($staticMatchRate < 60) {
        $recommendations[] = "Convert more routes to static routes where possible";
    }
    
    if ($metrics['dynamic_routes_count'] > 50) {
        $recommendations[] = "Consider grouping similar dynamic routes";
    }
    
    if ($avgFastTime > 1.0) {
        $recommendations[] = "Enable route compilation and caching";
    }
    
    if (empty($recommendations)) {
        $recommendations[] = "Router is well optimized! 🚀";
    }
    
    foreach ($recommendations as $i => $recommendation) {
        echo "   " . ($i + 1) . ". {$recommendation}\n";
    }
    echo "\n";

    echo "12. CLEANUP\n";
    echo "===========\n\n";

    // Clean up test cache
    if (file_exists($cacheFile)) {
        unlink($cacheFile);
        echo "✅ Cleaned up test cache file\n";
    }
    
    if (is_dir($cacheDir)) {
        rmdir($cacheDir);
        echo "✅ Cleaned up test cache directory\n";
    }
    echo "\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "========================================================\n";
echo "✅ ROUTING PERFORMANCE OPTIMIZATIONS EXAMPLE COMPLETE!\n";
echo "========================================================\n\n";

echo "Performance optimization features demonstrated:\n\n";

echo "🔹 ROUTE COMPILATION CACHING\n";
echo "   Pre-compiled routes stored in optimized cache files\n\n";

echo "🔹 FAST LOOKUP ALGORITHMS\n";
echo "   O(1) static route lookups and optimized dynamic matching\n\n";

echo "🔹 PERFORMANCE MONITORING\n";
echo "   Real-time metrics and diagnostic information\n\n";

echo "🔹 MEMORY OPTIMIZATION\n";
echo "   Efficient data structures and optional compression\n\n";

echo "🔹 CACHE MANAGEMENT\n";
echo "   Automatic cache invalidation and warming strategies\n\n";

echo "🔹 PRODUCTION-READY FEATURES\n";
echo "   TTL-based caching, statistics, and optimization hints\n\n";

echo "Ready for high-throughput routing! ⚡\n";