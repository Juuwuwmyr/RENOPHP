<?php

declare(strict_types=1);

namespace Horizon\Contracts\Database;

use Closure;

/**
 * Blueprint Interface
 * 
 * Defines the contract for database schema blueprints.
 */
interface BlueprintInterface
{
    /**
     * Execute the blueprint against the database.
     */
    public function build(ConnectionInterface $connection, SchemaGrammarInterface $grammar): void;

    /**
     * Get the raw SQL statements for the blueprint.
     */
    public function toSql(ConnectionInterface $connection, SchemaGrammarInterface $grammar): array;

    /**
     * Add a new command to the blueprint.
     */
    public function addCommand(string $name, array $parameters = []): CommandInterface;

    /**
     * Create a new auto-incrementing big integer (8-byte) column on the table.
     */
    public function id(string $column = 'id'): ColumnDefinitionInterface;

    /**
     * Create a new auto-incrementing integer (4-byte) column on the table.
     */
    public function increments(string $column): ColumnDefinitionInterface;

    /**
     * Create a new auto-incrementing big integer (8-byte) column on the table.
     */
    public function bigIncrements(string $column): ColumnDefinitionInterface;

    /**
     * Create a new char column on the table.
     */
    public function char(string $column, int $length = 255): ColumnDefinitionInterface;

    /**
     * Create a new string column on the table.
     */
    public function string(string $column, int $length = 255): ColumnDefinitionInterface;

    /**
     * Create a new text column on the table.
     */
    public function text(string $column): ColumnDefinitionInterface;

    /**
     * Create a new medium text column on the table.
     */
    public function mediumText(string $column): ColumnDefinitionInterface;

    /**
     * Create a new long text column on the table.
     */
    public function longText(string $column): ColumnDefinitionInterface;

    /**
     * Create a new integer (4-byte) column on the table.
     */
    public function integer(string $column, bool $autoIncrement = false, bool $unsigned = false): ColumnDefinitionInterface;

    /**
     * Create a new big integer (8-byte) column on the table.
     */
    public function bigInteger(string $column, bool $autoIncrement = false, bool $unsigned = false): ColumnDefinitionInterface;

    /**
     * Create a new medium integer (3-byte) column on the table.
     */
    public function mediumInteger(string $column, bool $autoIncrement = false, bool $unsigned = false): ColumnDefinitionInterface;

    /**
     * Create a new small integer (2-byte) column on the table.
     */
    public function smallInteger(string $column, bool $autoIncrement = false, bool $unsigned = false): ColumnDefinitionInterface;

    /**
     * Create a new tiny integer (1-byte) column on the table.
     */
    public function tinyInteger(string $column, bool $autoIncrement = false, bool $unsigned = false): ColumnDefinitionInterface;

    /**
     * Create a new unsigned integer (4-byte) column on the table.
     */
    public function unsignedInteger(string $column, bool $autoIncrement = false): ColumnDefinitionInterface;

    /**
     * Create a new unsigned big integer (8-byte) column on the table.
     */
    public function unsignedBigInteger(string $column, bool $autoIncrement = false): ColumnDefinitionInterface;

    /**
     * Create a new unsigned medium integer (3-byte) column on the table.
     */
    public function unsignedMediumInteger(string $column, bool $autoIncrement = false): ColumnDefinitionInterface;

    /**
     * Create a new unsigned small integer (2-byte) column on the table.
     */
    public function unsignedSmallInteger(string $column, bool $autoIncrement = false): ColumnDefinitionInterface;

    /**
     * Create a new unsigned tiny integer (1-byte) column on the table.
     */
    public function unsignedTinyInteger(string $column, bool $autoIncrement = false): ColumnDefinitionInterface;

    /**
     * Create a new float column on the table.
     */
    public function float(string $column, int $total = 8, int $places = 2): ColumnDefinitionInterface;

    /**
     * Create a new double column on the table.
     */
    public function double(string $column, ?int $total = null, ?int $places = null): ColumnDefinitionInterface;

