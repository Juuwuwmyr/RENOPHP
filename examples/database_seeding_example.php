<?php

declare(strict_types=1);

/**
 * Database Seeding System Example
 * 
 * Demonstrates the Horizon Framework's database seeding functionality
 * for populating databases with test data and initial application data.
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "Horizon Framework - Database Seeding System Example\n";
echo "====================================================\n\n";

use Horizon\Database\Seeder;
use Horizon\Database\Seeders\DatabaseSeeder;
use Horizon\Database\Seeders\UserSeeder;
use Horizon\Database\Seeders\PostSeeder;

echo "1. SEEDING SYSTEM OVERVIEW\n";
echo "--------------------------\n";

echo "Database seeding provides:\n";
echo "✓ Populate database with test data\n";
echo "✓ Initialize application with default data\n";
echo "✓ Generate realistic fake data for testing\n";
echo "✓ Organize data creation with seeder classes\n";
echo "✓ Factory pattern for model generation\n";
echo "✓ Relationship handling in test data\n";
echo "✓ Foreign key constraint management\n";
echo "✓ Reproducible dataset creation\n\n";

echo "2. SEEDER ARCHITECTURE\n";
echo "----------------------\n";

echo "Seeding system components:\n\n";

echo "📁 database/\n";
echo "  📁 seeders/\n";
echo "    📄 DatabaseSeeder.php      # Main orchestrator\n";
echo "    📄 UserSeeder.php          # User-specific data\n";
echo "    📄 PostSeeder.php          # Post-specific data\n";
echo "    📄 CategorySeeder.php      # Category data\n";
echo "  📁 factories/\n";
echo "    📄 UserFactory.php         # User model factory\n";
echo "    📄 PostFactory.php         # Post model factory\n\n";

echo "3. BASIC SEEDER STRUCTURE\n";
echo "-------------------------\n";

echo "Example seeder class:\n\n";

echo "```php\n";
echo "<?php\n\n";
echo "use Horizon\\Database\\Seeder;\n\n";
echo "class UserSeeder extends Seeder\n";
echo "{\n";
echo "    public function run(): void\n";
echo "    {\n";
echo "        // Clear existing data\n";
echo "        \$this->truncate('users');\n\n";
echo "        // Insert admin user\n";
echo "        \$connection = \$this->getConnection();\n";
echo "        \$connection->table('users')->insert([\n";
echo "            'name' => 'Administrator',\n";
echo "            'email' => 'admin@example.com',\n";
echo "            'password' => password_hash('password', PASSWORD_DEFAULT),\n";
echo "            'created_at' => \$this->now(),\n";
echo "            'updated_at' => \$this->now(),\n";
echo "        ]);\n\n";
echo "        // Generate test users\n";
echo "        \$users = \$this->generateUsers(10);\n";
echo "        foreach (\$users as \$user) {\n";
echo "            \$connection->table('users')->insert(\$user);\n";
echo "        }\n";
echo "    }\n\n";
echo "    protected function generateUsers(int \$count): array\n";
echo "    {\n";
echo "        // Generate realistic user data...\n";
echo "    }\n";
echo "}\n";
echo "```\n\n";

echo "4. SEEDER ORCHESTRATION\n";
echo "-----------------------\n";

echo "DatabaseSeeder coordinates all seeders:\n\n";

echo "```php\n";
echo "class DatabaseSeeder extends Seeder\n";
echo "{\n";
echo "    public function run(): void\n";
echo "    {\n";
echo "        // Run seeders in dependency order\n";
echo "        \$this->call([\n";
echo "            UserSeeder::class,\n";
echo "            CategorySeeder::class,\n";
echo "            PostSeeder::class,\n";
echo "            CommentSeeder::class,\n";
echo "        ]);\n\n";
echo "        // Or call with parameters\n";
echo "        \$this->call(UserSeeder::class, false, [50]); // 50 users\n\n";
echo "        // Silent execution\n";
echo "        \$this->callSilent([\n";
echo "            TagSeeder::class,\n";
echo "            PermissionSeeder::class,\n";
echo "        ]);\n";
echo "    }\n";
echo "}\n";
echo "```\n\n";

// Mock connection for demonstration
$mockConnection = new class {
    protected $data = [];
    
    public function table($table) {
        return new class($table) {
            protected $table;
            protected static $data = [];
            
            public function __construct($table) {
                $this->table = $table;
            }
            
            public function insert($data) {
                if (!isset(static::$data[$this->table])) {
                    static::$data[$this->table] = [];
                }
                static::$data[$this->table][] = $data;
                echo "✓ Inserted record into {$this->table}: " . ($data['name'] ?? $data['title'] ?? 'record') . "\n";
                return true;
            }
            
            public function truncate() {
                static::$data[$this->table] = [];
                echo "✓ Truncated table: {$this->table}\n";
                return true;
            }
            
            public function pluck($column) {
                $records = static::$data[$this->table] ?? [];
                return array_column($records, $column);
            }
            
            public function count() {
                return count(static::$data[$this->table] ?? []);
            }
        };
    }
    
    public function statement($sql) {
        echo "✓ Executed: $sql\n";
        return true;
    }
    
    public function getDriverName() {
        return 'mysql';
    }
};

try {
    echo "5. PRACTICAL SEEDING EXAMPLE\n";
    echo "-----------------------------\n";

    // Create mock seeders for demonstration
    $userSeeder = new class extends Seeder {
        public function run(): void {
            echo "Running UserSeeder...\n";
            
            // Truncate existing data
            $this->getConnection()->table('users')->truncate();
            
            // Seed admin user
            $this->getConnection()->table('users')->insert([
                'name' => 'Administrator',
                'email' => 'admin@horizon.dev',
                'password' => password_hash('admin123', PASSWORD_DEFAULT),
                'is_admin' => true,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            
            // Seed regular users
            $users = $this->generateUsers(5);
            foreach ($users as $user) {
                $this->getConnection()->table('users')->insert($user);
            }
        }
        
        protected function generateUsers(int $count): array {
            $users = [];
            $names = ['John Doe', 'Jane Smith', 'Mike Johnson', 'Sarah Wilson', 'David Brown'];
            $emails = ['john@example.com', 'jane@example.com', 'mike@example.com', 'sarah@example.com', 'david@example.com'];
            
            for ($i = 0; $i < $count; $i++) {
                $users[] = [
                    'name' => $names[$i],
                    'email' => $emails[$i],
                    'password' => password_hash('password', PASSWORD_DEFAULT),
                    'is_admin' => false,
                    'created_at' => $this->randomDate('-1 year'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
            }
            
            return $users;
        }
    };

    $postSeeder = new class extends Seeder {
        public function run(): void {
            echo "Running PostSeeder...\n";
            
            // Check for users first
            $userCount = $this->getConnection()->table('users')->count();
            if ($userCount === 0) {
                throw new \RuntimeException('No users found. Run UserSeeder first.');
            }
            
            // Truncate posts
            $this->getConnection()->table('posts')->truncate();
            
            // Generate posts
            $posts = $this->generatePosts(8);
            foreach ($posts as $post) {
                $this->getConnection()->table('posts')->insert($post);
            }
        }
        
        protected function generatePosts(int $count): array {
            $posts = [];
            $titles = [
                'Getting Started with Horizon Framework',
                'Building RESTful APIs',
                'Database Migrations Guide',
                'Advanced ORM Relationships',
                'Testing Your Application',
                'Deploying to Production',
                'Performance Optimization Tips',
                'Security Best Practices'
            ];
            
            for ($i = 0; $i < $count; $i++) {
                $posts[] = [
                    'title' => $titles[$i],
                    'slug' => strtolower(str_replace(' ', '-', $titles[$i])),
                    'content' => 'This is the content for ' . $titles[$i] . '. Lorem ipsum dolor sit amet...',
                    'user_id' => $this->randomNumber(1, 6), // Random user
                    'status' => $this->randomElement(['published', 'draft']),
                    'published_at' => $this->randomDate('-6 months'),
                    'created_at' => $this->randomDate('-1 year'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
            }
            
            return $posts;
        }
    };

    // Set up mock connections
    $userSeeder->setConnection($mockConnection);
    $postSeeder->setConnection($mockConnection);

    // Execute seeders
    echo "Executing UserSeeder:\n";
    $userSeeder->run();
    echo "\n";

    echo "Executing PostSeeder:\n";
    $postSeeder->run();
    echo "\n";

    echo "6. SEEDER HELPER METHODS\n";
    echo "------------------------\n";

    echo "Built-in helper methods for data generation:\n\n";

    $demoSeeder = new class extends Seeder {
        public function run(): void {
            echo "Demonstrating helper methods:\n\n";
            
            // Random data generation
            echo "Random element: " . $this->randomElement(['apple', 'banana', 'orange']) . "\n";
            echo "Random elements: " . implode(', ', $this->randomElements(['red', 'green', 'blue', 'yellow'], 2)) . "\n";
            echo "Random number: " . $this->randomNumber(1, 100) . "\n";
            echo "Random boolean: " . ($this->randomBoolean() ? 'true' : 'false') . "\n";
            echo "Random date: " . $this->randomDate('-1 year', 'now') . "\n\n";
            
            // Foreign key constraint handling
            echo "Managing foreign key constraints:\n";
            $this->disableForeignKeyChecks();
            echo "✓ Foreign key checks disabled\n";
            
            // Perform operations that might violate constraints
            echo "✓ Safe to truncate tables with relationships\n";
            
            $this->enableForeignKeyChecks();
            echo "✓ Foreign key checks re-enabled\n\n";
            
            // Using withoutForeignKeyChecks wrapper
            $this->withoutForeignKeyChecks(function() {
                echo "✓ Operations inside FK-disabled block\n";
            });
        }
    };

    $demoSeeder->setConnection($mockConnection);
    $demoSeeder->run();

    echo "7. MODEL FACTORIES\n";
    echo "------------------\n";

    echo "Model factories generate fake model instances:\n\n";

    echo "```php\n";
    echo "// Define a factory\n";
    echo "class UserFactory extends Factory\n";
    echo "{\n";
    echo "    protected string \$model = User::class;\n\n";
    echo "    public function definition(): array\n";
    echo "    {\n";
    echo "        return [\n";
    echo "            'name' => \$this->faker->name(),\n";
    echo "            'email' => \$this->faker->unique()->safeEmail(),\n";
    echo "            'password' => Hash::make('password'),\n";
    echo "            'email_verified_at' => \$this->faker->dateTime(),\n";
    echo "        ];\n";
    echo "    }\n\n";
    echo "    // Define states\n";
    echo "    public function admin()\n";
    echo "    {\n";
    echo "        return \$this->state([\n";
    echo "            'is_admin' => true,\n";
    echo "        ]);\n";
    echo "    }\n\n";
    echo "    public function unverified()\n";
    echo "    {\n";
    echo "        return \$this->state([\n";
    echo "            'email_verified_at' => null,\n";
    echo "        ]);\n";
    echo "    }\n";
    echo "}\n\n";

    echo "// Use factories in seeders\n";
    echo "class UserSeeder extends Seeder\n";
    echo "{\n";
    echo "    public function run(): void\n";
    echo "    {\n";
    echo "        // Create single user\n";
    echo "        \$admin = \$this->create(User::class, [\n";
    echo "            'name' => 'Admin User',\n";
    echo "            'is_admin' => true\n";
    echo "        ]);\n\n";
    echo "        // Create multiple users\n";
    echo "        \$users = \$this->create(User::class, 10);\n\n";
    echo "        // Create with factory states\n";
    echo "        User::factory()->admin()->create();\n";
    echo "        User::factory()->unverified()->count(5)->create();\n\n";
    echo "        // Create related models\n";
    echo "        User::factory()\n";
    echo "            ->has(Post::factory()->count(3))\n";
    echo "            ->create();\n";
    echo "    }\n";
    echo "}\n";
    echo "```\n\n";

    echo "8. RUNNING SEEDERS\n";
    echo "------------------\n";

    echo "Command-line usage:\n\n";

    echo "```bash\n";
    echo "# Run all seeders\n";
    echo "php horizon db:seed\n\n";
    echo "# Run specific seeder\n";
    echo "php horizon db:seed --class=UserSeeder\n\n";
    echo "# Run in production (with confirmation)\n";
    echo "php horizon db:seed --force\n\n";
    echo "# Fresh migration with seeding\n";
    echo "php horizon migrate:fresh --seed\n\n";
    echo "# Refresh with seeding\n";
    echo "php horizon migrate:refresh --seed\n";
    echo "```\n\n";

    echo "9. SEEDING STRATEGIES\n";
    echo "---------------------\n";

    echo "🔹 DEVELOPMENT SEEDING\n";
    echo "   • Rich, realistic test data\n";
    echo "   • Multiple user roles and scenarios\n";
    echo "   • Complex relationship examples\n";
    echo "   • Edge case data for testing\n\n";

    echo "🔹 PRODUCTION SEEDING\n";
    echo "   • Essential application data only\n";
    echo "   • Default settings and configurations\n";
    echo "   • Admin accounts and permissions\n";
    echo "   • Reference data (countries, categories)\n\n";

    echo "🔹 TESTING SEEDING\n";
    echo "   • Minimal, fast-loading datasets\n";
    echo "   • Predictable data for assertions\n";
    echo "   • Isolated test scenarios\n";
    echo "   • Factory-generated variations\n\n";

    echo "10. ADVANCED SEEDING PATTERNS\n";
    echo "-----------------------------\n";

    echo "```php\n";
    echo "// Conditional seeding\n";
    echo "public function run(): void\n";
    echo "{\n";
    echo "    if (app()->environment('local')) {\n";
    echo "        \$this->call(DemoDataSeeder::class);\n";
    echo "    }\n\n";
    echo "    if (User::count() === 0) {\n";
    echo "        \$this->call(DefaultUserSeeder::class);\n";
    echo "    }\n";
    echo "}\n\n";

    echo "// Progress feedback\n";
    echo "public function run(): void\n";
    echo "{\n";
    echo "    \$this->command->info('Creating users...');\n";
    echo "    \$this->create(User::class, 1000);\n\n";
    echo "    \$this->command->info('Creating posts...');\n";
    echo "    \$this->create(Post::class, 5000);\n\n";
    echo "    \$this->command->info('Seeding complete!');\n";
    echo "}\n\n";

    echo "// Transaction-wrapped seeding\n";
    echo "public function run(): void\n";
    echo "{\n";
    echo "    DB::transaction(function () {\n";
    echo "        \$this->call([\n";
    echo "            UserSeeder::class,\n";
    echo "            PostSeeder::class,\n";
    echo "        ]);\n";
    echo "    });\n";
    echo "}\n\n";

    echo "// Chunk processing for large datasets\n";
    echo "public function run(): void\n";
    echo "{\n";
    echo "    \$chunkSize = 1000;\n";
    echo "    \$totalUsers = 10000;\n\n";
    echo "    for (\$i = 0; \$i < \$totalUsers; \$i += \$chunkSize) {\n";
    echo "        \$this->create(User::class, min(\$chunkSize, \$totalUsers - \$i));\n";
    echo "        \$this->command->info('Created ' . min(\$i + \$chunkSize, \$totalUsers) . ' users');\n";
    echo "    }\n";
    echo "}\n";
    echo "```\n\n";

    echo "11. BEST PRACTICES\n";
    echo "------------------\n";

    echo "🔹 ORGANIZATION\n";
    echo "   • One seeder per main entity\n";
    echo "   • Use descriptive seeder names\n";
    echo "   • Group related seeders logically\n";
    echo "   • Keep seeders focused and simple\n\n";

    echo "🔹 DATA QUALITY\n";
    echo "   • Use realistic, varied test data\n";
    echo "   • Include edge cases and boundaries\n";
    echo "   • Maintain referential integrity\n";
    echo "   • Consider data relationships\n\n";

    echo "🔹 PERFORMANCE\n";
    echo "   • Batch insert operations\n";
    echo "   • Disable foreign key checks when safe\n";
    echo "   • Use transactions for consistency\n";
    echo "   • Consider memory usage for large datasets\n\n";

    echo "🔹 MAINTAINABILITY\n";
    echo "   • Make seeders idempotent\n";
    echo "   • Document seeding dependencies\n";
    echo "   • Use environment-specific seeding\n";
    echo "   • Version control seeder changes\n\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "====================================================\n";
echo "✅ DATABASE SEEDING SYSTEM EXAMPLE COMPLETE!\n";
echo "====================================================\n\n";

echo "The Seeding system provides:\n\n";

echo "🔹 ORGANIZED DATA CREATION\n";
echo "   Structured approach to database population\n\n";

echo "🔹 REALISTIC TEST DATA\n";
echo "   Generate meaningful data for development\n\n";

echo "🔹 REPRODUCIBLE DATASETS\n";
echo "   Consistent data across environments\n\n";

echo "🔹 RELATIONSHIP HANDLING\n";
echo "   Proper foreign key and constraint management\n\n";

echo "🔹 FACTORY INTEGRATION\n";
echo "   Powerful model generation capabilities\n\n";

echo "Ready for comprehensive database population! 🚀\n";