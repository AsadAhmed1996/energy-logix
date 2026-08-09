<?php

namespace App\Exceptions;

use Exception;

class SyncFetchException extends Exception
{
    protected $skip;
    protected $statusCode;

    /**
     * Create a new exception instance.
     */
    public function __construct(string $message, int $skip, int $statusCode, int $code = 0, ?Exception $previous = null)
    {
        $this->skip = $skip;
        $this->statusCode = $statusCode;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the skip offset where the failure occurred.
     */
    public function getSkip(): int
    {
        return $this->skip;
    }

    /**
     * Get the HTTP status code returned by the API.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
