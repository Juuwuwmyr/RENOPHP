<?php

namespace Horizon\Auth\Access;

use Exception;

/**
 * AuthorizationException
 * 
 * Exception thrown when authorization fails.
 * 
 * Philosophy: "Less Magic. More Understanding."
 * - Clear authorization failure
 * - HTTP 403 status
 * - Custom messages
 */
class AuthorizationException extends Exception
{
    /**
     * The response status code
     *
     * @var int
     */
    protected $code = 403;

    /**
     * Create a new authorization exception instance
     *
     * @param string $message
     * @param mixed $code
     */
    public function __construct(string $message = 'This action is unauthorized.', $code = null)
    {
        parent::__construct($message, $code ?? 403);
    }
}
