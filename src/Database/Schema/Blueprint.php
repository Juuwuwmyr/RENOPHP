<?php

declare(strict_types=1);

namespace Horizon\Database\Schema;

use Closure;
use BadMethodCallException;
use Horizon\Contracts\Database\ConnectionInterface;
use Horizon\Contracts\Database\SchemaGrammarInterface;
use Horizon\Contracts\Database\BlueprintInterface;
use Horizon\Contracts\Database\CommandInterface;
use Horizon\Contracts\Database\ColumnDefinitionInterface;
use Horizon\Contracts\Database\ForeignKeyDefinitionInterface;
use Horizon\Database\Schema\ColumnDefinition;
use Horizon\Database\Schema\ForeignKeyDefinition;

/**
 * Blueprint
 * 
 * Represents a database table schema blueprint.
 */
class Blueprint implements BlueprintInterface
{
    /**
     * The table the blueprint describes.
     */
    protected string $table;

    /**
     * The prefix for the table.
     */
    protected string $prefix;

    /**
     * The columns that should be added to the table.
     */
    protected array $columns = [];

    /**
     * The commands that should be run for the table.
     */
    protected array $commands = [];

    /**
     * The storage engine for the table.
     */
    public ?string $engine = null;

    /**
     * The default character set for the table.
     */
    public ?string $charset = null;

    /**
     * The collation for the table.
     */
    public ?string $collation = null;

    /**
     * Whether to make the table temporary.
     */
    public bool $temporary = false;

    /**
     * The column to add new columns after.
     */
    public ?string $after = null;

    /**
     * Create a new schema blueprint.
     */
    public function __construct(string $table, ?Closure $callback = null, string $prefix = '')
    {
        $this->table = $table;
        $this->prefix = $prefix;

        if (!is_null($callback)) {
            $callback($this);
        }
    }

    /**
     * Execute the blueprint against the database.
     */
    public function build(ConnectionInterface $connection, SchemaGrammarInterface $grammar): void
    {
        foreach ($this->toSql($connection, $grammar) as $statement) {
            $connection->statement($statement);
        }
    }

    /**
     * Get the raw SQL statements for the blueprint.
     */
    public function toSql(ConnectionInterface $connection, SchemaGrammarInterface $grammar): array
    {
        $this->addImpliedCommands($grammar);

        $statements = [];

        // Each type of command has a corresponding compiler function on the schema
        // grammar which is used to build the necessary SQL statements to build
        // the blueprint element, so we'll just call that compilers function.
        foreach ($this->commands as $command) {
            $method = 'compile' . ucfirst($command->name);

            if (method_exists($grammar, $method)) {
                if ($sql = $grammar->$method($this, $command)) {
                    $statements = array_merge($statements, (array) $sql);
                }
            }
        }

        return $statements;
    }

    /**
     * Add the commands that are implied by the blueprint's state.
     */
    protected function addImpliedCommands(SchemaGrammarInterface $grammar): void
    {
        if (count($this->getAddedColumns()) > 0 && !$this->creating()) {
            array_unshift($this->commands, $this->createCommand('add'));
        }

        if (count($this->getChangedColumns()) > 0 && !$this->creating()) {
            array_unshift($this->commands, $this->createCommand('change'));
        }

        $this->addFluentIndexes();
    }

    /**
     * Add the index commands fluently specified on columns.
     */
    protected function addFluentIndexes(): void
    {
        foreach ($this->columns as $column) {
            foreach (['primary', 'unique', 'index', 'spatialIndex'] as $index) {
                // If the index has been specified on the given column, but is simply
                // equal to "true" (boolean), no name has been specified for this
                // index so the index method can be called without a name.
                if ($column->{$index} === true) {
                    $this->$index($column->name);
                    $column->{$index} = false;
                    continue;
                }

                // If the index has been specified on the given column, and it has a
                // string value, we'll go ahead and call the index method and pass
                // the name for the index since one was specified on this column.
                elseif (isset($column->{$index})) {
                    $this->$index($column->name, $column->{$index});
                    $column->{$index} = false;
                }
            }
        }
    }
    /**
     * Determine if the blueprint has a create command.
     */
    protected function creating(): bool
    {
        return collect($this->commands)->contains(function ($command) {
            return $command->name === 'create';
        });
    }

