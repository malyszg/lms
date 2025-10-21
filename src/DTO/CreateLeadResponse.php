<?php

declare(strict_types=1);

namespace App\DTO;

use DateTimeInterface;

/**
 * Create lead response DTO
 * Based on leads table with additional cdp_delivery_status field
 */
class CreateLeadResponse
{
    public function __construct(
        public readonly int $id,
        public readonly string $leadUuid,
        public readonly string $status,
        public readonly int $customerId,
        public readonly string $applicationName,
        public readonly DateTimeInterface $createdAt,
        public readonly string $cdpDeliveryStatus
    ) {}
}
