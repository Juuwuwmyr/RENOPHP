<?php

declare(strict_types=1);

/**
 * Console Commands Example
 * 
 * Demonstrates the Horizon Framework's ORM-related console commands
 * for generating models, seeders, factories, and migrations.
 */

echo "Horizon Framework - ORM Console Commands Example\n";
echo "================================================\n\n";

echo "1. CONSOLE COMMANDS OVERVIEW\n";
echo "----------------------------\n";

echo "Available ORM-related commands:\n";
echo "✓ make:model     - Generate Eloquent model classes\n";
echo "✓ make:seeder    - Generate database seeder classes\n";
echo "✓ make:factory   - Generate model factory classes\n";
echo "✓ make:migration - Generate database migration files\n";
echo "✓ make:controller- Generate controller classes (mentioned)\n\n";

echo "2. MAKE:MODEL COMMAND\n";
echo "---------------------\n";

echo "Generate a basic model:\n";
echo "```bash\n";
echo "php horizon make:model User\n";
echo "```\n\n";

echo "Generated User model:\n";
echo "📄 app/Models/User.php\n\n";

echo "```php\n";
echo "<?php\n\n";
echo "declare(strict_types=1);\n\n";
echo "namespace App\\Models;\n\n";
echo "use Horizon\\Database\\Eloquent\\Model;\n\n";
echo "class User extends Model\n";
echo "{\n";
echo "    protected ?string \$table = 'users';\n\n";
echo "    protected array \$fillable = [\n";
echo "        // Add your fillable attributes here\n";
echo "    ];\n\n";
echo "    protected array \$hidden = [\n";
echo "        // Add attributes to hide from serialization\n";
echo "    ];\n\n";
echo "    protected array \$casts = [\n";
echo "        // Add your attribute casts here\n";
echo "    ];\n";
echo "}\n";
echo "```\n\n";

echo "3. MAKE:MODEL WITH OPTIONS\n";
echo "--------------------------\n";

echo "Generate model with migration:\n";
echo "```bash\n";
echo "php horizon make:model Post --migration\n";
echo "php horizon make:model Post -m\n";
echo "```\n\n";

echo "Generate model with factory:\n";
echo "```bash\n";
echo "php horizon make:model User --factory\n";
echo "php horizon make:model User -f\n";
echo "```\n\n";

echo "Generate model with seeder:\n";
echo "```bash\n";
echo "php horizon make:model Category --seeder\n";
echo "php horizon make:model Category -s\n";
echo "```\n\n";

echo "Generate model with controller:\n";
echo "```bash\n";
echo "php horizon make:model Product --controller\n";
echo "php horizon make:model Product -c\n";
echo "```\n\n";

echo "Generate model with resource controller:\n";
echo "```bash\n";
echo "php horizon make:model Order --resource\n";
echo "php horizon make:model Order -r\n";
echo "```\n\n";

echo "Generate everything at once:\n";
echo "```bash\n";
echo "php horizon make:model Article --all\n";
echo "php horizon make:model Article -a\n";
echo "```\n";
echo "Creates: Model + Migration + Factory + Seeder + Resource Controller\n\n";

echo "4. MAKE:MIGRATION COMMAND\n";
echo "-------------------------\n";

echo "Generate a table creation migration:\n";
echo "```bash\n";
echo "php horizon make:migration create_posts_table --create=posts\n";
echo "```\n\n";

echo "Generated migration:\n";
echo "📄 database/migrations/2024_01_15_143022_create_posts_table.php\n\n";

echo "```php\n";
echo "<?php\n\n";
echo "use Horizon\\Database\\Migrations\\Migration;\n";
echo "use Horizon\\Database\\Schema\\Blueprint;\n";
echo "use Horizon\\Database\\Schema\\Builder;\n\n";
echo "return new class extends Migration\n";
echo "{\n";
echo "    public function up(): void\n";
echo "    {\n";
echo "        Builder::create('posts', function (Blueprint \$table) {\n";
echo "            \$table->id();\n";
echo "            \$table->string('title');\n";
echo "            \$table->text('content');\n";
echo "            \$table->foreignId('user_id')->constrained();\n";
echo "            \$table->timestamps();\n";
echo "        });\n";
echo "    }\n\n";
echo "    public function down(): void\n";
echo "    {\n";
echo "        Builder::dropIfExists('posts');\n";
echo "    }\n";
echo "};\n";
echo "```\n\n";

echo "Generate a table modification migration:\n";
echo "```bash\n";
echo "php horizon make:migration add_status_to_posts_table --table=posts\n";
echo "```\n\n";

echo "5. MAKE:FACTORY COMMAND\n";
echo "-----------------------\n";

