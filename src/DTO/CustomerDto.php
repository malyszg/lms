<?php

declare(strict_types=1);

namespace App\DTO;

use DateTimeInterface;

/**
 * Customer DTO for list responses
 * Based on customers table with additional computed fields
 */
class CustomerDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $phone,
        public readonly ?string $firstName,
        public readonly ?string $lastName,
        public readonly DateTimeInterface $createdAt
    ) {}
}
