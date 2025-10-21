<?php

declare(strict_types=1);

namespace App\DTO;

use DateTimeInterface;

/**
 * Lead detail DTO for single lead responses
 * Combines leads, customers, customer_preferences, lead_properties, and events tables
 */
class LeadDetailDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $leadUuid,
        public readonly string $status,
        public readonly DateTimeInterface $createdAt,
        public readonly DateTimeInterface $updatedAt,
        public readonly CustomerWithPreferencesDto $customer,
        public readonly string $applicationName,
        public readonly PropertyDto $property,
        public readonly array $events
    ) {}
}
