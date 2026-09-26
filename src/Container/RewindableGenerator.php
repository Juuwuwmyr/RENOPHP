<?php

declare(strict_types=1);

namespace Reno\Container;

use Countable;
use Generator;
use IteratorAggregate;

class RewindableGenerator implements Countable, IteratorAggregate
{
    /**
     * The generator callback.
     */
    protected $generator;

    /**
     * The number of tagged services.
     */
    protected int $count;

    /**
     * Create a new rewindable generator instance.
     */
    public function __construct(callable $generator, int $count)
    {
        $this->count = $count;
        $this->generator = $generator;
    }

    /**
     * Get an iterator for the generator.
     */
    public function getIterator(): Generator
    {
        return ($this->generator)();
    }

    /**
     * Get the total number of tagged services.
     */
    public function count(): int
    {
        return $this->count;
    }
}