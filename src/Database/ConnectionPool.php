<?php

declare(strict_types=1);

namespace Reno\Database;

use Reno\Contracts\Database\ConnectionInterface;
use Reno\Database\Connection;

/**
 * Database Connection Pool
 * 
 * Manages database connections efficiently by reusing connections
 * and balancing load across multiple database servers.
 */
class ConnectionPool
{
    /**
     * Active connections pool.
     */
    protected array $connections = [];

    /**
     * Available connections queue.
     */
    protected array $available = [];

    /**
     * Connection configuration.
     */
    protected array $config = [];

    /**
     * Maximum connections per pool.
     */
    protected int $maxConnections = 10;

    /**
     * Minimum connections to maintain.
     */
    protected int $minConnections = 2;

    /**
     * Connection timeout in seconds.
     */
    protected int $connectionTimeout = 30;

    /**
     * Pool statistics.
     */
    protected array $stats = [
        'created' => 0,
        'reused' => 0,
        'closed' => 0,
        'errors' => 0,
    ];

    /**
     * Connection health check interval.
     */
    protected int $healthCheckInterval = 300; // 5 minutes

    /**
     * Create a new connection pool.
     */
    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->maxConnections = $config['max_connections'] ?? 10;
        $this->minConnections = $config['min_connections'] ?? 2;
        $this->connectionTimeout = $config['timeout'] ?? 30;
    }

    /**
     * Get a connection from the pool.
     */
    public function getConnection(string $name = 'default'): ConnectionInterface
    {
        // Try to get an available connection
        if (!empty($this->available[$name])) {
            $connectionId = array_pop($this->available[$name]);
            $connection = $this->connections[$name][$connectionId];
            
            // Verify connection is still healthy
            if ($this->isConnectionHealthy($connection)) {
                $this->stats['reused']++;
                return $connection;
            } else {
                // Remove unhealthy connection
                unset($this->connections[$name][$connectionId]);
            }
        }

        // Create a new connection if needed and allowed
        if ($this->canCreateConnection($name)) {
            return $this->createConnection($name);
        }

        // Wait for an available connection (simplified implementation)
        throw new \RuntimeException("No available connections in pool for '{$name}'");
    }

    /**
     * Return a connection to the pool.
     */
    public function releaseConnection(ConnectionInterface $connection, string $name = 'default'): void
    {
        $connectionId = spl_object_hash($connection);
        
        // Add to available queue if connection is healthy and pool not full
        if ($this->isConnectionHealthy($connection) && $this->canReturnToPool($name)) {
            if (!isset($this->available[$name])) {
                $this->available[$name] = [];
            }
            
            $this->available[$name][] = $connectionId;
            $this->connections[$name][$connectionId] = $connection;
        } else {
            // Close connection if pool is full or connection is unhealthy
            $this->closeConnection($connection);
        }
    }

    /**
     * Create a new database connection.
     */
    protected function createConnection(string $name): ConnectionInterface
    {
        $config = $this->config['connections'][$name] ?? $this->config;
        
        try {
            $connection = new Connection($config);
            
            $connectionId = spl_object_hash($connection);
            $this->connections[$name][$connectionId] = $connection;
            
            $this->stats['created']++;
            
            return $connection;
            
        } catch (\Throwable $e) {
            $this->stats['errors']++;
            throw new \RuntimeException("Failed to create connection '{$name}': " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Check if a connection is healthy.
     */
    protected function isConnectionHealthy(ConnectionInterface $connection): bool
    {
        try {
            // Perform a simple query to test connection
            $connection->select('SELECT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Check if we can create a new connection.
     */
    protected function canCreateConnection(string $name): bool
    {
        $currentConnections = isset($this->connections[$name]) ? count($this->connections[$name]) : 0;
        return $currentConnections < $this->maxConnections;
    }

    /**
     * Check if connection can be returned to pool.
     */
    protected function canReturnToPool(string $name): bool
    {
        $availableCount = isset($this->available[$name]) ? count($this->available[$name]) : 0;
        return $availableCount < $this->maxConnections;
    }

    /**
     * Close a database connection.
     */
    protected function closeConnection(ConnectionInterface $connection): void
    {
        try {
            $connection->disconnect();
            $this->stats['closed']++;
        } catch (\Throwable $e) {
            // Log error but don't throw
            $this->stats['errors']++;
        }
    }

    /**
     * Warm up the connection pool.
     */
    public function warmUp(string $name = 'default'): void
    {
        for ($i = 0; $i < $this->minConnections; $i++) {
            try {
                $connection = $this->createConnection($name);
                $this->releaseConnection($connection, $name);
            } catch (\Throwable $e) {
                // Continue creating other connections
                break;
            }
        }
    }

    /**
     * Perform health check on all connections.
     */
    public function healthCheck(): array
    {
        $results = [];
        
        foreach ($this->connections as $poolName => $connections) {
            $healthy = 0;
            $unhealthy = 0;
            
            foreach ($connections as $connectionId => $connection) {
                if ($this->isConnectionHealthy($connection)) {
                    $healthy++;
                } else {
                    $unhealthy++;
                    // Remove unhealthy connection
                    unset($this->connections[$poolName][$connectionId]);
                    
                    // Remove from available queue if present
                    if (isset($this->available[$poolName])) {
                        $key = array_search($connectionId, $this->available[$poolName]);
                        if ($key !== false) {
                            unset($this->available[$poolName][$key]);
                        }
                    }
                }
            }
            
            $results[$poolName] = [
                'healthy' => $healthy,
                'unhealthy' => $unhealthy,
                'total' => $healthy + $unhealthy,
                'available' => isset($this->available[$poolName]) ? count($this->available[$poolName]) : 0,
            ];
        }
        
        return $results;
    }

    /**
     * Get pool statistics.
     */
    public function getStats(): array
    {
        $poolStats = [];
        
        foreach ($this->connections as $poolName => $connections) {
            $poolStats[$poolName] = [
                'total_connections' => count($connections),
                'available_connections' => isset($this->available[$poolName]) ? count($this->available[$poolName]) : 0,
                'active_connections' => count($connections) - (isset($this->available[$poolName]) ? count($this->available[$poolName]) : 0),
            ];
        }
        
        return array_merge($this->stats, [
            'pools' => $poolStats,
            'efficiency' => $this->calculateEfficiency(),
        ]);
    }

    /**
     * Calculate pool efficiency.
     */
    protected function calculateEfficiency(): float
    {
        $total = $this->stats['created'] + $this->stats['reused'];
        
        if ($total === 0) {
            return 0;
        }
        
        return round(($this->stats['reused'] / $total) * 100, 2);
    }

    /**
     * Close all connections in the pool.
     */
    public function closeAll(): void
    {
        foreach ($this->connections as $poolName => $connections) {
            foreach ($connections as $connection) {
                $this->closeConnection($connection);
            }
        }
        
        $this->connections = [];
        $this->available = [];
    }

    /**
     * Resize the pool.
     */
    public function resize(int $maxConnections, int $minConnections = null): void
    {
        $this->maxConnections = $maxConnections;
        
        if ($minConnections !== null) {
            $this->minConnections = $minConnections;
        }
        
        // Close excess connections if new max is smaller
        foreach ($this->connections as $poolName => $connections) {
            if (count($connections) > $maxConnections) {
                $excess = array_slice($connections, $maxConnections);
                foreach ($excess as $connectionId => $connection) {
                    $this->closeConnection($connection);
                    unset($this->connections[$poolName][$connectionId]);
                    
                    // Remove from available queue
                    if (isset($this->available[$poolName])) {
                        $key = array_search($connectionId, $this->available[$poolName]);
                        if ($key !== false) {
                            unset($this->available[$poolName][$key]);
                        }
                    }
                }
            }
        }
    }

    /**
     * Get configuration.
     */
    public function getConfig(): array
    {
        return [
            'max_connections' => $this->maxConnections,
            'min_connections' => $this->minConnections,
            'connection_timeout' => $this->connectionTimeout,
            'health_check_interval' => $this->healthCheckInterval,
        ];
    }

    /**
     * Execute a callback with a pooled connection.
     */
    public function executeWithConnection(callable $callback, string $poolName = 'default'): mixed
    {
        $connection = $this->getConnection($poolName);
        
        try {
            return $callback($connection);
        } finally {
            $this->releaseConnection($connection, $poolName);
        }
    }

    /**
     * Get load balancing statistics.
     */
    public function getLoadBalancingStats(): array
    {
        $stats = [];
        
        foreach ($this->connections as $poolName => $connections) {
            $stats[$poolName] = [
                'connection_count' => count($connections),
                'load_percentage' => count($connections) > 0 ? (count($connections) / $this->maxConnections) * 100 : 0,
                'health_status' => $this->getPoolHealthStatus($poolName),
            ];
        }
        
        return $stats;
    }

    /**
     * Get health status for a specific pool.
     */
    protected function getPoolHealthStatus(string $poolName): string
    {
        if (!isset($this->connections[$poolName])) {
            return 'empty';
        }
        
        $connections = $this->connections[$poolName];
        $availableCount = isset($this->available[$poolName]) ? count($this->available[$poolName]) : 0;
        
        $utilizationRate = (count($connections) - $availableCount) / $this->maxConnections;
        
        if ($utilizationRate < 0.5) {
            return 'healthy';
        } elseif ($utilizationRate < 0.8) {
            return 'moderate';
        } else {
            return 'high';
        }
    }

    /**
     * Schedule automatic maintenance tasks.
     */
    public function scheduleMaintenance(): void
    {
        // In a real implementation, this would set up background tasks
        // For now, we'll just perform immediate maintenance
        $this->performMaintenance();
    }

    /**
     * Perform pool maintenance.
     */
    protected function performMaintenance(): void
    {
        // Health check all connections
        $healthResults = $this->healthCheck();
        
        // Ensure minimum connections are maintained
        foreach ($this->config['connections'] ?? ['default' => $this->config] as $poolName => $config) {
            $currentCount = isset($this->connections[$poolName]) ? count($this->connections[$poolName]) : 0;
            
            if ($currentCount < $this->minConnections) {
                $needed = $this->minConnections - $currentCount;
                for ($i = 0; $i < $needed && $this->canCreateConnection($poolName); $i++) {
                    try {
                        $connection = $this->createConnection($poolName);
                        $this->releaseConnection($connection, $poolName);
                    } catch (\Throwable $e) {
                        break;
                    }
                }
            }
        }
    }
}