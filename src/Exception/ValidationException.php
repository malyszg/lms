<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Exception thrown when request validation fails
 * Results in HTTP 400 Bad Request response
 */
class ValidationException extends \InvalidArgumentException
{
    /**
     * @param array<string, string> $errors Array of field => error message
     */
    public function __construct(
        private readonly array $errors
    ) {
        parent::__construct('Validation failed');
    }

    /**
     * @return array<string, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}































