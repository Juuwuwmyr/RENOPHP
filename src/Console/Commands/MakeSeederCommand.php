<?php

declare(strict_types=1);

namespace Horizon\Console\Commands;

use Horizon\Console\Command;
use Horizon\Support\Str;

/**
 * Make Seeder Command
 * 
 * Generates database seeder classes for populating the database with test data.
 */
class MakeSeederCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected string $signature = 'make:seeder 
                                  {name : The name of the seeder}
                                  {--force : Overwrite the seeder if it exists}';

    /**
     * The console command description.
     */
    protected string $description = 'Create a new database seeder class';

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

        // Check if seeder already exists
        if ($this->files->exists($path) && !$this->option('force')) {
            $this->error("Seeder [{$className}] already exists!");
            return 1;
        }

        // Create the seeder directory if it doesn't exist
        $this->makeDirectory($path);

        // Generate the seeder content
        $stub = $this->getStub();
        $content = $this->populateStub($stub, $className);

        // Write the seeder file
        $this->files->put($path, $content);

        $this->info("Seeder [{$className}] created successfully.");

        return 0;
    }

    /**
     * Validate the seeder name.
     */
    protected function validateName(string $name): bool
    {
        if (empty($name)) {
            $this->error('Seeder name cannot be empty.');
            return false;
        }

        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name)) {
            $this->error('Seeder name must be a valid PHP class name.');
            return false;
        }

        return true;
    }

    /**
     * Get the class name from the seeder name.
     */
    protected function getClassName(string $name): string
    {
        $className = Str::studly($name);
        
        // Ensure the class name ends with 'Seeder'
        if (!Str::endsWith($className, 'Seeder')) {
            $className .= 'Seeder';
        }
        
        return $className;
    }

    /**
     * Get the full path for the seeder.
     */
    protected function getPath(string $className): string
    {
        return $this->getBasePath() . '/database/seeders/' . $className . '.php';
    }

    /**
     * Get the base path for the application.
     */
    protected function getBasePath(): string
    {
        return getcwd();
    }

    /**
     * Create the directory for the seeder if it doesn't exist.
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

namespace Database\Seeders;

use Horizon\Database\Seeder;

/**
 * {{ class }}
 * 
 * Seeds the database with {{ entity }} data.
 */
class {{ class }} extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Disable foreign key checks during seeding
        $this->disableForeignKeyChecks();

        try {
            // Clear existing data (optional)
            // $this->truncate(\'{{ table }}\');

            // Create sample data
            $this->createSampleData();

        } finally {
            // Re-enable foreign key checks
            $this->enableForeignKeyChecks();
        }
    }

    /**
     * Create sample data for {{ entity }}.
     */
    protected function createSampleData(): void
    {
        $connection = $this->getConnection();

        // Example: Insert sample records
        /*
        $sampleData = [
            [
                \'name\' => \'Sample Record 1\',
                \'description\' => \'This is a sample record for testing\',
                \'created_at\' => $this->now(),
                \'updated_at\' => $this->now(),
            ],
            [
                \'name\' => \'Sample Record 2\',
                \'description\' => \'Another sample record for testing\',
                \'created_at\' => $this->now(),
                \'updated_at\' => $this->now(),
            ],
        ];

        foreach ($sampleData as $data) {
            $connection->table(\'{{ table }}\')->insert($data);
        }
        */

        // Or use the factory if available
        /*
        {{ modelClass }}::factory()->count(10)->create();
        */

        $this->command->info(\'{{ class }} completed successfully!\');
    }
}';
    }

    /**
     * Populate the stub with the class name and related information.
     */
    protected function populateStub(string $stub, string $className): string
    {
        // Try to determine the model class and table name from seeder name
        $entityName = $this->getEntityName($className);
        $modelClass = $this->getModelClassName($entityName);
        $tableName = $this->getTableName($entityName);

        $replacements = [
            '{{ class }}' => $className,
            '{{ entity }}' => $entityName,
            '{{ modelClass }}' => $modelClass,
            '{{ table }}' => $tableName,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    /**
     * Get the entity name from the seeder class name.
     */
    protected function getEntityName(string $className): string
    {
        // Remove 'Seeder' suffix and convert to readable format
        $entityName = str_replace('Seeder', '', $className);
        
        // Convert from StudlyCase to readable format
        return trim(preg_replace('/([A-Z])/', ' $1', $entityName));
    }

    /**
     * Get the model class name from entity name.
     */
    protected function getModelClassName(string $entityName): string
    {
        // Convert "User Data" to "UserData" or "Users" to "User"
        $modelName = str_replace(' ', '', $entityName);
        
        // Singularize if it appears to be plural
        if (Str::endsWith(strtolower($modelName), 's') && strlen($modelName) > 1) {
            $modelName = substr($modelName, 0, -1);
        }
        
        return $modelName;
    }

    /**
     * Get the table name from entity name.
     */
    protected function getTableName(string $entityName): string
    {
        // Convert "User Data" to "user_data" and pluralize
        $tableName = strtolower(str_replace(' ', '_', trim($entityName)));
        
        // Simple pluralization
        if (!Str::endsWith($tableName, 's')) {
            $tableName .= 's';
        }
        
        return $tableName;
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