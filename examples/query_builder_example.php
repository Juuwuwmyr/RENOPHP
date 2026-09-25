<?php

declare(strict_types=1);

/**
 * Query Builder Example
 * 
 * Demonstrates the Horizon Framework's Query Builder functionality
 * with fluent API for database operations.
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "Horizon Framework - Query Builder Example\n";
echo "=========================================\n\n";

// Note: This is a demonstration of the Query Builder API
// In a real application, you would use this through the DatabaseManager

use Horizon\Database\Query\QueryBuilder;
use Horizon\Database\Query\Grammars\Grammar;
use Horizon\Database\Query\Processors\Processor;

echo "1. BASIC QUERY BUILDING\n";
echo "-----------------------\n";

// Create mock connection for demonstration
$mockConnection = new class {
    public function getQueryGrammar() { return new Grammar(); }
    public function getPostProcessor() { return new Processor(); }
    public function select($sql, $bindings = []) { 
        echo "SQL: {$sql}\n";
        echo "Bindings: " . json_encode($bindings) . "\n\n";
        return [];
    }
    public function insert($sql, $bindings = []) { 
        echo "INSERT SQL: {$sql}\n";
        echo "Bindings: " . json_encode($bindings) . "\n\n";
        return true;
    }
    public function update($sql, $bindings = []) { 
        echo "UPDATE SQL: {$sql}\n";
        echo "Bindings: " . json_encode($bindings) . "\n\n";
        return 1;
    }
    public function delete($sql, $bindings = []) { 
        echo "DELETE SQL: {$sql}\n";
        echo "Bindings: " . json_encode($bindings) . "\n\n";
        return 1;
    }
    public function getPdo() { return null; }
};

try {
    $queryBuilder = new QueryBuilder($mockConnection);
    
    echo "✓ Query Builder created successfully\n\n";

    echo "2. SELECT QUERIES\n";
    echo "-----------------\n";

    // Basic select
    $sql = $queryBuilder->select(['name', 'email'])
                       ->from('users')
                       ->toSql();
    echo "Basic SELECT:\n";
    echo "SQL: {$sql}\n";
    echo "Expected: select \"name\", \"email\" from \"users\"\n\n";

    // Select with WHERE clause
    $builder = new QueryBuilder($mockConnection);
    $sql = $builder->select('*')
                   ->from('users')
                   ->where('active', '=', 1)
                   ->where('age', '>', 18)
                   ->toSql();
    echo "SELECT with WHERE:\n";
    echo "SQL: {$sql}\n\n";

    // Select with JOIN
    $builder = new QueryBuilder($mockConnection);
    $sql = $builder->select(['users.name', 'profiles.bio'])
                   ->from('users')
                   ->join('profiles', 'users.id', '=', 'profiles.user_id')
                   ->where('users.active', '=', 1)
                   ->toSql();
    echo "SELECT with JOIN:\n";
    echo "SQL: {$sql}\n\n";

    // Select with ORDER BY and LIMIT
    $builder = new QueryBuilder($mockConnection);
    $sql = $builder->select('*')
                   ->from('posts')
                   ->orderBy('created_at', 'desc')
                   ->limit(10)
                   ->offset(20)
                   ->toSql();
    echo "SELECT with ORDER BY and LIMIT:\n";
    echo "SQL: {$sql}\n\n";

    echo "3. WHERE CLAUSES\n";
    echo "----------------\n";

    // WHERE IN
    $builder = new QueryBuilder($mockConnection);
    $sql = $builder->select('*')
                   ->from('users')
                   ->whereIn('id', [1, 2, 3, 4, 5])
                   ->toSql();
    echo "WHERE IN:\n";
    echo "SQL: {$sql}\n\n";

    // WHERE BETWEEN
    $builder = new QueryBuilder($mockConnection);
    $sql = $builder->select('*')
                   ->from('orders')
                   ->whereBetween('created_at', ['2024-01-01', '2024-12-31'])
                   ->toSql();
    echo "WHERE BETWEEN:\n";
    echo "SQL: {$sql}\n\n";

    // WHERE NULL
    $builder = new QueryBuilder($mockConnection);
    $sql = $builder->select('*')
                   ->from('users')
                   ->whereNull('deleted_at')
                   ->toSql();
    echo "WHERE NULL:\n";
    echo "SQL: {$sql}\n\n";

    echo "4. AGGREGATE FUNCTIONS\n";
    echo "----------------------\n";

    // COUNT
    $builder = new QueryBuilder($mockConnection);
    $sql = $builder->from('users')
                   ->count();
    echo "COUNT Query:\n";
    echo "This would execute: " . $builder->toSql() . "\n\n";

    echo "5. QUERY BUILDER PATTERNS\n";
    echo "-------------------------\n";

    // Method chaining
    echo "✓ Fluent Interface: All methods return \$this for chaining\n";
    echo "✓ Readable Syntax: Reads like natural language\n";
    echo "✓ Parameter Binding: Automatic protection against SQL injection\n";
    echo "✓ Query Compilation: Builds optimized SQL for the database\n\n";

    echo "6. ADVANCED FEATURES\n";
    echo "--------------------\n";

    // Conditional queries
    $builder = new QueryBuilder($mockConnection);
    $active = true;
    $sql = $builder->select('*')
                   ->from('users')
                   ->when($active, function ($query) {
                       return $query->where('status', 'active');
                   })
                   ->toSql();
    echo "Conditional Query:\n";
    echo "SQL: {$sql}\n\n";

    echo "7. QUERY BUILDER BENEFITS\n";
    echo "-------------------------\n";
    echo "✅ SQL Injection Protection: Automatic parameter binding\n";
    echo "✅ Database Agnostic: Works with MySQL, PostgreSQL, SQLite\n";
    echo "✅ Readable Code: Clean, expressive syntax\n";
    echo "✅ Maintainable: Easy to modify and extend queries\n";
    echo "✅ Testable: Can mock and test query logic\n";
    echo "✅ Performance: Optimized SQL generation\n\n";

    echo "8. REAL-WORLD USAGE EXAMPLES\n";
    echo "-----------------------------\n";

    echo "// Find active users with posts\n";
    echo "\$users = DB::table('users')\n";
    echo "    ->select('users.*', 'posts.title')\n";
    echo "    ->join('posts', 'users.id', '=', 'posts.user_id')\n";
    echo "    ->where('users.active', true)\n";
    echo "    ->orderBy('users.name')\n";
    echo "    ->get();\n\n";

    echo "// Complex filtering\n";
    echo "\$products = DB::table('products')\n";
    echo "    ->where('category_id', \$categoryId)\n";
    echo "    ->where('price', '>=', \$minPrice)\n";
    echo "    ->where('price', '<=', \$maxPrice)\n";
    echo "    ->whereIn('brand', \$selectedBrands)\n";
    echo "    ->whereNull('deleted_at')\n";
    echo "    ->orderBy('price', 'asc')\n";
    echo "    ->paginate(20);\n\n";

    echo "// Aggregation with grouping\n";
    echo "\$stats = DB::table('orders')\n";
    echo "    ->select('status', DB::raw('COUNT(*) as count'))\n";
    echo "    ->groupBy('status')\n";
    echo "    ->having('count', '>', 10)\n";
    echo "    ->get();\n\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "========================================\n";
echo "✅ QUERY BUILDER EXAMPLE COMPLETE!\n";
echo "========================================\n\n";

echo "The Query Builder provides:\n\n";

echo "🔹 SIMPLE SYNTAX\n";
echo "   Natural, readable method chaining\n\n";

echo "🔹 SECURITY FIRST\n";
echo "   Built-in SQL injection protection\n\n";

echo "🔹 FLEXIBILITY\n";
echo "   Build simple or complex queries easily\n\n";

echo "🔹 PERFORMANCE\n";
echo "   Efficient SQL generation and execution\n\n";

echo "🔹 TESTABILITY\n";
echo "   Easy to mock and unit test\n\n";

echo "Ready for production use in the Horizon Framework! 🚀\n";