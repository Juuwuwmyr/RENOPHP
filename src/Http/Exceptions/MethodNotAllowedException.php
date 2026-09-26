<?php

declare(strict_types=1);

namespace Reno\Http\Exceptions;

class MethodNotAllowedException extends HttpException
{
    /**
     * Create a new method not allowed exception instance.
     */
    public function __construct(string $message = 'Method Not Allowed', ?\Throwable $previous = null, array $headers = [])
    {
        parent::__construct(405, $message, $previous, $headers);
    }
}