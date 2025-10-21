<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * User DTO for API responses
 * Based on users table with permissions array
 */
class UserDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $email,
        public readonly string $role,
        public readonly array $permissions
    ) {}
}
