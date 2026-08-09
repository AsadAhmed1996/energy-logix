<?php

namespace App\Exceptions;

use Exception;

class InvalidRecordException extends Exception
{
    protected $recordData;

    /**
     * Create a new exception instance.
     */
    public function __construct(string $message, array $recordData = [], int $code = 0, ?Exception $previous = null)
    {
        $this->recordData = $recordData;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the record data that failed validation.
     */
    public function getRecordData(): array
    {
        return $this->recordData;
    }
}
