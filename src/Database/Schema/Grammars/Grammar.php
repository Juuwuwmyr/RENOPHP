<?php

declare(strict_types=1);

namespace Reno\Database\Schema\Grammars;

use Reno\Contracts\Database\SchemaGrammarInterface;
use Reno\Contracts\Database\BlueprintInterface;
use Reno\Contracts\Database\CommandInterface;
use Reno\Contracts\Database\ColumnDefinitionInterface;
use Reno\Database\Query\Expression;

/**
 * Schema Grammar
 * 
 * Base grammar for compiling database schema operations.
 */
class Grammar implements SchemaGrammarInterface
{
    /**
     * The possible column modifiers.
     */
    protected array $modifiers = [
        'Unsigned', 'Nullable', 'Default', 'Comment', 'After', 'First'
    ];

    /**
     * The possible column serials.
     */
    protected array $serials = ['bigInteger', 'integer', 'mediumInteger', 'smallInteger', 'tinyInteger'];

    /**
     * The grammar table prefix.
     */
    protected string $tablePrefix = '';

    /**
     * Compile a create table command.
     */
    public function compileCreate(BlueprintInterface $blueprint, CommandInterface $command): string
    {
        $sql = $this->compileCreateTable(
            $blueprint, $command
        );

        // Once we have the primary create table clause, we can add the encoding and
        // collation clauses to the SQL statement as they are not currently part
        // of the basic table creation statement and need to be added to it.
        $sql = $this->compileCreateEncoding($sql, $blueprint, $command);

        return $this->compileCreateEngine($sql, $blueprint, $command);
    }

    /**
     * Create the main create table clause.
     */
    protected function compileCreateTable(BlueprintInterface $blueprint, CommandInterface $command): string
    {
        return sprintf('%s table %s (%s)',
            $blueprint->temporary ? 'create temporary' : 'create',
            $this->wrapTable($blueprint),
            implode(', ', $this->getColumns($blueprint))
        );
    }

    /**
     * Append the character set specifications to a command.
     */
    protected function compileCreateEncoding(string $sql, BlueprintInterface $blueprint, CommandInterface $command): string
    {
        if (isset($blueprint->charset)) {
            $sql .= ' default character set '.$blueprint->charset;
        } elseif (!is_null($charset = $command->charset)) {
            $sql .= ' default character set '.$charset;
        }

        if (isset($blueprint->collation)) {
            $sql .= " collate '{$blueprint->collation}'";
        } elseif (!is_null($collation = $command->collation)) {
            $sql .= " collate '{$collation}'";
        }

        return $sql;
    }

    /**
     * Append the engine specifications to a command.
     */
    protected function compileCreateEngine(string $sql, BlueprintInterface $blueprint, CommandInterface $command): string
    {
        if (isset($blueprint->engine)) {
            return $sql.' engine = '.$blueprint->engine;
        } elseif (!is_null($engine = $command->engine)) {
            return $sql.' engine = '.$engine;
        }

        return $sql;
    }

    /**
     * Compile a drop table command.
     */
    public function compileDrop(BlueprintInterface $blueprint, CommandInterface $command): string
    {
        return 'drop table '.$this->wrapTable($blueprint);
    }

    /**
     * Compile a drop table (if exists) command.
     */
    public function compileDropIfExists(BlueprintInterface $blueprint, CommandInterface $command): string
    {
        return 'drop table if exists '.$this->wrapTable($blueprint);
    }

    /**
     * Compile a drop column command.
     */
    public function compileDropColumn(BlueprintInterface $blueprint, CommandInterface $command): string
    {
        $columns = $this->prefixArray('drop', $this->wrapArray($command->columns));

        return 'alter table '.$this->wrapTable($blueprint).' '.implode(', ', $columns);
    }

    /**
     * Compile a drop primary key command.
     */
    public function compileDropPrimary(BlueprintInterface $blueprint, CommandInterface $command): string
    {
        return 'alter table '.$this->wrapTable($blueprint).' drop primary key';
    }

    /**
     * Compile a drop unique key command.
     */
    public function compileDropUnique(BlueprintInterface $blueprint, CommandInterface $command): string
    {
        $index = $this->wrap($command->index);

        return "alter table {$this->wrapTable($blueprint)} drop index {$index}";
    }

