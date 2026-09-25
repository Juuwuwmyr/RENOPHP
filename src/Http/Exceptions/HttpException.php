<?php

declare(strict_types=1);

namespace Horizon\Http\Exceptions;

use Exception;
use Throwable;

class HttpException extends Exception
{
    /**
     * The HTTP status code.
     */
    protected int $statusCode;

    /**
     * The response headers.
     */
    protected array $headers;

    /**
     * Create a new HTTP exception instance.
     */
    public function __construct(
        int $statusCode,
        string $message = '',
        ?Throwable $previous = null,
        array $headers = []
    ) {
        $this->statusCode = $statusCode;
        $this->headers = $headers;

        parent::__construct($message, $statusCode, $previous);
    }

    /**
     * Get the HTTP status code.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get the response headers.
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }
}