<?php

declare(strict_types=1);

namespace Reno\Database\Migrations;

use Closure;
use Throwable;
use RuntimeException;
use Reno\Foundation\Application;
use Reno\Contracts\Database\ConnectionInterface;
use Reno\Database\Migrations\Migration;
use Reno\Database\Migrations\MigrationRepository;
use Reno\Support\Collection;
use Reno\Support\Str;

/**
 * Migrator
 * 
 * Handles the execution of database migrations.
 */
class Migrator
{
    /**
     * The migration repository implementation.
     */
    protected MigrationRepository $repository;

    /**
     * The filesystem instance.
     */
    protected $files;

    /**
     * The application instance.
     */
    protected Application $container;

    /**
     * The name of the default connection.
     */
    protected ?string $connection = null;

    /**
     * The paths to all of the migration files.
     */
    protected array $paths = [];

    /**
     * The output interface implementation.
     */
    protected $output;

    /**
     * Create a new migrator instance.
     */
    public function __construct(MigrationRepository $repository, Application $container, $files = null)
    {
        $this->repository = $repository;
        $this->container = $container;
        $this->files = $files;
    }

    /**
     * Run the pending migrations.
     */
    public function run(array $paths = [], array $options = []): array
    {
        // Once we grab all of the migration files for the path, we will compare them
        // against the migrations that have already been run for this package then
        // run each of the outstanding migrations against a database connection.
        $files = $this->getMigrationFiles($paths);

        $this->requireFiles($migrations = $this->pendingMigrations(
            $files, $this->repository->getRan()
        ));

        // Once we have all these migrations that are outstanding we are ready to run
        // we will go ahead and run them "up". This will execute each migration as
        // an operation against a database. Then we'll return this list of them.
        $this->runPending($migrations, $options);

        return $migrations;
    }

    /**
     * Get the migration files in a given path.
     */
    public function getMigrationFiles(array $paths): array
    {
        return Collection::make($paths)->flatMap(function ($path) {
            if ($this->files) {
                return $this->files->glob($path.'/*_*.php');
            }
            return glob($path.'/*_*.php') ?: [];
        })->filter()->values()->keyBy(function ($file) {
            return $this->getMigrationName($file);
        })->sortBy(function ($file, $key) {
            return $key;
        })->all();
    }

    /**
     * Run an array of migrations.
     */
    public function runPending(array $migrations, array $options = []): void
    {
        // First we will just make sure that there are any migrations to run. If there
        // aren't, we will just make a note of it to the developer so they're aware
        // that all of the migrations have been run against this database system.
        if (count($migrations) === 0) {
            $this->fireMigrationEvent('nothing_to_migrate');
            return;
        }

        // Next, we will get the next batch number for the migrations so we can insert
        // correct batch number in the database migrations repository when we store
        // each migration's execution. We will also extract a few of the options.
        $batch = $this->repository->getNextBatchNumber();

        $pretend = $options['pretend'] ?? false;

        $step = $options['step'] ?? false;

        // Once we have the array of migrations, we will spin through them and run the
        // migrations "up" so the changes are made to the databases. We'll then log
        // that the migration was run so we don't repeat it next time we execute.
        foreach ($migrations as $file) {
            $this->runUp($file, $batch, $pretend);

            if ($step) {
                $batch++;
            }
        }
    }

    /**
     * Run "up" a migration instance.
     */
    protected function runUp(string $file, int $batch, bool $pretend): void
    {
        // First we will resolve a "real" instance of the migration class from this
        // migration file name. Once we have the instances we can run the actual
        // command such as "up" or "down", or we can just simulate the action.
        $migration = $this->resolvePath($file);

        $name = $this->getMigrationName($file);

        if ($pretend) {
            $this->pretendToRun($migration, 'up');
            return;
        }

        $this->runMigration($migration, 'up');

        // Once we have run a migrations class, we will log that it was run in this
        // repository so that we don't try to run it next time we do a migration
        // in the application. A migration repository keeps the migrate order.
        $this->repository->log($name, $batch);

        $this->fireMigrationEvent('migrated', [$file, $batch]);
    }

