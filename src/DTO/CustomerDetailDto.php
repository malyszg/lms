<?php

declare(strict_types=1);

namespace App\DTO;

use DateTimeInterface;

/**
 * Customer detail DTO
 * Combines customers, customer_preferences, and leads tables
 */
class CustomerDetailDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $phone,
        public readonly ?string $firstName,
        public readonly ?string $lastName,
        public readonly DateTimeInterface $createdAt,
        public readonly DateTimeInterface $updatedAt,
        public readonly PreferencesDto $preferences,
        public readonly array $leads, // LeadItemDto[]
        public readonly int $totalLeads,
        public readonly int $newLeads,
        public readonly int $contactedLeads,
        public readonly int $qualifiedLeads,
        public readonly int $convertedLeads
    ) {}
}
