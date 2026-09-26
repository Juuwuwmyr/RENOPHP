<?php

declare(strict_types=1);

namespace Reno\Database;

use Reno\Contracts\Database\ConnectionInterface;
use Reno\Database\Eloquent\Model;
use Reno\Support\Collection;
use InvalidArgumentException;

/**
 * Base Database Seeder
 * 
 * Provides functionality for seeding database tables with test or initial data.
 */
abstract class Seeder
{
    /**
     * The database connection instance.
     */
    protected ConnectionInterface $connection;

    /**
     * The console command instance.
     */
    protected $command;

    /**
     * The container instance.
     */
    protected $container;

    /**
     * Seed the given connection from the given path.
     */
    abstract public function run(): void;

    /**
     * Create a new seeder instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Seed the given connection from the given path.
     */
    public function call($class, bool $silent = false, array $parameters = []): static
    {
        $classes = is_array($class) ? $class : [$class];

        foreach ($classes as $class) {
            $seeder = $this->resolve($class);

            $name = get_class($seeder);

            if ($silent === false && isset($this->command)) {
                $this->command->getOutput()->writeln("<comment>Seeding:</comment> {$name}");
            }

            $startTime = microtime(true);

            $seeder->__invoke($parameters);

            $runTime = round((microtime(true) - $startTime) * 1000, 2);

            if ($silent === false && isset($this->command)) {
                $this->command->getOutput()->writeln("<info>Seeded:</info>  {$name} ({$runTime}ms)");
            }
        }

        return $this;
    }

    /**
     * Silently seed the given connection from the given path.
     */
    public function callSilent($class, array $parameters = []): static
    {
        return $this->call($class, true, $parameters);
    }

    /**
     * Resolve an instance of the given seeder class.
     */
    protected function resolve(string $class): Seeder
    {
        if (isset($this->container)) {
            $instance = $this->container->make($class);
        } else {
            $instance = new $class;
        }

        if (isset($this->command)) {
            $instance->setCommand($this->command);
        }

        if (isset($this->container)) {
            $instance->setContainer($this->container);
        }

        return $instance;
    }

    /**
     * Set the IoC container instance.
     */
    public function setContainer($container): static
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Set the console command instance.
     */
    public function setCommand($command): static
    {
        $this->command = $command;

        return $this;
    }

    /**
     * Run the database seeder.
     */
    public function __invoke(array $parameters = []): void
    {
        if (!method_exists($this, 'run')) {
            throw new InvalidArgumentException('Method [run] missing from '.get_class($this));
        }

        $this->run(...$parameters);
    }

    /**
     * Get a model factory builder for the given class.
     */
    protected function factory(string $class): Factory
    {
        if (isset($this->container)) {
            return $this->container->make(DatabaseManager::class)->factory($class);
        }

        throw new InvalidArgumentException('Container instance not set on seeder.');
    }

    /**
     * Create multiple model instances and persist them to the database.
     */
    protected function create(string $model, int $count = 1, array $attributes = []): Collection|Model
    {
        return $this->factory($model)->count($count)->create($attributes);
    }

    /**
     * Create multiple model instances without persisting to database.
     */
    protected function make(string $model, int $count = 1, array $attributes = []): Collection|Model
    {
        return $this->factory($model)->count($count)->make($attributes);
    }

    /**
     * Truncate the given tables.
     */
    protected function truncate(string ...$tables): void
    {
        $connection = $this->getConnection();

        foreach ($tables as $table) {
            $connection->table($table)->truncate();
        }
    }

    /**
     * Disable foreign key checks.
     */
    protected function disableForeignKeyChecks(): void
    {
        $connection = $this->getConnection();
        
        $driver = $connection->getDriverName();
        
        switch ($driver) {
            case 'mysql':
                $connection->statement('SET FOREIGN_KEY_CHECKS=0');
                break;
            case 'pgsql':
                $connection->statement('SET session_replication_role = replica');
                break;
            case 'sqlite':
                $connection->statement('PRAGMA foreign_keys = OFF');
                break;
        }
    }

    /**
     * Enable foreign key checks.
     */
    protected function enableForeignKeyChecks(): void
    {
        $connection = $this->getConnection();
        
        $driver = $connection->getDriverName();
        
        switch ($driver) {
            case 'mysql':
                $connection->statement('SET FOREIGN_KEY_CHECKS=1');
                break;
            case 'pgsql':
                $connection->statement('SET session_replication_role = DEFAULT');
                break;
            case 'sqlite':
                $connection->statement('PRAGMA foreign_keys = ON');
                break;
        }
    }

    /**
     * Run the seeder with foreign key checks disabled.
     */
    protected function withoutForeignKeyChecks(callable $callback): void
    {
        $this->disableForeignKeyChecks();
        
        try {
            $callback();
        } finally {
            $this->enableForeignKeyChecks();
        }
    }

    /**
     * Get the database connection.
     */
    protected function getConnection(): ConnectionInterface
    {
        if (isset($this->connection)) {
            return $this->connection;
        }

        if (isset($this->container)) {
            return $this->container->make('db')->connection();
        }

        throw new InvalidArgumentException('Database connection not available in seeder.');
    }

    /**
     * Set the database connection.
     */
    public function setConnection(ConnectionInterface $connection): static
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * Get a random element from an array.
     */
    protected function randomElement(array $elements)
    {
        return $elements[array_rand($elements)];
    }

    /**
     * Get multiple random elements from an array.
     */
    protected function randomElements(array $elements, int $count): array
    {
        shuffle($elements);
        return array_slice($elements, 0, $count);
    }

    /**
     * Generate a random number between min and max.
     */
    protected function randomNumber(int $min = 1, int $max = 100): int
    {
        return rand($min, $max);
    }

    /**
     * Generate a random boolean value.
     */
    protected function randomBoolean(float $probability = 0.5): bool
    {
        return (mt_rand() / mt_getrandmax()) < $probability;
    }

    /**
     * Generate a random date between two dates.
     */
    protected function randomDate(string $start = '-1 year', string $end = 'now'): string
    {
        $startTime = strtotime($start);
        $endTime = strtotime($end);
        
        $randomTime = rand($startTime, $endTime);
        
        return date('Y-m-d H:i:s', $randomTime);
    }
}