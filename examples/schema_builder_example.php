<?php

declare(strict_types=1);

/**
 * Schema Builder Example
 * 
 * Demonstrates the Horizon Framework's Schema Builder functionality
 * for creating and modifying database tables with fluent API.
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "Horizon Framework - Schema Builder Example\n";
echo "==========================================\n\n";

use Horizon\Database\Schema\Builder;
use Horizon\Database\Schema\Blueprint;

echo "1. BASIC SCHEMA OPERATIONS\n";
echo "--------------------------\n";

// Create mock connection for demonstration
$mockConnection = new class {
    protected $statements = [];
    
    public function statement($sql) {
        $this->statements[] = $sql;
        echo "EXECUTED: {$sql}\n";
        return true;
    }
    
    public function getStatements() {
        return $this->statements;
    }
    
    public function getSchemaGrammar() {
        return new \Horizon\Database\Schema\Grammars\Grammar();
    }
    
    public function getTablePrefix() {
        return '';
    }
    
    public function getConfig($key) {
        return $key === 'prefix_indexes' ? false : '';
    }
    
    public function selectFromWriteConnection($sql, $bindings = []) {
        echo "QUERY: {$sql} with bindings: " . json_encode($bindings) . "\n";
        return [];
    }
    
    public function getPostProcessor() {
        return new class {
            public function processColumnListing($results) {
                return ['id', 'name', 'email', 'created_at', 'updated_at'];
            }
        };
    }
};

try {
    $schema = new Builder($mockConnection);
    echo "✓ Schema Builder created successfully\n\n";

    echo "2. CREATE TABLE EXAMPLES\n";
    echo "------------------------\n";

    // Basic table creation
    echo "Creating 'users' table:\n";
    $schema->create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email')->unique();
        $table->timestamp('email_verified_at')->nullable();
        $table->string('password');
        $table->rememberToken();
        $table->timestamps();
    });
    echo "\n";

    // Table with relationships
    echo "Creating 'posts' table with foreign keys:\n";
    $schema->create('posts', function (Blueprint $table) {
        $table->id();
        $table->string('title');
        $table->text('content');
        $table->unsignedBigInteger('user_id');
        $table->string('status')->default('draft');
        $table->timestamps();
        
        $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        $table->index('status');
    });
    echo "\n";

    echo "3. COLUMN TYPES DEMONSTRATION\n";
    echo "-----------------------------\n";

    echo "Creating 'products' table with various column types:\n";
    $schema->create('products', function (Blueprint $table) {
        $table->id();
        
        // String types
        $table->string('name', 100);
        $table->char('code', 10)->unique();
        $table->text('description');
        $table->mediumText('long_description')->nullable();
        
        // Numeric types
        $table->decimal('price', 8, 2);
        $table->integer('quantity')->unsigned();
        $table->float('weight', 8, 2)->nullable();
        $table->double('volume')->nullable();
        
        // Boolean
        $table->boolean('active')->default(true);
        
        // Dates
        $table->date('release_date')->nullable();
        $table->dateTime('available_at')->nullable();
        $table->timestamp('last_restocked_at')->nullable();
        
        // JSON
        $table->json('metadata')->nullable();
        
        // Enums
        $table->enum('category', ['electronics', 'clothing', 'books', 'home']);
        
        // Special types
        $table->uuid('external_id')->nullable();
        $table->ipAddress('created_from_ip')->nullable();
        
        $table->timestamps();
    });
    echo "\n";

    echo "4. TABLE MODIFICATIONS\n";
    echo "----------------------\n";

    // Adding columns to existing table
    echo "Adding columns to 'users' table:\n";
    $schema->table('users', function (Blueprint $table) {
        $table->string('phone', 20)->nullable()->after('email');
        $table->date('birth_date')->nullable();
        $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
    });
    echo "\n";

    // Modifying columns
    echo "Modifying columns in 'products' table:\n";
    $schema->table('products', function (Blueprint $table) {
        $table->string('name', 150)->change(); // Increase length
        $table->text('description')->nullable()->change(); // Make nullable
    });
    echo "\n";

    echo "5. INDEX OPERATIONS\n";
    echo "-------------------\n";

    // Adding indexes
    echo "Adding indexes:\n";
    $schema->table('users', function (Blueprint $table) {
        $table->index('email'); // Simple index
        $table->index(['name', 'status']); // Compound index
        $table->unique('phone'); // Unique constraint
    });
    echo "\n";

    // Dropping indexes
    echo "Dropping indexes:\n";
    $schema->table('users', function (Blueprint $table) {
        $table->dropIndex(['name', 'status']);
        $table->dropUnique('users_phone_unique');
    });
    echo "\n";

    echo "6. FOREIGN KEY CONSTRAINTS\n";
    echo "--------------------------\n";

    echo "Creating 'comments' table with foreign keys:\n";
    $schema->create('comments', function (Blueprint $table) {
        $table->id();
        $table->text('content');
        $table->unsignedBigInteger('post_id');
        $table->unsignedBigInteger('user_id');
        $table->timestamps();
        
        // Foreign keys with different actions
        $table->foreign('post_id')->references('id')->on('posts')->cascadeOnDelete();
        $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
    });
    echo "\n";

    echo "7. POLYMORPHIC RELATIONSHIPS\n";
    echo "----------------------------\n";

    echo "Creating 'images' table for polymorphic relationships:\n";
    $schema->create('images', function (Blueprint $table) {
        $table->id();
        $table->string('filename');
        $table->string('path');
        $table->integer('size');
        $table->morphs('imageable'); // Creates imageable_type and imageable_id
        $table->timestamps();
    });
    echo "\n";

    echo "8. SOFT DELETES SUPPORT\n";
    echo "-----------------------\n";

    echo "Adding soft deletes to 'users' table:\n";
    $schema->table('users', function (Blueprint $table) {
        $table->softDeletes(); // Adds deleted_at timestamp
    });
    echo "\n";

    echo "9. DROPPING OPERATIONS\n";
    echo "----------------------\n";

    // Drop columns
    echo "Dropping columns:\n";
    $schema->table('users', function (Blueprint $table) {
        $table->dropColumn(['birth_date', 'phone']);
        $table->dropRememberToken();
        $table->dropTimestamps();
    });
    echo "\n";

    // Drop foreign keys
    echo "Dropping foreign keys:\n";
    $schema->table('comments', function (Blueprint $table) {
        $table->dropForeign(['post_id']);
        $table->dropForeign('comments_user_id_foreign');
    });
    echo "\n";

    echo "10. TABLE MANAGEMENT\n";
    echo "--------------------\n";

    // Rename table
    echo "Renaming table:\n";
    $schema->rename('products', 'items');
    echo "\n";

    // Drop tables
    echo "Dropping tables:\n";
    $schema->dropIfExists('comments');
    $schema->drop('images');
    echo "\n";

    echo "11. CHECKING TABLE/COLUMN EXISTENCE\n";
    echo "-----------------------------------\n";

    // Check if table exists
    $userTableExists = $schema->hasTable('users');
    echo "Users table exists: " . ($userTableExists ? 'Yes' : 'No') . "\n";

    // Check if column exists
    $hasEmailColumn = $schema->hasColumn('users', 'email');
    echo "Users table has email column: " . ($hasEmailColumn ? 'Yes' : 'No') . "\n";

    // Check multiple columns
    $hasRequiredColumns = $schema->hasColumns('users', ['name', 'email', 'password']);
    echo "Users table has required columns: " . ($hasRequiredColumns ? 'Yes' : 'No') . "\n\n";

    echo "12. SCHEMA BUILDER PATTERNS\n";
    echo "---------------------------\n";

    echo "✓ Fluent Interface: Clean, readable table definitions\n";
    echo "✓ Type Safety: Strong typing for column definitions\n";
    echo "✓ Database Agnostic: Works across different databases\n";
    echo "✓ Migration Ready: Perfect for database migrations\n";
    echo "✓ Constraint Support: Foreign keys, indexes, unique constraints\n";
    echo "✓ Modern Features: JSON columns, UUIDs, polymorphic relations\n\n";

    echo "13. REAL-WORLD USAGE EXAMPLES\n";
    echo "------------------------------\n";

    echo "// E-commerce product catalog\n";
    echo "Schema::create('products', function (Blueprint \$table) {\n";
    echo "    \$table->id();\n";
    echo "    \$table->string('name');\n";
    echo "    \$table->string('slug')->unique();\n";
    echo "    \$table->text('description');\n";
    echo "    \$table->decimal('price', 10, 2);\n";
    echo "    \$table->integer('stock')->unsigned();\n";
    echo "    \$table->boolean('active')->default(true);\n";
    echo "    \$table->json('attributes')->nullable();\n";
    echo "    \$table->timestamps();\n";
    echo "    \$table->softDeletes();\n";
    echo "    \n";
    echo "    \$table->index(['active', 'created_at']);\n";
    echo "    \$table->fullText(['name', 'description']);\n";
    echo "});\n\n";

    echo "// User management with roles\n";
    echo "Schema::create('user_roles', function (Blueprint \$table) {\n";
    echo "    \$table->id();\n";
    echo "    \$table->foreignId('user_id')->constrained()->cascadeOnDelete();\n";
    echo "    \$table->foreignId('role_id')->constrained()->cascadeOnDelete();\n";
    echo "    \$table->timestamps();\n";
    echo "    \n";
    echo "    \$table->unique(['user_id', 'role_id']);\n";
    echo "});\n\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "========================================\n";
echo "✅ SCHEMA BUILDER EXAMPLE COMPLETE!\n";
echo "========================================\n\n";

echo "The Schema Builder provides:\n\n";

echo "🔹 EXPRESSIVE SYNTAX\n";
echo "   Intuitive, fluent API for table definitions\n\n";

echo "🔹 COMPREHENSIVE TYPES\n";
echo "   Support for all common database column types\n\n";

echo "🔹 RELATIONSHIP SUPPORT\n";
echo "   Foreign keys, indexes, and constraints\n\n";

echo "🔹 MODERN FEATURES\n";
echo "   JSON columns, UUIDs, soft deletes, polymorphic relations\n\n";

echo "🔹 DATABASE AGNOSTIC\n";
echo "   Works with MySQL, PostgreSQL, SQLite, SQL Server\n\n";

echo "Ready for building robust database schemas! 🚀\n";