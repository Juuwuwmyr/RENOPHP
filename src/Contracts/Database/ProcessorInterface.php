<?php

declare(strict_types=1);

namespace Reno\Contracts\Database;

/**
 * Database Processor Interface
 * 
 * Defines the contract for post-processing database query results.
 */
interface ProcessorInterface
{
    /**
     * Process the results of a "select" query.
     */
    public function processSelect(QueryBuilderInterface $query, array $results): array;

    /**
     * Process an "insert get ID" query.
     */
    public function processInsertGetId(QueryBuilderInterface $query, string $sql, array $values, ?string $sequence = null): int;

    /**
     * Process the results of a column listing query.
     */
    public function processColumnListing(array $results): array;
}