    /**
     * Compile a drop index command.
     */
    public function compileDropIndex(BlueprintInterface $blueprint, CommandInterface $command): string
    {
        $index = $this->wrap($command->index);

        return "alter table {$this->wrapTable($blueprint)} drop index {$index}";
    }

    /**
     * Compile a drop spatial index command.
     */
    public function compileDropSpatialIndex(BlueprintInterface $blueprint, CommandInterface $command): string
    {
        return $this->compileDropIndex($blueprint, $command);
    }

    /**
     * Compile a drop foreign key command.
     */
    public function compileDropForeign(BlueprintInterface $blueprint, CommandInterface $command): string
    {
        $index = $this->wrap($command->index);

        return "alter table {$this->wrapTable($blueprint)} drop foreign key {$index}";
    }
    /**
     * Compile a rename table command.
     */
    public function compileRename(BlueprintInterface $blueprint, CommandInterface $command): string
    {
        $from = $this->wrapTable($blueprint);

        return "rename table {$from} to ".$this->wrapTable($command->to);
    }

    /**
     * Compile a rename index command.
     */
    public function compileRenameIndex(BlueprintInterface $blueprint, CommandInterface $command): string
    {
        return sprintf('alter table %s rename index %s to %s',
            $this->wrapTable($blueprint),
            $this->wrap($command->from),
            $this->wrap($command->to)
        );
    }

    /**
     * Compile the query to determine if a table exists.
     */
    public function compileTableExists(): string
    {
        return "select * from information_schema.tables where table_schema = ? and table_name = ? and table_type = 'BASE TABLE'";
    }

    /**
     * Compile the query to determine the list of columns.
     */
    public function compileColumnListing(string $table): string
    {
        return "select column_name as `column_name` from information_schema.columns where table_schema = database() and table_name = ?";
    }

    /**
     * Get the SQL for the column data type.
     */
    public function getType(ColumnDefinitionInterface $column): string
    {
        return $this->{'type'.ucfirst($column->type)}($column);
    }

    /**
     * Create the column definition for a char type.
     */
    protected function typeChar(ColumnDefinitionInterface $column): string
    {
        return "char({$column->length})";
    }

    /**
     * Create the column definition for a string type.
     */
    protected function typeString(ColumnDefinitionInterface $column): string
    {
        return "varchar({$column->length})";
    }

    /**
     * Create the column definition for a text type.
     */
    protected function typeText(ColumnDefinitionInterface $column): string
    {
        return 'text';
    }

    /**
     * Create the column definition for a medium text type.
     */
    protected function typeMediumText(ColumnDefinitionInterface $column): string
    {
        return 'mediumtext';
    }

    /**
     * Create the column definition for a long text type.
     */
    protected function typeLongText(ColumnDefinitionInterface $column): string
    {
        return 'longtext';
    }

    /**
     * Create the column definition for an integer type.
     */
    protected function typeInteger(ColumnDefinitionInterface $column): string
    {
        return 'int';
    }

    /**
     * Create the column definition for a big integer type.
     */
    protected function typeBigInteger(ColumnDefinitionInterface $column): string
    {
        return 'bigint';
    }

    /**
     * Create the column definition for a medium integer type.
     */
    protected function typeMediumInteger(ColumnDefinitionInterface $column): string
    {
        return 'mediumint';
    }

    /**
     * Create the column definition for a small integer type.
     */
    protected function typeSmallInteger(ColumnDefinitionInterface $column): string
    {
        return 'smallint';
    }

    /**
     * Create the column definition for a tiny integer type.
     */
    protected function typeTinyInteger(ColumnDefinitionInterface $column): string
    {
        return 'tinyint';
    }

    /**
     * Create the column definition for a float type.
     */
    protected function typeFloat(ColumnDefinitionInterface $column): string
    {
        return isset($column->total, $column->places)
                    ? "float({$column->total}, {$column->places})"
                    : 'float';
    }

    /**
     * Create the column definition for a double type.
     */
    protected function typeDouble(ColumnDefinitionInterface $column): string
    {
        return isset($column->total, $column->places)
                    ? "double({$column->total}, {$column->places})"
                    : 'double';
    }

    /**
     * Create the column definition for a decimal type.
     */
    protected function typeDecimal(ColumnDefinitionInterface $column): string
    {
        return "decimal({$column->total}, {$column->places})";
    }

