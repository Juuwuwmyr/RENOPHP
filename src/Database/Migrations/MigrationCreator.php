<?php

declare(strict_types=1);

namespace Reno\Database\Migrations;

use Closure;
use InvalidArgumentException;
use Reno\Support\Str;

/**
 * Migration Creator
 * 
 * Creates new migration files from templates.
 */
class MigrationCreator
{
    /**
     * The filesystem instance.
     */
    protected $files;

    /**
     * The registered post create hooks.
     */
    protected array $postCreate = [];

    /**
     * Create a new migration creator instance.
     */
    public function __construct($files = null)
    {
        $this->files = $files;
    }

    /**
     * Create a new migration at the given path.
     */
    public function create(string $name, string $path, ?string $table = null, bool $create = false): string
    {
        $this->ensureMigrationDoesntAlreadyExist($name, $path);

        // First we will get the stub file for the migration, which serves as a type
        // of template for the migration. Once we have those we will populate the
        // various place-holders, save the file, and run the post create event.
        $stub = $this->getStub($table, $create);

        $path = $this->getPath($name, $path);

        $this->files->ensureDirectoryExists(dirname($path));

        $this->files->put($path, $this->populateStub($name, $stub, $table));

        // Next, we will fire any hooks that are supposed to fire after a migration is
        // created. Once that is done we'll be ready to return the full path to the
        // migration file so it can be used however it's needed by the developer.
        $this->firePostCreateHooks($table, $path);

        return $path;
    }

    /**
     * Ensure that a migration with the given name doesn't already exist.
     */
    protected function ensureMigrationDoesntAlreadyExist(string $name, string $migrationPath = null): void
    {
        if (!empty($migrationPath)) {
            $migrationFiles = $this->files->glob($migrationPath.'/*.php');

            foreach ($migrationFiles as $migrationFile) {
                $this->files->requireOnce($migrationFile);
            }
        }

        if (class_exists($className = $this->getClassName($name))) {
            throw new InvalidArgumentException("A {$className} class already exists.");
        }
    }

    /**
     * Get the migration stub file.
     */
    protected function getStub(?string $table, bool $create): string
    {
        if (is_null($table)) {
            $stub = $this->files ? $this->files->get(__DIR__.'/stubs/migration.stub') : $this->getDefaultStub();
        } elseif ($create) {
            $stub = $this->files ? $this->files->get(__DIR__.'/stubs/migration.create.stub') : $this->getCreateStub();
        } else {
            $stub = $this->files ? $this->files->get(__DIR__.'/stubs/migration.update.stub') : $this->getUpdateStub();
        }

        return $stub;
    }

    /**
     * Populate the place-holders in the migration stub.
     */
    protected function populateStub(string $name, string $stub, ?string $table): string
    {
        $stub = str_replace(
            ['DummyClass', '{{ class }}', '{{class}}'],
            $this->getClassName($name), $stub
        );

        // Here we will replace the table place-holders with the table specified by
        // the developer, which is useful for quickly creating a tables creation
        // or update migration from the console instead of typing it manually.
        if (!is_null($table)) {
            $stub = str_replace(
                ['DummyTable', '{{ table }}', '{{table}}'],
                $table, $stub
            );
        }

        return $stub;
    }

    /**
     * Get the class name of a migration name.
     */
    protected function getClassName(string $name): string
    {
        return Str::studly($name);
    }

    /**
     * Get the full path to the migration.
     */
    protected function getPath(string $name, string $path): string
    {
        return $path.'/'.$this->getDatePrefix().'_'.$name.'.php';
    }

    /**
     * Fire the registered post create hooks.
     */
    protected function firePostCreateHooks(?string $table, string $path): void
    {
        foreach ($this->postCreate as $callback) {
            $callback($table, $path);
        }
    }

    /**
     * Register a post migration create hook.
     */
    public function afterCreate(Closure $callback): void
    {
        $this->postCreate[] = $callback;
    }

    /**
     * Get the date prefix for the migration.
     */
    protected function getDatePrefix(): string
    {
        return date('Y_m_d_His');
    }

    /**
     * Get the default migration stub.
     */
    protected function getDefaultStub(): string
    {
        return '<?php

use Reno\Database\Migrations\Migration;
use Reno\Database\Schema\Blueprint;
use Reno\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        //
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};';
    }

    /**
     * Get the create table migration stub.
     */
    protected function getCreateStub(): string
    {
        return '<?php

use Reno\Database\Migrations\Migration;
use Reno\Database\Schema\Blueprint;
use Reno\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(\'{{ table }}\', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(\'{{ table }}\');
    }
};';
    }

    /**
     * Get the update table migration stub.
     */
    protected function getUpdateStub(): string
    {
        return '<?php

use Reno\Database\Migrations\Migration;
use Reno\Database\Schema\Blueprint;
use Reno\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table(\'{{ table }}\', function (Blueprint $table) {
            //
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(\'{{ table }}\', function (Blueprint $table) {
            //
        });
    }
};';
    }

    /**
     * Get the filesystem instance.
     */
    public function getFilesystem()
    {
        return $this->files;
    }
}