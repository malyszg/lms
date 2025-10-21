<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Exception thrown when Google Gemini API encounters an error
 * 
 * This exception is used to wrap various API errors with additional context
 * including error codes and HTTP status information.
 */
class GeminiApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $errorCode,
        int $httpStatus = 0,
        private readonly array $context = []
    ) {
        parent::__construct($message, $httpStatus);
    }

    /**
     * Get the specific error code for this exception
     * 
     * @return string Error code (e.g., GEMINI_INVALID_API_KEY, GEMINI_RATE_LIMIT)
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Get additional context information about the error
     * 
     * @return array<string, mixed> Context data
     */
    public function getContext(): array
    {
        return $this->context;
    }
}