    /**
     * Create the column definition for a boolean type.
     */
    protected function typeBoolean(ColumnDefinitionInterface $column): string
    {
        return 'tinyint(1)';
    }

    /**
     * Create the column definition for an enumeration type.
     */
    protected function typeEnum(ColumnDefinitionInterface $column): string
    {
        return sprintf('enum(%s)', implode(', ', array_map([$this, 'quoteString'], $column->allowed)));
    }

    /**
     * Create the column definition for a set enumeration type.
     */
    protected function typeSet(ColumnDefinitionInterface $column): string
    {
        return sprintf('set(%s)', implode(', ', array_map([$this, 'quoteString'], $column->allowed)));
    }

    /**
     * Create the column definition for a json type.
     */
    protected function typeJson(ColumnDefinitionInterface $column): string
    {
        return 'json';
    }

    /**
     * Create the column definition for a jsonb type.
     */
    protected function typeJsonb(ColumnDefinitionInterface $column): string
    {
        return 'json';
    }

    /**
     * Create the column definition for a date type.
     */
    protected function typeDate(ColumnDefinitionInterface $column): string
    {
        return 'date';
    }

    /**
     * Create the column definition for a date-time type.
     */
    protected function typeDateTime(ColumnDefinitionInterface $column): string
    {
        $columnType = 'datetime';

        if ($column->precision) {
            $columnType .= "({$column->precision})";
        }

        return $columnType;
    }

    /**
     * Create the column definition for a date-time (with time zone) type.
     */
    protected function typeDateTimeTz(ColumnDefinitionInterface $column): string
    {
        return $this->typeDateTime($column);
    }

    /**
     * Create the column definition for a time type.
     */
    protected function typeTime(ColumnDefinitionInterface $column): string
    {
        return 'time'.($column->precision ? "({$column->precision})" : '');
    }

    /**
     * Create the column definition for a time (with time zone) type.
     */
    protected function typeTimeTz(ColumnDefinitionInterface $column): string
    {
        return $this->typeTime($column);
    }

    /**
     * Create the column definition for a timestamp type.
     */
    protected function typeTimestamp(ColumnDefinitionInterface $column): string
    {
        $columnType = 'timestamp';

        if ($column->precision) {
            $columnType .= "({$column->precision})";
        }

        return $columnType;
    }

    /**
     * Create the column definition for a timestamp (with time zone) type.
     */
    protected function typeTimestampTz(ColumnDefinitionInterface $column): string
    {
        return $this->typeTimestamp($column);
    }

    /**
     * Create the column definition for a year type.
     */
    protected function typeYear(ColumnDefinitionInterface $column): string
    {
        return 'year';
    }

    /**
     * Create the column definition for a binary type.
     */
    protected function typeBinary(ColumnDefinitionInterface $column): string
    {
        return 'blob';
    }

    /**
     * Create the column definition for a uuid type.
     */
    protected function typeUuid(ColumnDefinitionInterface $column): string
    {
        return 'char(36)';
    }

    /**
     * Create the column definition for an IP address type.
     */
    protected function typeIpAddress(ColumnDefinitionInterface $column): string
    {
        return 'varchar(45)';
    }

    /**
     * Create the column definition for a MAC address type.
     */
    protected function typeMacAddress(ColumnDefinitionInterface $column): string
    {
        return 'varchar(17)';
    }
    /**
     * Get the columns for the schema.
     */
    protected function getColumns(BlueprintInterface $blueprint): array
    {
        $columns = [];

        foreach ($blueprint->getAddedColumns() as $column) {
            // Each of the column types has their own compiler functions, which are used
            // to build the SQL for the column definitions. This lets us easily add
            // and modify the various column definitions without changing anything.
            $sql = $this->wrap($column) . ' ' . $this->getType($column);

            $columns[] = $this->addModifiers($sql, $blueprint, $column);
        }

        return $columns;
    }

    /**
     * Add the column modifiers to the definition.
     */
    protected function addModifiers(string $sql, BlueprintInterface $blueprint, ColumnDefinitionInterface $column): string
    {
        foreach ($this->modifiers as $modifier) {
            if (method_exists($this, $method = "modify{$modifier}")) {
                $sql .= $this->{$method}($blueprint, $column);
            }
        }

        return $sql;
    }

