<?php

declare(strict_types=1);

namespace Horizon\Http\Exceptions;

class NotFoundHttpException extends HttpException
{
    /**
     * Create a new not found exception instance.
     */
    public function __construct(string $message = 'Not Found', ?\Throwable $previous = null, array $headers = [])
    {
        parent::__construct(404, $message, $previous, $headers);
    }
}