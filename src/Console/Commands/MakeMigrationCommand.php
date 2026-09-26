<?php

declare(strict_types=1);

namespace Reno\Console\Commands;

use Reno\Console\Command;
use Reno\Support\Str;

/**
 * Make Migration Command
 * 
 * Generates database migration files for schema changes.
 */
class MakeMigrationCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected string $signature = 'make:migration 
                                  {name : The name of the migration}
                                  {--create= : Create a new table}
                                  {--table= : Modify an existing table}
                                  {--force : Overwrite the migration if it exists}';

    /**
     * The console command description.
     */
    protected string $description = 'Create a new database migration file';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->argument('name');

        if (!$this->validateName($name)) {
            return 1;
        }

        $className = $this->getClassName($name);
        $fileName = $this->getFileName($name);
        $path = $this->getPath($fileName);

        // Check if migration already exists
        if ($this->migrationExists($name) && !$this->option('force')) {
            $this->error("Migration for [{$name}] already exists!");
            return 1;
        }

        // Create the migration directory if it doesn't exist
        $this->makeDirectory($path);

        // Generate the migration content
        $stub = $this->getStub();
        $content = $this->populateStub($stub, $className, $name);

        // Write the migration file
        $this->files->put($path, $content);

        $this->info("Migration [{$fileName}] created successfully.");

        return 0;
    }

    /**
     * Validate the migration name.
     */
    protected function validateName(string $name): bool
    {
        if (empty($name)) {
            $this->error('Migration name cannot be empty.');
            return false;
        }

        return true;
    }

    /**
     * Get the class name from the migration name.
     */
    protected function getClassName(string $name): string
    {
        return Str::studly($name);
    }

    /**
     * Get the file name for the migration.
     */
    protected function getFileName(string $name): string
    {
        $timestamp = date('Y_m_d_His');
        $snakeName = Str::snake($name);
        
        return $timestamp . '_' . $snakeName . '.php';
    }

    /**
     * Get the full path for the migration.
     */
    protected function getPath(string $fileName): string
    {
        return $this->getBasePath() . '/database/migrations/' . $fileName;
    }

    /**
     * Get the base path for the application.
     */
    protected function getBasePath(): string
    {
        return getcwd();
    }

    /**
     * Check if a migration with similar name already exists.
     */
    protected function migrationExists(string $name): bool
    {
        $migrationPath = $this->getBasePath() . '/database/migrations';
        $snakeName = Str::snake($name);

        if (!is_dir($migrationPath)) {
            return false;
        }

        $files = glob($migrationPath . '/*_' . $snakeName . '.php');
        return !empty($files);
    }

    /**
     * Create the directory for the migration if it doesn't exist.
     */
    protected function makeDirectory(string $path): void
    {
        $directory = dirname($path);
        
        if (!$this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }
    }

    /**
     * Get the appropriate stub file content.
     */
    protected function getStub(): string
    {
        if ($this->option('create')) {
            return $this->getCreateTableStub();
        } elseif ($this->option('table')) {
            return $this->getUpdateTableStub();
        } else {
            return $this->getBlankStub();
        }
    }

    /**
     * Get the create table stub.
     */
    protected function getCreateTableStub(): string
    {
        return '<?php

declare(strict_types=1);

use Reno\Database\Migrations\Migration;
use Reno\Database\Schema\Blueprint;
use Reno\Database\Schema\Builder;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Builder::create(\'{{ table }}\', function (Blueprint $table) {
            $table->id();
            
            // Add your table columns here
            // $table->string(\'name\');
            // $table->string(\'email\')->unique();
            // $table->text(\'description\')->nullable();
            // $table->boolean(\'is_active\')->default(true);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Builder::dropIfExists(\'{{ table }}\');
    }
};';
    }

    /**
     * Get the update table stub.
     */
    protected function getUpdateTableStub(): string
    {
        return '<?php

declare(strict_types=1);

use Reno\Database\Migrations\Migration;
use Reno\Database\Schema\Blueprint;
use Reno\Database\Schema\Builder;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Builder::table(\'{{ table }}\', function (Blueprint $table) {
            // Add your table modifications here
            // $table->string(\'new_column\')->after(\'existing_column\');
            // $table->dropColumn(\'old_column\');
            // $table->renameColumn(\'old_name\', \'new_name\');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Builder::table(\'{{ table }}\', function (Blueprint $table) {
            // Reverse your table modifications here
            // $table->dropColumn(\'new_column\');
            // $table->string(\'old_column\');
            // $table->renameColumn(\'new_name\', \'old_name\');
        });
    }
};';
    }

    /**
     * Get the blank migration stub.
     */
    protected function getBlankStub(): string
    {
        return '<?php

declare(strict_types=1);

use Reno\Database\Migrations\Migration;
use Reno\Database\Schema\Blueprint;
use Reno\Database\Schema\Builder;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add your migration logic here
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add your rollback logic here
    }
};';
    }

    /**
     * Populate the stub with the appropriate values.
     */
    protected function populateStub(string $stub, string $className, string $name): string
    {
        $tableName = $this->getTableName($name);

        $replacements = [
            '{{ class }}' => $className,
            '{{ table }}' => $tableName,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    /**
     * Get the table name from the migration name or options.
     */
    protected function getTableName(string $name): string
    {
        // Check if --create option is provided
        if ($createTable = $this->option('create')) {
            return $createTable;
        }

        // Check if --table option is provided
        if ($table = $this->option('table')) {
            return $table;
        }

        // Try to extract table name from migration name
        if (preg_match('/^create_(.+)_table$/', Str::snake($name), $matches)) {
            return $matches[1];
        }

        if (preg_match('/^add_(.+)_to_(.+)_table$/', Str::snake($name), $matches)) {
            return $matches[2];
        }

        if (preg_match('/^drop_(.+)_from_(.+)_table$/', Str::snake($name), $matches)) {
            return $matches[2];
        }

        // Default fallback
        return 'table_name';
    }

    /**
     * Get file system instance.
     */
    protected function getFileSystem()
    {
        return new class {
            public function exists(string $path): bool {
                return file_exists($path);
            }
            
            public function put(string $path, string $content): bool {
                return file_put_contents($path, $content) !== false;
            }
            
            public function isDirectory(string $path): bool {
                return is_dir($path);
            }
            
            public function makeDirectory(string $path, int $mode = 0755, bool $recursive = false): bool {
                return mkdir($path, $mode, $recursive);
            }
        };
    }

    /**
     * Get the files property.
     */
    public function __get(string $name)
    {
        if ($name === 'files') {
            return $this->getFileSystem();
        }
        
        return parent::__get($name);
    }
}