    /**
     * Create a new decimal column on the table.
     */
    public function decimal(string $column, int $total = 8, int $places = 2): ColumnDefinitionInterface;

    /**
     * Create a new boolean column on the table.
     */
    public function boolean(string $column): ColumnDefinitionInterface;

    /**
     * Create a new enum column on the table.
     */
    public function enum(string $column, array $allowed): ColumnDefinitionInterface;

    /**
     * Create a new set column on the table.
     */
    public function set(string $column, array $allowed): ColumnDefinitionInterface;

    /**
     * Create a new json column on the table.
     */
    public function json(string $column): ColumnDefinitionInterface;

    /**
     * Create a new jsonb column on the table.
     */
    public function jsonb(string $column): ColumnDefinitionInterface;

    /**
     * Create a new date column on the table.
     */
    public function date(string $column): ColumnDefinitionInterface;

    /**
     * Create a new date-time column on the table.
     */
    public function dateTime(string $column, int $precision = 0): ColumnDefinitionInterface;

    /**
     * Create a new date-time column (with time zone) on the table.
     */
    public function dateTimeTz(string $column, int $precision = 0): ColumnDefinitionInterface;

    /**
     * Create a new time column on the table.
     */
    public function time(string $column, int $precision = 0): ColumnDefinitionInterface;

    /**
     * Create a new time column (with time zone) on the table.
     */
    public function timeTz(string $column, int $precision = 0): ColumnDefinitionInterface;

    /**
     * Create a new timestamp column on the table.
     */
    public function timestamp(string $column, int $precision = 0): ColumnDefinitionInterface;

    /**
     * Create a new timestamp (with time zone) column on the table.
     */
    public function timestampTz(string $column, int $precision = 0): ColumnDefinitionInterface;

    /**
     * Add nullable creation and update timestamps to the table.
     */
    public function timestamps(int $precision = 0): void;

    /**
     * Add nullable creation and update timestamps to the table.
     */
    public function nullableTimestamps(int $precision = 0): void;

    /**
     * Add creation and update timestampTz columns to the table.
     */
    public function timestampsTz(int $precision = 0): void;

    /**
     * Add a "deleted at" timestamp for the table.
     */
    public function softDeletes(string $column = 'deleted_at', int $precision = 0): ColumnDefinitionInterface;

    /**
     * Add a "deleted at" timestampTz for the table.
     */
    public function softDeletesTz(string $column = 'deleted_at', int $precision = 0): ColumnDefinitionInterface;

    /**
     * Create a new year column on the table.
     */
    public function year(string $column): ColumnDefinitionInterface;

    /**
     * Create a new binary column on the table.
     */
    public function binary(string $column): ColumnDefinitionInterface;

    /**
     * Create a new uuid column on the table.
     */
    public function uuid(string $column): ColumnDefinitionInterface;

    /**
     * Create a new IP address column on the table.
     */
    public function ipAddress(string $column): ColumnDefinitionInterface;

    /**
     * Create a new MAC address column on the table.
     */
    public function macAddress(string $column): ColumnDefinitionInterface;

    /**
     * Create a new geometry column on the table.
     */
    public function geometry(string $column): ColumnDefinitionInterface;

    /**
     * Create a new point column on the table.
     */
    public function point(string $column, ?int $srid = null): ColumnDefinitionInterface;

    /**
     * Create a new linestring column on the table.
     */
    public function lineString(string $column): ColumnDefinitionInterface;

    /**
     * Create a new polygon column on the table.
     */
    public function polygon(string $column): ColumnDefinitionInterface;

    /**
     * Create a new geometrycollection column on the table.
     */
    public function geometryCollection(string $column): ColumnDefinitionInterface;

    /**
     * Create a new multipoint column on the table.
     */
    public function multiPoint(string $column): ColumnDefinitionInterface;

    /**
     * Create a new multilinestring column on the table.
     */
    public function multiLineString(string $column): ColumnDefinitionInterface;

