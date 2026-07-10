<?php

namespace App\Exceptions;

class ValidationException extends \Exception
{
    /**
     * @var array<string, array<int, string>>
     */
    private array $errors;

    /**
     * @param string $message
     * @param array $errors
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(string $message = 'Validation failed', array $errors = [], int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
