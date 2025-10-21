<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Error response DTO
 * Used for API error responses
 */
class ErrorResponseDto
{
    public function __construct(
        public readonly string $error,
        public readonly string $message,
        public readonly ?array $details = null
    ) {}
}
