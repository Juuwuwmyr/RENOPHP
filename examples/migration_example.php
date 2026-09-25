<?php

declare(strict_types=1);

/**
 * Migration System Example
 * 
 * Demonstrates the Horizon Framework's Migration system functionality
 * for database versioning, schema changes, and rollback capabilities.
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "Horizon Framework - Migration System Example\n";
echo "============================================\n\n";

use Horizon\Database\Migrations\Migration;
use Horizon\Database\Migrations\Migrator;
use Horizon\Database\Migrations\MigrationRepository;
use Horizon\Database\Migrations\MigrationCreator;
use Horizon\Database\Schema\Blueprint;

echo "1. MIGRATION SYSTEM OVERVIEW\n";
echo "-----------------------------\n";

echo "The Migration system provides:\n";
echo "✓ Database versioning and schema changes\n";
echo "✓ Forward (up) and backward (down) migrations\n";
echo "✓ Batch tracking for rollback operations\n";
echo "✓ Migration file generation and templates\n";
echo "✓ Transaction support for safe operations\n";
echo "✓ Rollback to specific points in time\n\n";

echo "2. CREATING MIGRATION FILES\n";
echo "---------------------------\n";

// Mock file system for demonstration
$mockFiles = new class {
    protected $files = [];
    protected $directories = [];
    
    public function put($path, $content) {
        $this->files[$path] = $content;
        echo "Created migration file: " . basename($path) . "\n";
        return true;
    }
    
    public function get($path) {
        return $this->files[$path] ?? '';
    }
    
    public function ensureDirectoryExists($path) {
        $this->directories[] = $path;
        return true;
    }
    
    public function glob($pattern) {
        return array_keys(array_filter($this->files, function($path) use ($pattern) {
            return fnmatch(str_replace('\\', '/', $pattern), str_replace('\\', '/', $path));
        }, ARRAY_FILTER_USE_KEY));
    }
    
    public function requireOnce($path) {
        return true;
    }
};

try {
    $creator = new MigrationCreator($mockFiles);
    
    // Create different types of migrations
    echo "Creating table migration:\n";
    $path1 = $creator->create('create_users_table', '/database/migrations', 'users', true);
    echo "Generated: " . basename($path1) . "\n\n";
    
    echo "Creating update migration:\n";
    $path2 = $creator->create('add_email_verification_to_users_table', '/database/migrations', 'users', false);
    echo "Generated: " . basename($path2) . "\n\n";
    
    echo "Creating generic migration:\n";
    $path3 = $creator->create('create_database_indexes', '/database/migrations');
    echo "Generated: " . basename($path3) . "\n\n";

    echo "3. EXAMPLE MIGRATION CLASSES\n";
    echo "-----------------------------\n";

    // Example migration for creating a users table
    echo "Example: Create Users Table Migration\n";
    echo "```php\n";
    echo "<?php\n\n";
    echo "use Horizon\\Database\\Migrations\\Migration;\n";
    echo "use Horizon\\Database\\Schema\\Blueprint;\n";
    echo "use Horizon\\Support\\Facades\\Schema;\n\n";
    echo "return new class extends Migration {\n";
    echo "    public function up(): void {\n";
    echo "        Schema::create('users', function (Blueprint \$table) {\n";
    echo "            \$table->id();\n";
    echo "            \$table->string('name');\n";
    echo "            \$table->string('email')->unique();\n";
    echo "            \$table->timestamp('email_verified_at')->nullable();\n";
    echo "            \$table->string('password');\n";
    echo "            \$table->rememberToken();\n";
    echo "            \$table->timestamps();\n";
    echo "        });\n";
    echo "    }\n\n";
    echo "    public function down(): void {\n";
    echo "        Schema::dropIfExists('users');\n";
    echo "    }\n";
    echo "};\n";
    echo "```\n\n";

    // Example migration for adding columns
    echo "Example: Add Columns Migration\n";
    echo "```php\n";
    echo "<?php\n\n";
    echo "return new class extends Migration {\n";
    echo "    public function up(): void {\n";
    echo "        Schema::table('users', function (Blueprint \$table) {\n";
    echo "            \$table->string('phone', 20)->nullable()->after('email');\n";
    echo "            \$table->date('birth_date')->nullable();\n";
    echo "            \$table->enum('status', ['active', 'inactive'])->default('active');\n";
    echo "            \$table->softDeletes();\n";
    echo "        });\n";
    echo "    }\n\n";
    echo "    public function down(): void {\n";
    echo "        Schema::table('users', function (Blueprint \$table) {\n";
    echo "            \$table->dropColumn(['phone', 'birth_date', 'status']);\n";
    echo "            \$table->dropSoftDeletes();\n";
    echo "        });\n";
    echo "    }\n";
    echo "};\n";
    echo "```\n\n";

    echo "4. MIGRATION OPERATIONS\n";
    echo "-----------------------\n";

    // Mock connection and repository
    $mockConnection = new class {
        public function table($table) {
            return new class {
                public function orderBy($column, $direction = 'asc') { return $this; }
                public function where($column, $value) { return $this; }
                public function pluck($column, $key = null) { 
                    return ['2024_01_01_000000_create_users_table', '2024_01_02_000000_add_columns_to_users'];
                }
                public function max($column) { return 2; }
                public function get() { 
                    return [
                        (object)['migration' => '2024_01_02_000000_add_columns_to_users', 'batch' => 2]
                    ];
                }
                public function take($number) { return $this; }
                public function insert($data) { echo "Logged migration: {$data['migration']} (batch {$data['batch']})\n"; }
                public function delete() { echo "Removed migration from log\n"; }
            };
        }
        public function getSchemaBuilder() {
            return new class {
                public function hasTable($table) { return $table === 'migrations'; }
                public function create($table, $callback) {
                    echo "Created migrations table: $table\n";
                }
                public function drop($table) {
                    echo "Dropped table: $table\n";
                }
            };
        }
    };

    // Create mock application container
    $mockApp = new class {
        public function make($abstract) {
            if ($abstract === 'db') {
                return new class {
                    public function connection($name = null) {
                        return new class {
                            public function getSchemaBuilder() {
                                return new \Horizon\Database\Schema\Builder($this);
                            }
                            public function getSchemaGrammar() {
                                return new class {
                                    public function supportsSchemaTransactions() { return true; }
                                };
                            }
                            public function transaction($callback) {
                                echo "Running migration in transaction\n";
                                return $callback();
                            }
                        };
                    }
                };
            }
            return null;
        }
        public function bound($abstract) { return false; }
    };

    $repository = new MigrationRepository($mockConnection, 'migrations');
    $migrator = new Migrator($repository, $mockApp, $mockFiles);

    echo "Migration Repository Operations:\n";
    echo "✓ Get ran migrations: " . count($repository->getRan()) . " migrations found\n";
    echo "✓ Next batch number: " . $repository->getNextBatchNumber() . "\n";
    echo "✓ Last batch number: " . $repository->getLastBatchNumber() . "\n";
    echo "✓ Repository exists: " . ($repository->repositoryExists() ? 'Yes' : 'No') . "\n\n";

    echo "5. MIGRATION WORKFLOW\n";
    echo "---------------------\n";

    echo "Step 1: Create Migration Repository\n";
    if (!$repository->repositoryExists()) {
        $repository->createRepository();
    }
    echo "✓ Migrations table ready\n\n";

    echo "Step 2: Run Pending Migrations\n";
    echo "// php horizon migrate\n";
    echo "✓ Scanning for migration files...\n";
    echo "✓ Found 2 pending migrations\n";
    echo "✓ Running migrations in batch 3...\n";
    $repository->log('2024_01_03_000000_create_posts_table', 3);
    $repository->log('2024_01_03_000001_add_indexes', 3);
    echo "\n";

    echo "Step 3: Rollback Last Batch\n";
    echo "// php horizon migrate:rollback\n";
    echo "✓ Rolling back batch 2...\n";
    echo "✓ Found 1 migration to rollback\n";
    echo "✓ Running down() method...\n";
    echo "\n";

    echo "Step 4: Reset All Migrations\n";
    echo "// php horizon migrate:reset\n";
    echo "✓ Rolling back all migrations...\n";
    echo "✓ Database reset to initial state\n";
    echo "\n";

    echo "Step 5: Fresh Migration\n";
    echo "// php horizon migrate:fresh\n";
    echo "✓ Dropping all tables...\n";
    echo "✓ Running all migrations...\n";
    echo "✓ Database rebuilt from scratch\n\n";

    echo "6. MIGRATION COMMANDS\n";
    echo "---------------------\n";

    $commands = [
        'make:migration create_users_table' => 'Create a new migration file',
        'make:migration add_email_to_users --table=users' => 'Create migration to modify table',
        'migrate' => 'Run pending migrations',
        'migrate:rollback' => 'Rollback the last batch of migrations',
        'migrate:rollback --step=2' => 'Rollback last 2 migration batches',
        'migrate:reset' => 'Rollback all migrations',
        'migrate:refresh' => 'Reset and re-run all migrations',
        'migrate:fresh' => 'Drop all tables and run migrations',
        'migrate:status' => 'Show migration status',
        'migrate:install' => 'Create the migration repository',
    ];

    foreach ($commands as $command => $description) {
        echo sprintf("%-50s %s\n", "php horizon {$command}", $description);
    }
    echo "\n";

    echo "7. ADVANCED MIGRATION FEATURES\n";
    echo "------------------------------\n";

    echo "✓ Transaction Support: Migrations run in database transactions\n";
    echo "✓ Batch Tracking: Group migrations for coordinated rollbacks\n";
    echo "✓ Connection Management: Specify database connections per migration\n";
    echo "✓ Pretend Mode: Preview SQL without executing (--pretend flag)\n";
    echo "✓ Step Migration: Run specific number of migrations (--step flag)\n";
    echo "✓ Path Management: Support multiple migration directories\n";
    echo "✓ Event System: Hook into migration lifecycle events\n";
    echo "✓ Error Recovery: Safe rollback on migration failures\n\n";

    echo "8. MIGRATION BEST PRACTICES\n";
    echo "---------------------------\n";

    echo "🔹 NAMING CONVENTIONS\n";
    echo "   • Use descriptive names: create_users_table, add_email_to_users\n";
    echo "   • Include table name: add_index_to_posts_table\n";
    echo "   • Use action verbs: create, add, drop, modify, rename\n\n";

    echo "🔹 STRUCTURE\n";
    echo "   • Always implement both up() and down() methods\n";
    echo "   • Make down() reverse exactly what up() does\n";
    echo "   • Use Schema facade for table operations\n";
    echo "   • Add constraints and indexes after table creation\n\n";

    echo "🔹 SAFETY\n";
    echo "   • Test migrations in development first\n";
    echo "   • Use transactions for complex operations\n";
    echo "   • Backup database before production migrations\n";
    echo "   • Run --pretend to preview changes\n\n";

    echo "🔹 COLLABORATION\n";
    echo "   • Keep migrations small and focused\n";
    echo "   • Don't modify old migrations in shared repositories\n";
    echo "   • Coordinate schema changes with team\n";
    echo "   • Document breaking changes\n\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "========================================\n";
echo "✅ MIGRATION SYSTEM EXAMPLE COMPLETE!\n";
echo "========================================\n\n";

echo "The Migration system provides:\n\n";

echo "🔹 DATABASE VERSIONING\n";
echo "   Track and manage schema changes over time\n\n";

echo "🔹 ROLLBACK CAPABILITY\n";
echo "   Safely undo database changes when needed\n\n";

echo "🔹 TEAM COLLABORATION\n";
echo "   Coordinate schema changes across developers\n\n";

echo "🔹 DEPLOYMENT SAFETY\n";
echo "   Automated, repeatable database updates\n\n";

echo "🔹 DEVELOPMENT WORKFLOW\n";
echo "   Easy schema iteration during development\n\n";

echo "Ready for production database management! 🚀\n";