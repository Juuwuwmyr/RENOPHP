<?php

declare(strict_types=1);

namespace Reno\Console\Commands;

use Reno\Console\Command;
use Reno\Support\Str;
use InvalidArgumentException;

/**
 * Make Model Command
 * 
 * Generates Eloquent model classes with optional migrations, factories, and seeders.
 */
class MakeModelCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected string $signature = 'make:model 
                                  {name : The name of the model}
                                  {--m|migration : Create a new migration file for the model}
                                  {--f|factory : Create a new factory for the model}
                                  {--s|seeder : Create a new seeder for the model}
                                  {--c|controller : Create a new controller for the model}
                                  {--r|resource : Create a resource controller for the model}
                                  {--a|all : Generate migration, factory, seeder and controller}
                                  {--force : Overwrite the model if it exists}';

    /**
     * The console command description.
     */
    protected string $description = 'Create a new Eloquent model class';

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
        $path = $this->getPath($className);

        // Check if model already exists
        if ($this->files->exists($path) && !$this->option('force')) {
            $this->error("Model [{$className}] already exists!");
            return 1;
        }

        // Create the model directory if it doesn't exist
        $this->makeDirectory($path);

        // Generate the model content
        $stub = $this->getStub();
        $content = $this->populateStub($stub, $className, $name);

        // Write the model file
        $this->files->put($path, $content);

        $this->info("Model [{$className}] created successfully.");

        // Generate additional files if requested
        $this->generateAdditionalFiles($name, $className);

        return 0;
    }

    /**
     * Validate the model name.
     */
    protected function validateName(string $name): bool
    {
        if (empty($name)) {
            $this->error('Model name cannot be empty.');
            return false;
        }

        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name)) {
            $this->error('Model name must be a valid PHP class name.');
            return false;
        }

        return true;
    }

    /**
     * Get the class name from the model name.
     */
    protected function getClassName(string $name): string
    {
        return Str::studly($name);
    }

    /**
     * Get the full path for the model.
     */
    protected function getPath(string $className): string
    {
        return $this->getBasePath() . '/app/Models/' . $className . '.php';
    }

    /**
     * Get the base path for the application.
     */
    protected function getBasePath(): string
    {
        return getcwd();
    }

    /**
     * Create the directory for the model if it doesn't exist.
     */
    protected function makeDirectory(string $path): void
    {
        $directory = dirname($path);
        
        if (!$this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }
    }

    /**
     * Get the stub file content.
     */
    protected function getStub(): string
    {
        return '<?php

declare(strict_types=1);

namespace App\Models;

use Reno\Database\Eloquent\Model;

/**
 * {{ class }} Model
 * 
 * @property int $id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class {{ class }} extends Model
{
    /**
     * The table associated with the model.
     */
    protected ?string $table = \'{{ table }}\';

    /**
     * The attributes that are mass assignable.
     */
    protected array $fillable = [
        // Add your fillable attributes here
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected array $hidden = [
        // Add attributes to hide from serialization
    ];

    /**
     * The attributes that should be cast.
     */
    protected array $casts = [
        // Add your attribute casts here
        // \'created_at\' => \'datetime\',
        // \'updated_at\' => \'datetime\',
    ];

    /**
     * Get the attributes that should be converted to dates.
     */
    public function getDates(): array
    {
        return [
            \'created_at\',
            \'updated_at\',
        ];
    }

    // Add your model relationships and methods here
}';
    }

    /**
     * Populate the stub with the class name and table name.
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
     * Get the table name for the model.
     */
    protected function getTableName(string $name): string
    {
        return Str::snake(Str::plural($name));
    }

    /**
     * Generate additional files based on options.
     */
    protected function generateAdditionalFiles(string $name, string $className): void
    {
        $generateAll = $this->option('all');

        // Generate migration
        if ($this->option('migration') || $generateAll) {
            $this->generateMigration($name);
        }

        // Generate factory
        if ($this->option('factory') || $generateAll) {
            $this->generateFactory($className);
        }

        // Generate seeder
        if ($this->option('seeder') || $generateAll) {
            $this->generateSeeder($className);
        }

        // Generate controller
        if ($this->option('controller') || $this->option('resource') || $generateAll) {
            $this->generateController($className);
        }
    }

    /**
     * Generate a migration for the model.
     */
    protected function generateMigration(string $name): void
    {
        $tableName = $this->getTableName($name);
        $migrationName = "create_{$tableName}_table";

        try {
            $this->call('make:migration', [
                'name' => $migrationName,
                '--create' => $tableName,
            ]);
        } catch (\Exception $e) {
            $this->warn("Could not create migration: {$e->getMessage()}");
        }
    }

    /**
     * Generate a factory for the model.
     */
    protected function generateFactory(string $className): void
    {
        try {
            $this->call('make:factory', [
                'name' => $className . 'Factory',
                '--model' => $className,
            ]);
        } catch (\Exception $e) {
            $this->warn("Could not create factory: {$e->getMessage()}");
        }
    }

    /**
     * Generate a seeder for the model.
     */
    protected function generateSeeder(string $className): void
    {
        try {
            $this->call('make:seeder', [
                'name' => $className . 'Seeder',
            ]);
        } catch (\Exception $e) {
            $this->warn("Could not create seeder: {$e->getMessage()}");
        }
    }

    /**
     * Generate a controller for the model.
     */
    protected function generateController(string $className): void
    {
        $options = [];
        
        if ($this->option('resource') || $this->option('all')) {
            $options['--resource'] = true;
            $options['--model'] = $className;
        }

        try {
            $this->call('make:controller', array_merge([
                'name' => $className . 'Controller',
            ], $options));
        } catch (\Exception $e) {
            $this->warn("Could not create controller: {$e->getMessage()}");
        }
    }

    /**
     * Get file system instance.
     */
    protected function getFileSystem()
    {
        // In a real implementation, this would return the file system service
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