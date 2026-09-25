<?php

declare(strict_types=1);

namespace Horizon\Database;

use PDO;
use Closure;
use InvalidArgumentException;
use Horizon\Foundation\Application;
use Horizon\Contracts\Database\ConnectionInterface;
use Horizon\Database\Connectors\ConnectorInterface;
use Horizon\Database\Connectors\MySqlConnector;
use Horizon\Database\Connectors\PostgresConnector;
use Horizon\Database\Connectors\SQLiteConnector;
use Horizon\Database\Connectors\SqlServerConnector;

/**
 * Database Manager
 * 
 * Manages database connections and provides a factory for creating connections.
 */
class DatabaseManager
{
    /**
     * The application instance.
     */
    protected Application $app;

    /**
     * The database connection factory instance.
     */
    protected ConnectionFactory $factory;

    /**
     * The active connection instances.
     */
    protected array $connections = [];

    /**
     * The custom connection resolvers.
     */
    protected array $extensions = [];

    /**
     * The callback to be executed to reconnect to a database.
     */
    protected ?Closure $reconnector = null;

    /**
     * Create a new database manager instance.
     */
    public function __construct(Application $app, ConnectionFactory $factory)
    {
        $this->app = $app;
        $this->factory = $factory;

        $this->reconnector = function (ConnectionInterface $connection) {
            $this->reconnect($connection->getName());
        };
    }

    /**
     * Get a database connection instance.
     */
    public function connection(?string $name = null): ConnectionInterface
    {
        [$database, $type] = $this->parseConnectionName($name);

        $name = $name ?: $database;

        // If we haven't created this connection, we'll create it based on the config
        // provided in the application. Once we've created the connections we will
        // set the "fetch mode" for PDO which determines the query return types.
        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->configure(
                $this->makeConnection($database), $type
            );
        }

        return $this->connections[$name];
    }

    /**
     * Parse the connection into an array of the name and read / write type.
     */
    protected function parseConnectionName(?string $name): array
    {
        $name = $name ?: $this->getDefaultConnection();

        return str_ends_with($name, ['::read', '::write'])
                    ? explode('::', $name, 2) : [$name, null];
    }

    /**
     * Make the database connection instance.
     */
    protected function makeConnection(string $name): ConnectionInterface
    {
        $config = $this->configuration($name);

        // First we will check by the connection name to see if an extension has been
        // registered specifically for that connection. If it has we will call the
        // Closure and pass it the config allowing it to resolve the connection.
        if (isset($this->extensions[$name])) {
            return call_user_func($this->extensions[$name], $config, $name);
        }

        // Next we will check to see if an extension has been registered for a driver
        // and will call the Closure if so, which allows them to easily add new
        // drivers without having to make any changes to the manager itself.
        if (isset($this->extensions[$driver = $config['driver']])) {
            return call_user_func($this->extensions[$driver], $config, $name);
        }

        return $this->factory->make($config, $name);
    }

    /**
     * Get the configuration for a connection.
     */
    protected function configuration(string $name): array
    {
        $name = $name ?: $this->getDefaultConnection();

        // To get the database connection configuration, we will just pull each of the
        // connection configurations and get the configurations for the given name.
        // If the configuration doesn't exist, we'll throw an exception and bail.
        $connections = $this->app['config']['database.connections'];

        if (!isset($connections[$name])) {
            throw new InvalidArgumentException("Database connection [{$name}] not configured.");
        }

        return $connections[$name];
    }

    /**
     * Configure the PDO connection based on the configuration.
     */
    protected function configure(ConnectionInterface $connection, ?string $type): ConnectionInterface
    {
        $connection = $this->setPdoForType($connection, $type)->setReadWriteType($type);

        // Here we'll set a reconnector function on the PDO connection so that we can
        // reconnect if it gets disconnected. This reconnector can be overridden by
        // an application developer allowing them to build their own reconnector.
        if ($this->app->bound('events')) {
            $connection->setEventDispatcher($this->app['events']);
        }

        // We will also set the fetch mode for each connection. This takes place
        // immediately after we make the database connection to ensure that the
        // developer's preferences are respected by the built connection instance.
        $connection->setFetchMode(
            $this->app['config']->get('database.fetch', PDO::FETCH_OBJ)
        );

        return $connection;
    }

    /**
     * Set the PDO connection for the given type.
     */
    protected function setPdoForType(ConnectionInterface $connection, ?string $type): ConnectionInterface
    {
        if ($type === 'read') {
            $connection->setPdo($connection->getReadPdo());
        } elseif ($type === 'write') {
            $connection->setReadPdo($connection->getPdo());
        }

        return $connection;
    }

    /**
     * Disconnect from the given database and remove from local cache.
     */
    public function purge(?string $name = null): void
    {
        $name = $name ?: $this->getDefaultConnection();

        $this->disconnect($name);

        unset($this->connections[$name]);
    }

    /**
     * Disconnect from the given database.
     */
    public function disconnect(?string $name = null): void
    {
        if (isset($this->connections[$name = $name ?: $this->getDefaultConnection()])) {
            $this->connections[$name]->disconnect();
        }
    }

    /**
     * Reconnect to the given database.
     */
    public function reconnect(?string $name = null): ConnectionInterface
    {
        $this->disconnect($name = $name ?: $this->getDefaultConnection());

        if (!isset($this->connections[$name])) {
            return $this->connection($name);
        }

        return $this->refreshPdoConnections($name);
    }

    /**
     * Refresh the PDO connections on a given connection.
     */
    protected function refreshPdoConnections(string $name): ConnectionInterface
    {
        [$database, $type] = $this->parseConnectionName($name);

        $fresh = $this->configure(
            $this->makeConnection($database), $type
        );

        return $this->connections[$name]
                    ->setPdo($fresh->getRawPdo())
                    ->setReadPdo($fresh->getRawReadPdo());
    }

    /**
     * Get the default connection name.
     */
    public function getDefaultConnection(): string
    {
        return $this->app['config']['database.default'];
    }

    /**
     * Set the default connection name.
     */
    public function setDefaultConnection(string $name): void
    {
        $this->app['config']['database.default'] = $name;
    }

    /**
     * Get all of the support drivers.
     */
    public function supportedDrivers(): array
    {
        return ['mysql', 'pgsql', 'sqlite', 'sqlsrv'];
    }

    /**
     * Get all of the drivers that are actually available.
     */
    public function availableDrivers(): array
    {
        return array_intersect(
            $this->supportedDrivers(),
            str_replace('dblib', 'sqlsrv', PDO::getAvailableDrivers())
        );
    }

    /**
     * Register an extension connection resolver.
     */
    public function extend(string $name, Closure $resolver): void
    {
        $this->extensions[$name] = $resolver;
    }

    /**
     * Remove an extension connection resolver.
     */
    public function forgetExtension(string $name): void
    {
        unset($this->extensions[$name]);
    }

    /**
     * Return all of the created connections.
     */
    public function getConnections(): array
    {
        return $this->connections;
    }

    /**
     * Set the database reconnector callback.
     */
    public function setReconnector(Closure $reconnector): void
    {
        $this->reconnector = $reconnector;
    }

    /**
     * Get the database reconnector callback.
     */
    public function getReconnector(): ?Closure
    {
        return $this->reconnector;
    }

    /**
     * Dynamically pass methods to the default connection.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->connection()->$method(...$parameters);
    }
}