    /**
     * Rollback the last migration operation.
     */
    public function rollback(array $paths = [], array $options = []): array
    {
        // We want to pull in the last batch of migrations that ran on the previous
        // migration operation. We'll then reverse those migrations and run each
        // of them "down" to reverse the last migration "operation" which ran.
        $migrations = $this->getMigrationsForRollback($options);

        if (count($migrations) === 0) {
            $this->fireMigrationEvent('nothing_to_rollback');
            return [];
        }

        return $this->rollbackMigrations($migrations, $paths, $options);
    }

    /**
     * Get the migrations for a rollback operation.
     */
    protected function getMigrationsForRollback(array $options): array
    {
        if (($steps = $options['step'] ?? 0) > 0) {
            return $this->repository->getMigrations($steps);
        }

        return $this->repository->getLast();
    }

    /**
     * Rollback the given migrations.
     */
    protected function rollbackMigrations(array $migrations, array $paths, array $options): array
    {
        $rolledBack = [];

        $this->requireFiles($files = $this->getMigrationFiles($paths));

        // Next we will run through all of the migrations and call the "down" method
        // which will reverse each migration in order. This getLast method on the
        // repository already returns these migration's names in reverse order.
        foreach ($migrations as $migration) {
            $migration = (object) $migration;

            if (!$file = ($files[$migration->migration] ?? null)) {
                $this->fireMigrationEvent('migration_not_found', [$migration->migration]);
                continue;
            }

            $rolledBack[] = $file;

            $this->runDown($file, $migration, $options['pretend'] ?? false);
        }

        return $rolledBack;
    }

    /**
     * Rolls all of the currently applied migrations back.
     */
    public function reset(array $paths = [], bool $pretend = false): array
    {
        // Next, we will reverse the migration list so we can run them back in the
        // correct order for resetting this database. This will allow us to get
        // the database back into its "empty" state ready for the migrations.
        $migrations = array_reverse($this->repository->getRan());

        if (count($migrations) === 0) {
            $this->fireMigrationEvent('nothing_to_rollback');
            return [];
        }

        return $this->resetMigrations($migrations, $paths, $pretend);
    }

    /**
     * Reset the given migrations.
     */
    protected function resetMigrations(array $migrations, array $paths, bool $pretend): array
    {
        // Since the getRan method that retrieves the migration name just gives us the
        // migration name, we will format the names into objects with the name as a
        // property on the objects so that we can pass it to the rollback method.
        $migrations = collect($migrations)->map(function ($m) {
            return (object) ['migration' => $m];
        })->all();

        return $this->rollbackMigrations(
            $migrations, $paths, compact('pretend')
        );
    }
    /**
     * Run "down" a migration instance.
     */
    protected function runDown(string $file, object $migration, bool $pretend): void
    {
        $instance = $this->resolvePath($file);

        $name = $this->getMigrationName($file);

        if ($pretend) {
            $this->pretendToRun($instance, 'down');
            return;
        }

        $this->runMigration($instance, 'down');

        // Once we have successfully run the migration "down" we will remove it from
        // the migration repository so it will be considered to have not been run
        // by the application then will be able to fire by any later operation.
        $this->repository->delete($migration);

        $this->fireMigrationEvent('rolled_back', [$file]);
    }

    /**
     * Run a migration inside a transaction if the database supports it.
     */
    protected function runMigration(Migration $migration, string $method): void
    {
        $connection = $this->resolveConnection($migration->getConnection());

        $callback = function () use ($migration, $method) {
            if (method_exists($migration, $method)) {
                $migration->{$method}();
            }
        };

        $this->getSchemaGrammar($connection)->supportsSchemaTransactions()
            && $migration->withinTransaction()
                ? $connection->transaction($callback)
                : $callback();
    }

    /**
     * Pretend to run the migrations.
     */
    protected function pretendToRun(Migration $migration, string $method): void
    {
        foreach ($this->getQueries($migration, $method) as $query) {
            $name = get_class($migration);

            $this->fireMigrationEvent('migration_pretended', [$name, $query]);
        }
    }

