<?php

namespace App\Exceptions;

use Exception;

class SyncAuthException extends Exception
{
    /**
     * Create a new exception instance.
     */
    public function __construct(string $message = "Authentication with the third-party API failed.", int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
