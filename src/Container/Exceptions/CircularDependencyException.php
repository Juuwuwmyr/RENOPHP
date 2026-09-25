<?php

declare(strict_types=1);

namespace Horizon\Container\Exceptions;

use Exception;

class CircularDependencyException extends Exception
{
    /**
     * Create a new circular dependency exception.
     */
    public function __construct(string $abstract, array $chain)
    {
        $cycle = implode(' → ', $chain) . ' → ' . $abstract;
        
        parent::__construct(
            "Circular dependency detected: {$cycle}. " .
            "Break the circular dependency by using interfaces, factories, or lazy loading."
        );
    }
}