    /**
     * Get all of the queries that would be run for a migration.
     */
    protected function getQueries(Migration $migration, string $method): array
    {
        // Now that we have the connections we can resolve it and pretend to run the
        // queries against the database returning the array of raw SQL statements
        // that would get fired against the database system for this migration.
        $db = $this->resolveConnection($migration->getConnection());

        return $db->pretend(function () use ($migration, $method) {
            if (method_exists($migration, $method)) {
                $migration->{$method}();
            }
        });
    }

    /**
     * Resolve a migration instance from a file.
     */
    public function resolvePath(string $path): Migration
    {
        $class = $this->getClassName($path);

        if (class_exists($class)) {
            $migration = new $class;
        } else {
            // If the class doesn't exist, require the file and try again
            require_once $path;
            $migration = new $class;
        }

        $migration->setSchema($this->resolveConnection($migration->getConnection())->getSchemaBuilder());

        return $migration;
    }

    /**
     * Get the name of the migration.
     */
    public function getMigrationName(string $path): string
    {
        return str_replace('.php', '', basename($path));
    }

    /**
     * Get the class name from migration file path.
     */
    protected function getClassName(string $path): string
    {
        $name = $this->getMigrationName($path);
        
        // Remove the timestamp prefix (e.g., "2024_01_01_000000_")
        $class = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $name);
        
        // Convert snake_case to StudlyCase
        return Str::studly($class);
    }

    /**
     * Register a custom migration path.
     */
    public function path(string $path): void
    {
        $this->paths = array_unique(array_merge($this->paths, [$path]));
    }

    /**
     * Get all of the custom migration paths.
     */
    public function paths(): array
    {
        return $this->paths;
    }

    /**
     * Get the default connection name.
     */
    public function getConnection(): ?string
    {
        return $this->connection;
    }

    /**
     * Set the default connection name.
     */
    public function setConnection(string $name): void
    {
        if (!is_null($name)) {
            $this->repository->setSource($name);
        }

        $this->connection = $name;
    }

    /**
     * Resolve the database connection instance.
     */
    public function resolveConnection(?string $connection): ConnectionInterface
    {
        return $this->container->make('db')->connection($connection ?: $this->connection);
    }

    /**
     * Get the schema grammar out of a migration connection.
     */
    protected function getSchemaGrammar(ConnectionInterface $connection)
    {
        return $connection->getSchemaGrammar() ?? $connection->withSchemaGrammar();
    }

    /**
     * Get the migration repository instance.
     */
    public function getRepository(): MigrationRepository
    {
        return $this->repository;
    }

    /**
     * Determine if the migration repository exists.
     */
    public function repositoryExists(): bool
    {
        return $this->repository->repositoryExists();
    }

    /**
     * Determine if any migrations have been run.
     */
    public function hasRunAnyMigrations(): bool
    {
        return $this->repositoryExists() && count($this->repository->getRan()) > 0;
    }

    /**
     * Delete the migration repository.
     */
    public function deleteRepository(): void
    {
        $this->repository->deleteRepository();
    }

    /**
     * Get the file system instance.
     */
    public function getFilesystem()
    {
        return $this->files;
    }

    /**
     * Set the output implementation that should be used by the console.
     */
    public function setOutput($output): static
    {
        $this->output = $output;

        return $this;
    }

    /**
     * Write a string as information output.
     */
    protected function write(string $string): void
    {
        if ($this->output) {
            $this->output->write($string);
        }
    }

    /**
     * Raise a given event and call the listeners.
     */
    protected function fireMigrationEvent(string $event, array $data = []): void
    {
        // Fire migration events if event dispatcher is available
        if (method_exists($this->container, 'make') && $this->container->bound('events')) {
            $this->container->make('events')->dispatch('migration.' . $event, $data);
        }
    }

    /**
     * Get the migrations that have not yet run.
     */
    protected function pendingMigrations(array $files, array $ran): array
    {
        return Collection::make($files)
            ->reject(function ($file, $migration) use ($ran) {
                return in_array($migration, $ran);
            })->values()->all();
    }

    /**
     * Require in all the migration files in a given path.
     */
    public function requireFiles(array $files): void
    {
        foreach ($files as $file) {
            require_once $file;
        }
    }
}