<?php

declare(strict_types=1);

namespace Reno\Database\Concerns;

use Reno\Database\Eloquent\SecurityException;

/**
 * SQL Injection Prevention
 * 
 * Provides methods to detect and prevent SQL injection attacks
 * in database queries and user input.
 */
trait PreventsSqlInjection
{
    /**
     * Validate a raw SQL query for potential injection attacks.
     */
    protected function validateRawSql(string $sql): void
    {
        if ($this->containsSqlInjection($sql)) {
            throw SecurityException::sqlInjection('raw_sql', $sql);
        }
    }

    /**
     * Sanitize a table name to prevent injection.
     */
    protected function sanitizeTableName(string $table): string
    {
        // Only allow alphanumeric characters, underscores, and dots
        if (!preg_match('/^[a-zA-Z0-9_\.]+$/', $table)) {
            throw new SecurityException("Invalid table name: {$table}");
        }

        return $table;
    }

    /**
     * Sanitize a column name to prevent injection.
     */
    protected function sanitizeColumnName(string $column): string
    {
        // Only allow alphanumeric characters, underscores, and dots
        if (!preg_match('/^[a-zA-Z0-9_\.]+$/', $column)) {
            throw new SecurityException("Invalid column name: {$column}");
        }

        return $column;
    }

    /**
     * Check if a string contains potential SQL injection patterns.
     */
    protected function containsSqlInjection(string $value): bool
    {
        // Normalize the string for checking
        $normalized = strtoupper(preg_replace('/\s+/', ' ', $value));

        $dangerousPatterns = [
            // Union-based injection
            '/UNION\s+SELECT/i',
            '/UNION\s+ALL\s+SELECT/i',
            
            // Boolean-based injection
            '/\'\s*(OR|AND)\s+\'/i',
            '/\'\s*(OR|AND)\s+1\s*=/i',
            '/1\s*=\s*1/i',
            '/1\s*=\s*0/i',
            
            // Time-based injection
            '/SLEEP\s*\(/i',
            '/WAITFOR\s+DELAY/i',
            '/BENCHMARK\s*\(/i',
            
            // Stacked queries
            '/;\s*(SELECT|INSERT|UPDATE|DELETE|DROP|CREATE|ALTER)/i',
            
            // Comment injection
            '/\/\*.*\*\//i',
            '/--\s*[\r\n]/i',
            '/#.*[\r\n]/i',
            
            // Information gathering
            '/INFORMATION_SCHEMA/i',
            '/SYSTEM_USER\s*\(\s*\)/i',
            '/USER\s*\(\s*\)/i',
            '/VERSION\s*\(\s*\)/i',
            '/DATABASE\s*\(\s*\)/i',
            
            // Database structure
            '/SHOW\s+(TABLES|DATABASES|COLUMNS)/i',
            '/DESC\s+\w+/i',
            '/DESCRIBE\s+\w+/i',
            
            // File operations
            '/LOAD_FILE\s*\(/i',
            '/INTO\s+OUTFILE/i',
            '/INTO\s+DUMPFILE/i',
            
            // Stored procedures
            '/EXEC\s*\(/i',
            '/EXECUTE\s*\(/i',
            '/SP_/i',
            '/XP_/i',
            
            // NoSQL injection patterns
            '/\$WHERE/i',
            '/\$NE/i',
            '/\$GT/i',
            '/\$LT/i',
        ];

        foreach ($dangerousPatterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Escape a value for safe inclusion in SQL queries.
     */
    protected function escapeValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            // Use proper PDO escaping in real implementation
            return "'" . addslashes($value) . "'";
        }

        throw new SecurityException("Cannot escape value of type: " . gettype($value));
    }

    /**
     * Validate query parameters for potential injection.
     */
    protected function validateQueryParameters(array $parameters): void
    {
        foreach ($parameters as $key => $value) {
            if (is_string($value) && $this->containsSqlInjection($value)) {
                throw SecurityException::sqlInjection($key, $value);
            }

            if (is_string($key) && !$this->isValidParameterName($key)) {
                throw new SecurityException("Invalid parameter name: {$key}");
            }
        }
    }

    /**
     * Check if a parameter name is valid.
     */
    protected function isValidParameterName(string $name): bool
    {
        // Parameter names should only contain alphanumeric characters and underscores
        return preg_match('/^[a-zA-Z0-9_]+$/', $name) === 1;
    }

    /**
     * Sanitize ORDER BY clause to prevent injection.
     */
    protected function sanitizeOrderBy(string $orderBy): string
    {
        // Split by comma to handle multiple columns
        $parts = array_map('trim', explode(',', $orderBy));
        $sanitized = [];

        foreach ($parts as $part) {
            // Extract column and direction
            $tokens = preg_split('/\s+/', trim($part));
            $column = $tokens[0] ?? '';
            $direction = strtoupper($tokens[1] ?? 'ASC');

            // Validate column name
            if (!preg_match('/^[a-zA-Z0-9_\.]+$/', $column)) {
                throw new SecurityException("Invalid column in ORDER BY: {$column}");
            }

            // Validate direction
            if (!in_array($direction, ['ASC', 'DESC'])) {
                throw new SecurityException("Invalid direction in ORDER BY: {$direction}");
            }

            $sanitized[] = "{$column} {$direction}";
        }

        return implode(', ', $sanitized);
    }

    /**
     * Sanitize GROUP BY clause to prevent injection.
     */
    protected function sanitizeGroupBy(string $groupBy): string
    {
        $columns = array_map('trim', explode(',', $groupBy));
        $sanitized = [];

        foreach ($columns as $column) {
            if (!preg_match('/^[a-zA-Z0-9_\.]+$/', $column)) {
                throw new SecurityException("Invalid column in GROUP BY: {$column}");
            }
            $sanitized[] = $column;
        }

        return implode(', ', $sanitized);
    }

    /**
     * Check if a LIMIT clause is safe.
     */
    protected function validateLimit(mixed $limit, mixed $offset = null): void
    {
        if (!is_numeric($limit) || $limit < 0) {
            throw new SecurityException("Invalid LIMIT value: {$limit}");
        }

        if ($offset !== null && (!is_numeric($offset) || $offset < 0)) {
            throw new SecurityException("Invalid OFFSET value: {$offset}");
        }
    }

    /**
     * Detect and prevent second-order SQL injection.
     */
    protected function checkSecondOrderInjection(array $data): void
    {
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                // Check for encoded injection attempts
                $decoded = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
                if ($this->containsSqlInjection($decoded)) {
                    throw SecurityException::sqlInjection($key, $value);
                }

                // Check for URL-encoded injection
                $urlDecoded = urldecode($value);
                if ($this->containsSqlInjection($urlDecoded)) {
                    throw SecurityException::sqlInjection($key, $value);
                }

                // Check for base64-encoded injection
                if (base64_decode($value, true) !== false) {
                    $base64Decoded = base64_decode($value);
                    if ($this->containsSqlInjection($base64Decoded)) {
                        throw SecurityException::sqlInjection($key, $value);
                    }
                }
            }
        }
    }
}