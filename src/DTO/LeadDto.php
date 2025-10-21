<?php

declare(strict_types=1);

namespace App\DTO;

use DateTimeInterface;

/**
 * Lead DTO for list responses
 * Combines leads, customers, and lead_properties tables
 */
class LeadDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $leadUuid,
        public readonly string $status,
        public readonly DateTimeInterface $createdAt,
        public readonly CustomerDto $customer,
        public readonly string $applicationName,
        public readonly PropertyDto $property
    ) {}
}
