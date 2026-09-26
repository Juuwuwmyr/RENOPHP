<?php

declare(strict_types=1);

namespace Reno\Console\Commands;

use Reno\Console\Command;
use Reno\Support\Str;

/**
 * Make Factory Command
 * 
 * Generates model factory classes for creating fake model instances.
 */
class MakeFactoryCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected string $signature = 'make:factory 
                                  {name : The name of the factory}
                                  {--model= : The name of the model}
                                  {--force : Overwrite the factory if it exists}';

    /**
     * The console command description.
     */
    protected string $description = 'Create a new model factory class';

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
        $modelClass = $this->getModelClass($name);
        $path = $this->getPath($className);

        // Check if factory already exists
        if ($this->files->exists($path) && !$this->option('force')) {
            $this->error("Factory [{$className}] already exists!");
            return 1;
        }

        // Create the factory directory if it doesn't exist
        $this->makeDirectory($path);

        // Generate the factory content
        $stub = $this->getStub();
        $content = $this->populateStub($stub, $className, $modelClass);

        // Write the factory file
        $this->files->put($path, $content);

        $this->info("Factory [{$className}] created successfully.");

        return 0;
    }

    /**
     * Validate the factory name.
     */
    protected function validateName(string $name): bool
    {
        if (empty($name)) {
            $this->error('Factory name cannot be empty.');
            return false;
        }

        if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name)) {
            $this->error('Factory name must be a valid PHP class name.');
            return false;
        }

        return true;
    }

    /**
     * Get the class name from the factory name.
     */
    protected function getClassName(string $name): string
    {
        $className = Str::studly($name);
        
        // Ensure the class name ends with 'Factory'
        if (!Str::endsWith($className, 'Factory')) {
            $className .= 'Factory';
        }
        
        return $className;
    }

    /**
     * Get the model class name.
     */
    protected function getModelClass(string $name): string
    {
        // If model option is provided, use it
        if ($modelOption = $this->option('model')) {
            return Str::studly($modelOption);
        }

        // Otherwise, derive from factory name
        $modelName = str_replace('Factory', '', Str::studly($name));
        
        // If the name doesn't contain 'Factory', assume it's the model name
        if ($modelName === Str::studly($name)) {
            $modelName = $name;
        }
        
        return Str::studly($modelName);
    }

    /**
     * Get the full path for the factory.
     */
    protected function getPath(string $className): string
    {
        return $this->getBasePath() . '/database/factories/' . $className . '.php';
    }

    /**
     * Get the base path for the application.
     */
    protected function getBasePath(): string
    {
        return getcwd();
    }

    /**
     * Create the directory for the factory if it doesn't exist.
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

namespace Database\Factories;

use App\Models\{{ modelClass }};
use Reno\Database\Factories\Factory;

/**
 * {{ class }}
 * 
 * Factory for generating {{ modelClass }} model instances.
 * 
 * @extends Factory<{{ modelClass }}>
 */
class {{ class }} extends Factory
{
    /**
     * The name of the factory\'s corresponding model.
     */
    protected string $model = {{ modelClass }}::class;

    /**
     * Define the model\'s default state.
     */
    public function definition(): array
    {
        return [
            // Define your model attributes here
            // Example attributes:
            /*
            \'name\' => $this->faker->name(),
            \'email\' => $this->faker->unique()->safeEmail(),
            \'email_verified_at\' => $this->faker->dateTime(),
            \'password\' => bcrypt(\'password\'), // Default password
            \'created_at\' => $this->faker->dateTimeBetween(\'-1 year\', \'now\'),
            \'updated_at\' => function (array $attributes) {
                return $this->faker->dateTimeBetween($attributes[\'created_at\'], \'now\');
            },
            */
        ];
    }

    /**
     * Indicate that the model should be active.
     */
    public function active(): static
    {
        return $this->state(function (array $attributes) {
            return [
                \'status\' => \'active\',
            ];
        });
    }

    /**
     * Indicate that the model should be inactive.
     */
    public function inactive(): static
    {
        return $this->state(function (array $attributes) {
            return [
                \'status\' => \'inactive\',
            ];
        });
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterMaking(function ({{ modelClass }} ${{ modelVariable }}) {
            // Perform actions after making the model instance
        })->afterCreating(function ({{ modelClass }} ${{ modelVariable }}) {
            // Perform actions after creating the model in database
        });
    }
}';
    }

    /**
     * Populate the stub with the class name and model information.
     */
    protected function populateStub(string $stub, string $className, string $modelClass): string
    {
        $modelVariable = $this->getModelVariable($modelClass);

        $replacements = [
            '{{ class }}' => $className,
            '{{ modelClass }}' => $modelClass,
            '{{ modelVariable }}' => $modelVariable,
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $stub);
    }

    /**
     * Get the model variable name.
     */
    protected function getModelVariable(string $modelClass): string
    {
        return Str::camel($modelClass);
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