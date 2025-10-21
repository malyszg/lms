<?php

declare(strict_types=1);

namespace App\DTO;

use DateTimeInterface;

/**
 * Customer list DTO with additional computed fields
 * Based on customers table with leads_count and last_lead_at
 */
class CustomerListDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $phone,
        public readonly ?string $firstName,
        public readonly ?string $lastName,
        public readonly DateTimeInterface $createdAt,
        public readonly int $leadsCount,
        public readonly ?DateTimeInterface $lastLeadAt
    ) {}
}