    /**
     * Add a new command to the blueprint.
     */
    public function addCommand(string $name, array $parameters = []): CommandInterface
    {
        $this->commands[] = $command = $this->createCommand($name, $parameters);

        return $command;
    }

    /**
     * Create a new Fluent command.
     */
    protected function createCommand(string $name, array $parameters = []): CommandInterface
    {
        return new Command($name, $parameters);
    }

    /**
     * Add a new column to the blueprint.
     */
    public function addColumn(string $type, string $name, array $parameters = []): ColumnDefinitionInterface
    {
        $this->columns[] = $column = new ColumnDefinition(
            array_merge(compact('type', 'name'), $parameters)
        );

        return $column;
    }

    /**
     * Remove a column from the schema blueprint.
     */
    public function removeColumn(string $name): static
    {
        $this->columns = array_values(array_filter($this->columns, function ($c) use ($name) {
            return $c->name != $name;
        }));

        return $this;
    }

    /**
     * Create a new auto-incrementing big integer (8-byte) column on the table.
     */
    public function id(string $column = 'id'): ColumnDefinitionInterface
    {
        return $this->bigIncrements($column);
    }

    /**
     * Create a new auto-incrementing integer (4-byte) column on the table.
     */
    public function increments(string $column): ColumnDefinitionInterface
    {
        return $this->unsignedInteger($column, true);
    }

    /**
     * Create a new auto-incrementing big integer (8-byte) column on the table.
     */
    public function bigIncrements(string $column): ColumnDefinitionInterface
    {
        return $this->unsignedBigInteger($column, true);
    }

    /**
     * Create a new char column on the table.
     */
    public function char(string $column, int $length = 255): ColumnDefinitionInterface
    {
        return $this->addColumn('char', $column, compact('length'));
    }

    /**
     * Create a new string column on the table.
     */
    public function string(string $column, int $length = 255): ColumnDefinitionInterface
    {
        return $this->addColumn('string', $column, compact('length'));
    }

    /**
     * Create a new text column on the table.
     */
    public function text(string $column): ColumnDefinitionInterface
    {
        return $this->addColumn('text', $column);
    }

    /**
     * Create a new medium text column on the table.
     */
    public function mediumText(string $column): ColumnDefinitionInterface
    {
        return $this->addColumn('mediumText', $column);
    }

    /**
     * Create a new long text column on the table.
     */
    public function longText(string $column): ColumnDefinitionInterface
    {
        return $this->addColumn('longText', $column);
    }

