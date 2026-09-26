<?php

declare(strict_types=1);

namespace Reno\Contracts\Database;

/**
 * Schema Grammar Interface
 * 
 * Defines the contract for database schema grammar implementations.
 */
interface SchemaGrammarInterface
{
    /**
     * Compile a create table command.
     */
    public function compileCreate(BlueprintInterface $blueprint, CommandInterface $command): string;

    /**
     * Compile a drop table command.
     */
    public function compileDrop(BlueprintInterface $blueprint, CommandInterface $command): string;

    /**
     * Compile a drop table (if exists) command.
     */
    public function compileDropIfExists(BlueprintInterface $blueprint, CommandInterface $command): string;

    /**
     * Compile a drop column command.
     */
    public function compileDropColumn(BlueprintInterface $blueprint, CommandInterface $command): string;

    /**
     * Compile a drop primary key command.
     */
    public function compileDropPrimary(BlueprintInterface $blueprint, CommandInterface $command): string;

    /**
     * Compile a drop unique key command.
     */
    public function compileDropUnique(BlueprintInterface $blueprint, CommandInterface $command): string;

    /**
     * Compile a drop index command.
     */
    public function compileDropIndex(BlueprintInterface $blueprint, CommandInterface $command): string;

    /**
     * Compile a drop foreign key command.
     */
    public function compileDropForeign(BlueprintInterface $blueprint, CommandInterface $command): string;

    /**
     * Compile a rename table command.
     */
    public function compileRename(BlueprintInterface $blueprint, CommandInterface $command): string;

    /**
     * Compile a rename index command.
     */
    public function compileRenameIndex(BlueprintInterface $blueprint, CommandInterface $command): string;

    /**
     * Compile the query to determine the list of tables.
     */
    public function compileTableExists(): string;

    /**
     * Compile the query to determine the list of columns.
     */
    public function compileColumnListing(string $table): string;

    /**
     * Get the SQL for the column data type.
     */
    public function getType(ColumnDefinitionInterface $column): string;

    /**
     * Add a prefix to an array of values.
     */
    public function prefixArray(string $prefix, array $values): array;

    /**
     * Wrap a table in keyword identifiers.
     */
    public function wrapTable(mixed $table): string;

    /**
     * Wrap a value in keyword identifiers.
     */
    public function wrap(mixed $value): string;

    /**
     * Format a value so that it can be used in "default" clauses.
     */
    public function getDefaultValue(mixed $value): string;

    /**
     * Get the grammar's table prefix.
     */
    public function getTablePrefix(): string;

    /**
     * Set the grammar's table prefix.
     */
    public function setTablePrefix(string $prefix): void;
}