    /**
     * Get the SQL for an unsigned column modifier.
     */
    protected function modifyUnsigned(BlueprintInterface $blueprint, ColumnDefinitionInterface $column): string
    {
        if ($column->unsigned) {
            return ' unsigned';
        }

        return '';
    }

    /**
     * Get the SQL for a nullable column modifier.
     */
    protected function modifyNullable(BlueprintInterface $blueprint, ColumnDefinitionInterface $column): string
    {
        return $column->nullable ? ' null' : ' not null';
    }

    /**
     * Get the SQL for a default column modifier.
     */
    protected function modifyDefault(BlueprintInterface $blueprint, ColumnDefinitionInterface $column): string
    {
        if (!is_null($column->default)) {
            return ' default '.$this->getDefaultValue($column->default);
        }

        return '';
    }

    /**
     * Get the SQL for a comment column modifier.
     */
    protected function modifyComment(BlueprintInterface $blueprint, ColumnDefinitionInterface $column): string
    {
        if (!is_null($column->comment)) {
            return " comment '".$column->comment."'";
        }

        return '';
    }

    /**
     * Get the SQL for an "after" column modifier.
     */
    protected function modifyAfter(BlueprintInterface $blueprint, ColumnDefinitionInterface $column): string
    {
        if (!is_null($column->after)) {
            return ' after '.$this->wrap($column->after);
        }

        return '';
    }

    /**
     * Get the SQL for a "first" column modifier.
     */
    protected function modifyFirst(BlueprintInterface $blueprint, ColumnDefinitionInterface $column): string
    {
        return $column->first ? ' first' : '';
    }

    /**
     * Wrap a single string in keyword identifiers.
     */
    public function wrap(mixed $value): string
    {
        if ($value instanceof Expression) {
            return $value->getValue();
        }

        // If the value being wrapped has a column alias we will need to separate out
        // the pieces so we can wrap each of the segments of the expression on its
        // own, and then join these both back together using the "as" connector.
        if (strpos(strtolower($value), ' as ') !== false) {
            return $this->wrapAliasedValue($value);
        }

        return $this->wrapSegments(explode('.', $value));
    }

    /**
     * Wrap a value that has an alias.
     */
    protected function wrapAliasedValue(string $value): string
    {
        $segments = preg_split('/\s+as\s+/i', $value);

        return $this->wrap($segments[0]).' as '.$this->wrapValue($segments[1]);
    }

    /**
     * Wrap the given value segments.
     */
    protected function wrapSegments(array $segments): string
    {
        return collect($segments)->map(function ($segment, $key) use ($segments) {
            return $key == 0 && count($segments) > 1
                        ? $this->wrapTable($segment)
                        : $this->wrapValue($segment);
        })->implode('.');
    }

    /**
     * Wrap a single string in keyword identifiers.
     */
    protected function wrapValue(string $value): string
    {
        if ($value !== '*') {
            return '`'.str_replace('`', '``', $value).'`';
        }

        return $value;
    }

    /**
     * Wrap a table in keyword identifiers.
     */
    public function wrapTable(mixed $table): string
    {
        if ($table instanceof BlueprintInterface) {
            $table = $table->getTable();
        }

        return $this->wrap($this->tablePrefix.$table);
    }

    /**
     * Wrap an array of values.
     */
    public function wrapArray(array $values): array
    {
        return array_map([$this, 'wrap'], $values);
    }

    /**
     * Format a value so that it can be used in "default" clauses.
     */
    public function getDefaultValue(mixed $value): string
    {
        if ($value instanceof Expression) {
            return $value->getValue();
        }

        return is_bool($value) ? "'".(int) $value."'" : "'".(string) $value."'";
    }

    /**
     * Add a prefix to an array of values.
     */
    public function prefixArray(string $prefix, array $values): array
    {
        return array_map(function ($value) use ($prefix) {
            return $prefix.' '.$value;
        }, $values);
    }

    /**
     * Quote the given string literal.
     */
    public function quoteString(mixed $value): string
    {
        if (is_array($value)) {
            return implode(', ', array_map([$this, 'quoteString'], $value));
        }

        return "'".$value."'";
    }

    /**
     * Get the grammar's table prefix.
     */
    public function getTablePrefix(): string
    {
        return $this->tablePrefix;
    }

    /**
     * Set the grammar's table prefix.
     */
    public function setTablePrefix(string $prefix): void
    {
        $this->tablePrefix = $prefix;
    }
}