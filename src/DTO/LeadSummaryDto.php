<?php

declare(strict_types=1);

namespace App\DTO;

use DateTimeInterface;

/**
 * Lead summary DTO for nested use
 * Based on leads table with minimal fields
 */
class LeadSummaryDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $leadUuid,
        public readonly string $status,
        public readonly string $applicationName,
        public readonly DateTimeInterface $createdAt
    ) {}
}
