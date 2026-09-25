<?php

declare(strict_types=1);

namespace Horizon\Database\Query\Processors;

use Horizon\Contracts\Database\ProcessorInterface;
use Horizon\Contracts\Database\QueryBuilderInterface;

/**
 * Processor
 * 
 * Base processor for handling database query results.
 */
class Processor implements ProcessorInterface
{
    /**
     * Process the results of a "select" query.
     */
    public function processSelect(QueryBuilderInterface $query, array $results): array
    {
        return $results;
    }

    /**
     * Process an "insert get ID" query.
     */
    public function processInsertGetId(QueryBuilderInterface $query, string $sql, array $values, ?string $sequence = null): int
    {
        $query->getConnection()->insert($sql, $values);

        $id = $query->getConnection()->getPdo()->lastInsertId($sequence);

        return is_numeric($id) ? (int) $id : $id;
    }

    /**
     * Process the results of a column listing query.
     */
    public function processColumnListing(array $results): array
    {
        return $results;
    }
}