    /**
     * Create a new integer (4-byte) column on the table.
     */
    public function integer(string $column, bool $autoIncrement = false, bool $unsigned = false): ColumnDefinitionInterface
    {
        return $this->addColumn('integer', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Create a new big integer (8-byte) column on the table.
     */
    public function bigInteger(string $column, bool $autoIncrement = false, bool $unsigned = false): ColumnDefinitionInterface
    {
        return $this->addColumn('bigInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Create a new medium integer (3-byte) column on the table.
     */
    public function mediumInteger(string $column, bool $autoIncrement = false, bool $unsigned = false): ColumnDefinitionInterface
    {
        return $this->addColumn('mediumInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Create a new small integer (2-byte) column on the table.
     */
    public function smallInteger(string $column, bool $autoIncrement = false, bool $unsigned = false): ColumnDefinitionInterface
    {
        return $this->addColumn('smallInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Create a new tiny integer (1-byte) column on the table.
     */
    public function tinyInteger(string $column, bool $autoIncrement = false, bool $unsigned = false): ColumnDefinitionInterface
    {
        return $this->addColumn('tinyInteger', $column, compact('autoIncrement', 'unsigned'));
    }
    /**
     * Create a new unsigned integer (4-byte) column on the table.
     */
    public function unsignedInteger(string $column, bool $autoIncrement = false): ColumnDefinitionInterface
    {
        return $this->integer($column, $autoIncrement, true);
    }

    /**
     * Create a new unsigned big integer (8-byte) column on the table.
     */
    public function unsignedBigInteger(string $column, bool $autoIncrement = false): ColumnDefinitionInterface
    {
        return $this->bigInteger($column, $autoIncrement, true);
    }

    /**
     * Create a new unsigned medium integer (3-byte) column on the table.
     */
    public function unsignedMediumInteger(string $column, bool $autoIncrement = false): ColumnDefinitionInterface
    {
        return $this->mediumInteger($column, $autoIncrement, true);
    }

    /**
     * Create a new unsigned small integer (2-byte) column on the table.
     */
    public function unsignedSmallInteger(string $column, bool $autoIncrement = false): ColumnDefinitionInterface
    {
        return $this->smallInteger($column, $autoIncrement, true);
    }

    /**
     * Create a new unsigned tiny integer (1-byte) column on the table.
     */
    public function unsignedTinyInteger(string $column, bool $autoIncrement = false): ColumnDefinitionInterface
    {
        return $this->tinyInteger($column, $autoIncrement, true);
    }

    /**
     * Create a new float column on the table.
     */
    public function float(string $column, int $total = 8, int $places = 2): ColumnDefinitionInterface
    {
        return $this->addColumn('float', $column, compact('total', 'places'));
    }

    /**
     * Create a new double column on the table.
     */
    public function double(string $column, ?int $total = null, ?int $places = null): ColumnDefinitionInterface
    {
        return $this->addColumn('double', $column, compact('total', 'places'));
    }

    /**
     * Create a new decimal column on the table.
     */
    public function decimal(string $column, int $total = 8, int $places = 2): ColumnDefinitionInterface
    {
        return $this->addColumn('decimal', $column, compact('total', 'places'));
    }

    /**
     * Create a new boolean column on the table.
     */
    public function boolean(string $column): ColumnDefinitionInterface
    {
        return $this->addColumn('boolean', $column);
    }

    /**
     * Create a new enum column on the table.
     */
    public function enum(string $column, array $allowed): ColumnDefinitionInterface
    {
        return $this->addColumn('enum', $column, compact('allowed'));
    }

    /**
     * Create a new set column on the table.
     */
    public function set(string $column, array $allowed): ColumnDefinitionInterface
    {
        return $this->addColumn('set', $column, compact('allowed'));
    }

    /**
     * Create a new json column on the table.
     */
    public function json(string $column): ColumnDefinitionInterface
    {
        return $this->addColumn('json', $column);
    }

    /**
     * Create a new jsonb column on the table.
     */
    public function jsonb(string $column): ColumnDefinitionInterface
    {
        return $this->addColumn('jsonb', $column);
    }

    /**
     * Create a new date column on the table.
     */
    public function date(string $column): ColumnDefinitionInterface
    {
        return $this->addColumn('date', $column);
    }

    /**
     * Create a new date-time column on the table.
     */
    public function dateTime(string $column, int $precision = 0): ColumnDefinitionInterface
    {
        return $this->addColumn('dateTime', $column, compact('precision'));
    }

    /**
     * Create a new date-time column (with time zone) on the table.
     */
    public function dateTimeTz(string $column, int $precision = 0): ColumnDefinitionInterface
    {
        return $this->addColumn('dateTimeTz', $column, compact('precision'));
    }

    /**
     * Create a new time column on the table.
     */
    public function time(string $column, int $precision = 0): ColumnDefinitionInterface
    {
        return $this->addColumn('time', $column, compact('precision'));
    }

    /**
     * Create a new time column (with time zone) on the table.
     */
    public function timeTz(string $column, int $precision = 0): ColumnDefinitionInterface
    {
        return $this->addColumn('timeTz', $column, compact('precision'));
    }
    /**
     * Create a new timestamp column on the table.
     */
    public function timestamp(string $column, int $precision = 0): ColumnDefinitionInterface
    {
        return $this->addColumn('timestamp', $column, compact('precision'));
    }

    /**
     * Create a new timestamp (with time zone) column on the table.
     */
    public function timestampTz(string $column, int $precision = 0): ColumnDefinitionInterface
    {
        return $this->addColumn('timestampTz', $column, compact('precision'));
    }

    /**
     * Add nullable creation and update timestamps to the table.
     */
    public function timestamps(int $precision = 0): void
    {
        $this->timestamp('created_at', $precision)->nullable();
        $this->timestamp('updated_at', $precision)->nullable();
    }

    /**
     * Add nullable creation and update timestamps to the table.
     */
    public function nullableTimestamps(int $precision = 0): void
    {
        $this->timestamps($precision);
    }

    /**
     * Add creation and update timestampTz columns to the table.
     */
    public function timestampsTz(int $precision = 0): void
    {
        $this->timestampTz('created_at', $precision)->nullable();
        $this->timestampTz('updated_at', $precision)->nullable();
    }

    /**
     * Add a "deleted at" timestamp for the table.
     */
    public function softDeletes(string $column = 'deleted_at', int $precision = 0): ColumnDefinitionInterface
    {
        return $this->timestamp($column, $precision)->nullable();
    }

    /**
     * Add a "deleted at" timestampTz for the table.
     */
    public function softDeletesTz(string $column = 'deleted_at', int $precision = 0): ColumnDefinitionInterface
    {
        return $this->timestampTz($column, $precision)->nullable();
    }

    /**
     * Create a new year column on the table.
     */
    public function year(string $column): ColumnDefinitionInterface
    {
        return $this->addColumn('year', $column);
    }

    /**
     * Create a new binary column on the table.
     */
    public function binary(string $column): ColumnDefinitionInterface
    {
        return $this->addColumn('binary', $column);
    }

    /**
     * Create a new uuid column on the table.
     */
    public function uuid(string $column): ColumnDefinitionInterface
    {
        return $this->addColumn('uuid', $column);
    }

    /**
     * Create a new IP address column on the table.
     */
    public function ipAddress(string $column): ColumnDefinitionInterface
    {
        return $this->addColumn('ipAddress', $column);
    }

    /**
     * Create a new MAC address column on the table.
     */
    public function macAddress(string $column): ColumnDefinitionInterface
    {
        return $this->addColumn('macAddress', $column);
    }

    /**
     * Add the proper columns for a polymorphic table.
     */
    public function morphs(string $name, ?string $indexName = null): void
    {
        $this->string("{$name}_type");
        $this->unsignedBigInteger("{$name}_id");
        $this->index(["{$name}_type", "{$name}_id"], $indexName);
    }

    /**
     * Add nullable columns for a polymorphic table.
     */
    public function nullableMorphs(string $name, ?string $indexName = null): void
    {
        $this->string("{$name}_type")->nullable();
        $this->unsignedBigInteger("{$name}_id")->nullable();
        $this->index(["{$name}_type", "{$name}_id"], $indexName);
    }

    /**
     * Adds the `remember_token` column to the table.
     */
    public function rememberToken(): ColumnDefinitionInterface
    {
        return $this->string('remember_token', 100)->nullable();
    }

    /**
     * Specify an index for the table.
     */
    public function index(array|string $columns, ?string $name = null, ?string $algorithm = null): CommandInterface
    {
        return $this->indexCommand('index', $columns, $name, $algorithm);
    }

    /**
     * Specify a spatial index for the table.
     */
    public function spatialIndex(array|string $columns, ?string $name = null): CommandInterface
    {
        return $this->indexCommand('spatialIndex', $columns, $name);
    }

    /**
     * Specify a primary key for the table.
     */
    public function primary(array|string $columns, ?string $name = null, ?string $algorithm = null): CommandInterface
    {
        return $this->indexCommand('primary', $columns, $name, $algorithm);
    }

    /**
     * Specify a unique index for the table.
     */
    public function unique(array|string $columns, ?string $name = null, ?string $algorithm = null): CommandInterface
    {
        return $this->indexCommand('unique', $columns, $name, $algorithm);
    }
    /**
     * Specify a foreign key for the table.
     */
    public function foreign(array|string $columns, ?string $name = null): ForeignKeyDefinitionInterface
    {
        $command = $this->indexCommand('foreign', $columns, $name);

        return new ForeignKeyDefinition($command);
    }

    /**
     * Create a new drop index command on the blueprint.
     */
    public function dropIndex(array|string $index): CommandInterface
    {
        return $this->dropIndexCommand('dropIndex', $index);
    }

    /**
     * Create a new drop spatial index command on the blueprint.
     */
    public function dropSpatialIndex(array|string $index): CommandInterface
    {
        return $this->dropIndexCommand('dropSpatialIndex', $index);
    }

    /**
     * Create a new drop primary key command on the blueprint.
     */
    public function dropPrimary(array|string $index = null): CommandInterface
    {
        return $this->dropIndexCommand('dropPrimary', $index);
    }

    /**
     * Create a new drop unique key command on the blueprint.
     */
    public function dropUnique(array|string $index): CommandInterface
    {
        return $this->dropIndexCommand('dropUnique', $index);
    }

    /**
     * Create a new drop foreign key command on the blueprint.
     */
    public function dropForeign(array|string $index): CommandInterface
    {
        return $this->dropIndexCommand('dropForeign', $index);
    }

    /**
     * Create a new drop column command on the blueprint.
     */
    public function dropColumn(array|string $columns): CommandInterface
    {
        $columns = is_array($columns) ? $columns : func_get_args();

        return $this->addCommand('dropColumn', compact('columns'));
    }

    /**
     * Create a new rename column command on the blueprint.
     */
    public function renameColumn(string $from, string $to): CommandInterface
    {
        return $this->addCommand('renameColumn', compact('from', 'to'));
    }

    /**
     * Drop the timestamps from the table.
     */
    public function dropTimestamps(): void
    {
        $this->dropColumn('created_at', 'updated_at');
    }

    /**
     * Drop the timestampsTz from the table.
     */
    public function dropTimestampsTz(): void
    {
        $this->dropTimestamps();
    }

    /**
     * Drop the soft deletes column from the table.
     */
    public function dropSoftDeletes(string $column = 'deleted_at'): void
    {
        $this->dropColumn($column);
    }

    /**
     * Drop the soft deletes column from the table.
     */
    public function dropSoftDeletesTz(string $column = 'deleted_at'): void
    {
        $this->dropSoftDeletes($column);
    }

    /**
     * Drop the remember token from the table.
     */
    public function dropRememberToken(): void
    {
        $this->dropColumn('remember_token');
    }

    /**
     * Drop the polymorphic columns from the table.
     */
    public function dropMorphs(string $name, ?string $indexName = null): void
    {
        $this->dropIndex($indexName ?: $this->createIndexName('index', ["{$name}_type", "{$name}_id"]));

        $this->dropColumn("{$name}_type", "{$name}_id");
    }

    /**
     * Rename the table to a given name.
     */
    public function rename(string $to): CommandInterface
    {
        return $this->addCommand('rename', compact('to'));
    }

    /**
     * Indicate that the table needs to be created.
     */
    public function create(): CommandInterface
    {
        return $this->addCommand('create');
    }

    /**
     * Indicate that the table should be dropped.
     */
    public function drop(): CommandInterface
    {
        return $this->addCommand('drop');
    }

    /**
     * Indicate that the table should be dropped if it exists.
     */
    public function dropIfExists(): CommandInterface
    {
        return $this->addCommand('dropIfExists');
    }
    /**
     * Indicate that the given columns should be dropped.
     */
    public function dropColumns(array $columns): void
    {
        $this->dropColumn($columns);
    }

    /**
     * Create a command to add an index to the table.
     */
    protected function indexCommand(string $type, array|string $columns, ?string $index, ?string $algorithm = null): CommandInterface
    {
        $columns = (array) $columns;

        // If no name was specified for this index, we will create one using a basic
        // convention of the table name, followed by the columns, followed by an
        // index type, such as primary or index, which makes the index unique.
        $index = $index ?: $this->createIndexName($type, $columns);

        return $this->addCommand(
            $type, compact('index', 'columns', 'algorithm')
        );
    }

    /**
     * Create a command to drop an index from the table.
     */
    protected function dropIndexCommand(string $type, array|string $index): CommandInterface
    {
        $columns = [];

        // If the given "index" is actually an array of columns, the developer means
        // to drop an index merely by specifying the columns involved without the
        // conventional name, so we will build the index name from the columns.
        if (is_array($index)) {
            $index = $this->createIndexName($type, $columns = $index);
        }

        return $this->indexCommand($type, $columns, $index);
    }

    /**
     * Create a default index name for the table.
     */
    protected function createIndexName(string $type, array $columns): string
    {
        $index = strtolower($this->prefix.$this->table.'_'.implode('_', $columns).'_'.$type);

        return str_replace(['-', '.'], '_', $index);
    }

    /**
     * Get the table the blueprint describes.
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Get the columns on the blueprint.
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Get the commands on the blueprint.
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * Get the columns on the blueprint that should be added.
     */
    public function getAddedColumns(): array
    {
        return array_filter($this->columns, function ($column) {
            return !$column->change;
        });
    }

    /**
     * Get the columns on the blueprint that should be changed.
     */
    public function getChangedColumns(): array
    {
        return array_filter($this->columns, function ($column) {
            return (bool) $column->change;
        });
    }
}