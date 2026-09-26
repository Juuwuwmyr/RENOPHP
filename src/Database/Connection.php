<?php

declare(strict_types=1);

namespace Reno\Database;

use PDO;
use Closure;
use Exception;
use Throwable;
use RuntimeException;
use DateTimeInterface;
use Reno\Contracts\Database\ConnectionInterface;
use Reno\Contracts\Database\QueryGrammarInterface;
use Reno\Contracts\Database\SchemaGrammarInterface;
use Reno\Contracts\Database\ProcessorInterface;
use Reno\Database\Query\QueryBuilder;
use Reno\Database\Query\Expression;
use Reno\Database\Schema\Builder as SchemaBuilder;

/**
 * Database Connection
 * 
 * Represents a database connection and provides query execution methods.
 */
abstract class Connection implements ConnectionInterface
{
    /**
     * The active PDO connection.
     */
    protected ?PDO $pdo = null;

    /**
     * The active PDO connection used for reads.
     */
    protected ?PDO $readPdo = null;

    /**
     * The name of the connected database.
     */
    protected string $database;

    /**
     * The database connection configuration options.
     */
    protected array $config = [];

    /**
     * The reconnector instance for the connection.
     */
    protected ?Closure $reconnector = null;

    /**
     * The query grammar implementation.
     */
    protected ?QueryGrammarInterface $queryGrammar = null;

    /**
     * The schema grammar implementation.
     */
    protected ?SchemaGrammarInterface $schemaGrammar = null;

    /**
     * The query post processor implementation.
     */
    protected ?ProcessorInterface $postProcessor = null;

    /**
     * All of the queries run against the connection.
     */
    protected array $queryLog = [];

    /**
     * Indicates whether queries are being logged.
     */
    protected bool $loggingQueries = false;

    /**
     * Indicates if the connection is in a "dry run".
     */
    protected bool $pretending = false;
    /**
     * The number of active transactions.
     */
    protected int $transactions = 0;

    /**
     * The transaction manager instance.
     */
    protected ?DatabaseTransactionsManager $transactionsManager = null;

    /**
     * All of the available clause operators.
     */
    protected array $operators = [
        '=', '<', '>', '<=', '>=', '<>', '!=', '<=>',
        'like', 'like binary', 'not like', 'ilike',
        '&', '|', '^', '<<', '>>', '&~', 'is', 'is not',
        'rlike', 'not rlike', 'regexp', 'not regexp',
        '~', '~*', '!~', '!~*', 'similar to',
        'not similar to', 'not ilike', '~~*', '!~~*',
    ];

    /**
     * The event dispatcher instance.
     */
    protected ?Dispatcher $events = null;

    /**
     * The default fetch mode of the connection.
     */
    protected int $fetchMode = PDO::FETCH_OBJ;

    /**
     * The connection resolvers.
     */
    protected static array $resolvers = [];

    /**
     * Create a new database connection instance.
     */
    public function __construct(Closure|PDO $pdo, string $database = '', string $tablePrefix = '', array $config = [])
    {
        $this->pdo = $pdo instanceof Closure ? null : $pdo;

        $this->database = $database;
        $this->tablePrefix = $tablePrefix;
        $this->config = $config;

        $this->useDefaultQueryGrammar();
        $this->useDefaultPostProcessor();
    }

    /**
     * Set the query grammar to the default implementation.
     */
    public function useDefaultQueryGrammar(): void
    {
        $this->queryGrammar = $this->getDefaultQueryGrammar();
    }

    /**
     * Get the default query grammar instance.
     */
    protected function getDefaultQueryGrammar(): QueryGrammarInterface
    {
        ($grammar = new Query\Grammars\Grammar)->setTablePrefix($this->tablePrefix);

        return $grammar;
    }
    /**
     * Set the schema grammar to the default implementation.
     */
    public function useDefaultSchemaGrammar(): void
    {
        $this->schemaGrammar = $this->getDefaultSchemaGrammar();
    }

    /**
     * Get the default schema grammar instance.
     */
    protected function getDefaultSchemaGrammar(): ?SchemaGrammarInterface
    {
        return null;
    }

    /**
     * Set the query post processor to the default implementation.
     */
    public function useDefaultPostProcessor(): void
    {
        $this->postProcessor = $this->getDefaultPostProcessor();
    }

    /**
     * Get the default post processor instance.
     */
    protected function getDefaultPostProcessor(): ProcessorInterface
    {
        return new Query\Processors\Processor;
    }

    /**
     * Begin a fluent query against a database table.
     */
    public function table(string $table, ?string $as = null): QueryBuilder
    {
        return $this->query()->from($table, $as);
    }

    /**
     * Get a new query builder instance.
     */
    public function query(): QueryBuilder
    {
        return new QueryBuilder(
            $this, $this->getQueryGrammar(), $this->getPostProcessor()
        );
    }

    /**
     * Run a select statement and return a single result.
     */
    public function selectOne(string $query, array $bindings = [], bool $useReadPdo = true): mixed
    {
        $records = $this->select($query, $bindings, $useReadPdo);

        return array_shift($records);
    }