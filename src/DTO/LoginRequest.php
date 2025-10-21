<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Login request DTO
 * Maps to users table for username validation
 */
class LoginRequest
{
    public function __construct(
        public readonly string $username,
        public readonly string $password
    ) {}
}