echo "Generate a factory for a model:\n";
echo "```bash\n";
echo "php horizon make:factory UserFactory --model=User\n";
echo "```\n\n";

echo "Generated factory:\n";
echo "📄 database/factories/UserFactory.php\n\n";

echo "```php\n";
echo "<?php\n\n";
echo "namespace Database\\Factories;\n\n";
echo "use App\\Models\\User;\n";
echo "use Horizon\\Database\\Factories\\Factory;\n\n";
echo "class UserFactory extends Factory\n";
echo "{\n";
echo "    protected string \$model = User::class;\n\n";
echo "    public function definition(): array\n";
echo "    {\n";
echo "        return [\n";
echo "            'name' => \$this->faker->name(),\n";
echo "            'email' => \$this->faker->unique()->safeEmail(),\n";
echo "            'password' => bcrypt('password'),\n";
echo "            'created_at' => \$this->faker->dateTimeBetween('-1 year'),\n";
echo "        ];\n";
echo "    }\n\n";
echo "    public function admin(): static\n";
echo "    {\n";
echo "        return \$this->state([\n";
echo "            'is_admin' => true,\n";
echo "        ]);\n";
echo "    }\n";
echo "}\n";
echo "```\n\n";

echo "6. MAKE:SEEDER COMMAND\n";
echo "----------------------\n";

echo "Generate a seeder:\n";
echo "```bash\n";
echo "php horizon make:seeder UserSeeder\n";
echo "```\n\n";

echo "Generated seeder:\n";
echo "📄 database/seeders/UserSeeder.php\n\n";

echo "```php\n";
echo "<?php\n\n";
echo "namespace Database\\Seeders;\n\n";
echo "use Horizon\\Database\\Seeder;\n\n";
echo "class UserSeeder extends Seeder\n";
echo "{\n";
echo "    public function run(): void\n";
echo "    {\n";
echo "        \$this->disableForeignKeyChecks();\n\n";
echo "        try {\n";
echo "            \$this->createSampleData();\n";
echo "        } finally {\n";
echo "            \$this->enableForeignKeyChecks();\n";
echo "        }\n";
echo "    }\n\n";
echo "    protected function createSampleData(): void\n";
echo "    {\n";
echo "        // Generate users using factory\n";
echo "        User::factory()->count(10)->create();\n\n";
echo "        \$this->command->info('UserSeeder completed successfully!');\n";
echo "    }\n";
echo "}\n";
echo "```\n\n";

echo "7. COMMAND OPTIONS AND FLAGS\n";
echo "----------------------------\n";

echo "Common options available:\n\n";

echo "🔹 --force\n";
echo "   Overwrite existing files without confirmation\n";
echo "   Example: php horizon make:model User --force\n\n";

echo "🔹 Model-specific options:\n";
echo "   -m, --migration  : Create migration\n";
echo "   -f, --factory    : Create factory\n";
echo "   -s, --seeder     : Create seeder\n";
echo "   -c, --controller : Create controller\n";
echo "   -r, --resource   : Create resource controller\n";
echo "   -a, --all        : Create all related files\n\n";

echo "🔹 Migration-specific options:\n";
echo "   --create=table   : Create new table migration\n";
echo "   --table=table    : Modify existing table migration\n\n";

echo "🔹 Factory-specific options:\n";
echo "   --model=Model    : Specify the target model\n\n";

echo "8. PRACTICAL WORKFLOWS\n";
echo "----------------------\n";

echo "🔹 CREATING A NEW ENTITY\n";
echo "Complete entity setup:\n\n";

echo "```bash\n";
echo "# Create everything for a Blog Post entity\n";
echo "php horizon make:model Post --all\n";
echo "```\n\n";

echo "This creates:\n";
echo "✓ app/Models/Post.php\n";
echo "✓ database/migrations/xxxx_create_posts_table.php\n";
echo "✓ database/factories/PostFactory.php\n";
echo "✓ database/seeders/PostSeeder.php\n";
echo "✓ app/Http/Controllers/PostController.php (resource)\n\n";

echo "🔹 ADDING TO EXISTING ENTITY\n";
echo "Add missing components:\n\n";

echo "```bash\n";
echo "# Add factory to existing User model\n";
echo "php horizon make:factory UserFactory --model=User\n\n";
echo "# Add seeder for existing Category model\n";
echo "php horizon make:seeder CategorySeeder\n\n";
echo "# Add migration to modify existing table\n";
echo "php horizon make:migration add_status_to_users_table --table=users\n";
echo "```\n\n";

echo "🔹 RAPID PROTOTYPING\n";
echo "Quick setup for multiple entities:\n\n";

