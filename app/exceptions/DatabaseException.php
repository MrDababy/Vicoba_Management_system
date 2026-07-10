<?php

namespace App\Exceptions;

class DatabaseException extends \Exception
{
    /**
     * @var \Throwable|null
     */
    private ?\Throwable $previousException;

    public function __construct(string $message = 'Database error', int|string $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, is_int($code) ? $code : 0, $previous);
        $this->previousException = $previous;
    }

    public function getPreviousException(): ?\Throwable
    {
        return $this->previousException;
    }
}
