<?php

declare(strict_types=1);

namespace App\DTO;

use DateTimeInterface;

/**
 * Login response DTO
 * Combines user data with session token
 */
class LoginResponse
{
    public function __construct(
        public readonly string $token,
        public readonly UserDto $user,
        public readonly DateTimeInterface $expiresAt
    ) {}
}