echo "```bash\n";
echo "# Create core blog entities\n";
echo "php horizon make:model User --all\n";
echo "php horizon make:model Category --all\n";
echo "php horizon make:model Post --all\n";
echo "php horizon make:model Comment --all\n";
echo "php horizon make:model Tag --all\n\n";
echo "# Create junction table\n";
echo "php horizon make:migration create_post_tag_table --create=post_tag\n";
echo "```\n\n";

echo "9. GENERATED FILE STRUCTURE\n";
echo "---------------------------\n";

echo "After running make commands, your project structure:\n\n";

echo "📁 app/\n";
echo "  📁 Models/\n";
echo "    📄 User.php\n";
echo "    📄 Post.php\n";
echo "    📄 Category.php\n";
echo "    📄 Comment.php\n";
echo "  📁 Http/Controllers/\n";
echo "    📄 UserController.php\n";
echo "    📄 PostController.php\n\n";

echo "📁 database/\n";
echo "  📁 migrations/\n";
echo "    📄 2024_01_15_120000_create_users_table.php\n";
echo "    📄 2024_01_15_120001_create_posts_table.php\n";
echo "    📄 2024_01_15_120002_add_status_to_users_table.php\n";
echo "  📁 factories/\n";
echo "    📄 UserFactory.php\n";
echo "    📄 PostFactory.php\n";
echo "  📁 seeders/\n";
echo "    📄 DatabaseSeeder.php\n";
echo "    📄 UserSeeder.php\n";
echo "    📄 PostSeeder.php\n\n";

echo "10. INTEGRATION WITH OTHER COMMANDS\n";
echo "-----------------------------------\n";

echo "Generated files work seamlessly with other commands:\n\n";

echo "```bash\n";
echo "# Run migrations\n";
echo "php horizon migrate\n\n";
echo "# Run seeders\n";
echo "php horizon db:seed\n";
echo "php horizon db:seed --class=UserSeeder\n\n";
echo "# Refresh with seeding\n";
echo "php horizon migrate:fresh --seed\n\n";
echo "# Generate API resources\n";
echo "php horizon make:resource UserResource\n\n";
echo "# Run tests\n";
echo "php horizon test\n";
echo "```\n\n";

echo "11. CUSTOMIZATION AND EXTENSIBILITY\n";
echo "-----------------------------------\n";

echo "🔹 CUSTOM STUBS\n";
echo "   Create custom templates in resources/stubs/\n";
echo "   Override default model, migration, factory templates\n\n";

echo "🔹 NAMESPACE CUSTOMIZATION\n";
echo "   Configure model namespace in config/app.php\n";
echo "   Support for custom directory structures\n\n";

echo "🔹 COMMAND EXTENSION\n";
echo "   Extend base command classes\n";
echo "   Add custom validation and options\n";
echo "   Create domain-specific generators\n\n";

echo "12. BEST PRACTICES\n";
echo "------------------\n";

echo "🔹 NAMING CONVENTIONS\n";
echo "   • Models: Singular PascalCase (User, BlogPost)\n";
echo "   • Tables: Plural snake_case (users, blog_posts)\n";
echo "   • Factories: ModelNameFactory (UserFactory)\n";
echo "   • Seeders: ModelNameSeeder (UserSeeder)\n";
echo "   • Migrations: Descriptive snake_case (create_users_table)\n\n";

echo "🔹 ORGANIZATION\n";
echo "   • Group related entities together\n";
echo "   • Use consistent migration ordering\n";
echo "   • Keep factories focused and realistic\n";
echo "   • Make seeders idempotent and environment-aware\n\n";

echo "🔹 DEVELOPMENT WORKFLOW\n";
echo "   1. Generate model with migration\n";
echo "   2. Design schema in migration\n";
echo "   3. Add relationships to model\n";
echo "   4. Create factory with realistic data\n";
echo "   5. Build seeder with proper dependencies\n";
echo "   6. Test with fresh migration and seeding\n\n";

echo "====================================================\n";
echo "✅ CONSOLE COMMANDS EXAMPLE COMPLETE!\n";
echo "====================================================\n\n";

echo "The Console Commands system provides:\n\n";

echo "🔹 RAPID SCAFFOLDING\n";
echo "   Quick generation of all ORM-related files\n\n";

echo "🔹 CONSISTENT STRUCTURE\n";
echo "   Standardized file organization and naming\n\n";

echo "🔹 FLEXIBLE OPTIONS\n";
echo "   Generate individual files or complete sets\n\n";

echo "🔹 INTELLIGENT DEFAULTS\n";
echo "   Smart naming and relationship inference\n\n";

echo "🔹 EXTENSIBLE ARCHITECTURE\n";
echo "   Customizable templates and generation logic\n\n";

echo "Ready for efficient ORM development! 🚀\n";