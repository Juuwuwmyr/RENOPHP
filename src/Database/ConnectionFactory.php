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
 * Connection Factory
 * 
 * Factory for creating database connections.
 */
class ConnectionFactory
{
    /**
     * The IoC container instance.
     */
    protected Application $container;

    /**
     * Create a new connection factory instance.
     */
    public function __construct(Application $container)
    {
        $this->container = $container;
    }

    /**
     * Establish a PDO connection based on the configuration.
     */
    public function make(array $config, ?string $name = null): ConnectionInterface
    {
        $config = $this->parseConfig($config, $name);

        if (isset($config['read'])) {
            return $this->createReadWriteConnection($config, $name);
        }

        return $this->createSingleConnection($config, $name);
    }

    /**
     * Parse and prepare the database configuration.
     */
    protected function parseConfig(array $config, ?string $name): array
    {
        return tap($config, function (&$config) use ($name) {
            $config['prefix'] = $config['prefix'] ?? '';
            $config['name'] = $name;
        });
    }

    /**
     * Create a single database connection instance.
     */
    protected function createSingleConnection(array $config, ?string $name): ConnectionInterface
    {
        $pdo = $this->createPdoResolver($config);

        return $this->createConnection(
            $config['driver'], $pdo, $config['database'] ?? '', $config['prefix'] ?? '', $config
        );
    }

    /**
     * Create a read / write database connection instance.
     */
    protected function createReadWriteConnection(array $config, ?string $name): ConnectionInterface
    {
        $connection = $this->createSingleConnection($this->getWriteConfig($config), $name);

        return $connection->setReadPdo($this->createPdoResolver($this->getReadConfig($config)));
    }

    /**
     * Get the read configuration for a read / write connection.
     */
    protected function getReadConfig(array $config): array
    {
        return $this->mergeReadWriteConfig(
            $config, $config['read']
        );
    }

    /**
     * Get the write configuration for a read / write connection.
     */
    protected function getWriteConfig(array $config): array
    {
        return $this->mergeReadWriteConfig(
            $config, $config['write'] ?? []
        );
    }

    /**
     * Merge a configuration for a read / write connection.
     */
    protected function mergeReadWriteConfig(array $config, array $merge): array
    {
        return array_merge($config, $merge, [
            'read' => null,
            'write' => null,
        ]);
    }

    /**
     * Create a PDO resolver.
     */
    protected function createPdoResolver(array $config): Closure
    {
        return array_key_exists('host', $config)
                    ? $this->createPdoResolverWithHosts($config)
                    : $this->createPdoResolverWithoutHosts($config);
    }

    /**
     * Create a PDO resolver with hosts in configuration.
     */
    protected function createPdoResolverWithHosts(array $config): Closure
    {
        return function () use ($config) {
            foreach ((array) $config['host'] as $key => $host) {
                $config['host'] = $host;

                try {
                    return $this->createConnector($config)->connect($config);
                } catch (PDOException $e) {
                    if (count((array) $config['host']) === 1 || is_int($key + 1) && count((array) $config['host']) <= $key + 1) {
                        throw $e;
                    }

                    continue;
                }
            }
        };
    }

    /**
     * Create a PDO resolver without hosts in configuration.
     */
    protected function createPdoResolverWithoutHosts(array $config): Closure
    {
        return fn () => $this->createConnector($config)->connect($config);
    }

    /**
     * Create a connector instance based on the configuration.
     */
    public function createConnector(array $config): ConnectorInterface
    {
        if (!isset($config['driver'])) {
            throw new InvalidArgumentException('A driver must be specified.');
        }

        if ($this->container->bound($key = "db.connector.{$config['driver']}")) {
            return $this->container->make($key);
        }

        return match ($config['driver']) {
            'mysql' => new MySqlConnector,
            'pgsql' => new PostgresConnector,
            'sqlite' => new SQLiteConnector,
            'sqlsrv' => new SqlServerConnector,
            default => throw new InvalidArgumentException("Unsupported driver [{$config['driver']}]."),
        };
    }

    /**
     * Create a new connection instance.
     */
    protected function createConnection(string $driver, Closure $connection, string $database, string $prefix = '', array $config = []): ConnectionInterface
    {
        if ($resolver = Connection::getResolver($driver)) {
            return $resolver($connection, $database, $prefix, $config);
        }

        return match ($driver) {
            'mysql' => new MySqlConnection($connection, $database, $prefix, $config),
            'pgsql' => new PostgresConnection($connection, $database, $prefix, $config),
            'sqlite' => new SQLiteConnection($connection, $database, $prefix, $config),
            'sqlsrv' => new SqlServerConnection($connection, $database, $prefix, $config),
            default => throw new InvalidArgumentException("Unsupported driver [{$driver}]."),
        };
    }
}