    /**
     * Create a new multipolygon column on the table.
     */
    public function multiPolygon(string $column): ColumnDefinitionInterface;

    /**
     * Add the proper columns for a polymorphic table.
     */
    public function morphs(string $name, ?string $indexName = null): void;

    /**
     * Add nullable columns for a polymorphic table.
     */
    public function nullableMorphs(string $name, ?string $indexName = null): void;

    /**
     * Adds the `remember_token` column to the table.
     */
    public function rememberToken(): ColumnDefinitionInterface;

    /**
     * Specify an index for the table.
     */
    public function index(array|string $columns, ?string $name = null, ?string $algorithm = null): CommandInterface;

    /**
     * Specify a spatial index for the table.
     */
    public function spatialIndex(array|string $columns, ?string $name = null): CommandInterface;

    /**
     * Specify a primary key for the table.
     */
    public function primary(array|string $columns, ?string $name = null, ?string $algorithm = null): CommandInterface;

    /**
     * Specify a unique index for the table.
     */
    public function unique(array|string $columns, ?string $name = null, ?string $algorithm = null): CommandInterface;

    /**
     * Specify a foreign key for the table.
     */
    public function foreign(array|string $columns, ?string $name = null): ForeignKeyDefinitionInterface;

    /**
     * Drop an index from the table.
     */
    public function dropIndex(array|string $index): CommandInterface;

    /**
     * Drop a spatial index from the table.
     */
    public function dropSpatialIndex(array|string $index): CommandInterface;

    /**
     * Drop a primary key from the table.
     */
    public function dropPrimary(array|string $index = null): CommandInterface;

    /**
     * Drop a unique index from the table.
     */
    public function dropUnique(array|string $index): CommandInterface;

    /**
     * Drop a foreign key from the table.
     */
    public function dropForeign(array|string $index): CommandInterface;

    /**
     * Drop the given columns from the table.
     */
    public function dropColumn(array|string $columns): CommandInterface;

    /**
     * Rename a column on the table.
     */
    public function renameColumn(string $from, string $to): CommandInterface;

    /**
     * Drop the timestamps from the table.
     */
    public function dropTimestamps(): void;

    /**
     * Drop the timestampsTz from the table.
     */
    public function dropTimestampsTz(): void;

    /**
     * Drop the soft deletes column from the table.
     */
    public function dropSoftDeletes(string $column = 'deleted_at'): void;

    /**
     * Drop the soft deletes column from the table.
     */
    public function dropSoftDeletesTz(string $column = 'deleted_at'): void;

    /**
     * Drop the remember token from the table.
     */
    public function dropRememberToken(): void;

    /**
     * Drop the polymorphic columns from the table.
     */
    public function dropMorphs(string $name, ?string $indexName = null): void;

    /**
     * Rename the table to a given name.
     */
    public function rename(string $to): CommandInterface;

    /**
     * Set the table the blueprint describes.
     */
    public function create(): CommandInterface;

    /**
     * Indicate that the table needs to be created.
     */
    public function drop(): CommandInterface;

    /**
     * Indicate that the table should be dropped if it exists.
     */
    public function dropIfExists(): CommandInterface;

    /**
     * Indicate that the given columns should be dropped.
     */
    public function dropColumns(array $columns): void;

    /**
     * Add a new column to the blueprint.
     */
    public function addColumn(string $type, string $name, array $parameters = []): ColumnDefinitionInterface;

    /**
     * Remove a column from the schema blueprint.
     */
    public function removeColumn(string $name): static;

    /**
     * Get the table the blueprint describes.
     */
    public function getTable(): string;

    /**
     * Get the columns on the blueprint.
     */
    public function getColumns(): array;

    /**
     * Get the commands on the blueprint.
     */
    public function getCommands(): array;

    /**
     * Get the columns on the blueprint that should be added.
     */
    public function getAddedColumns(): array;

    /**
     * Get the columns on the blueprint that should be changed.
     */
    public function getChangedColumns(): array;
}