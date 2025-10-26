<?php

declare(strict_types=1);

namespace App\DTO;

use DateTimeInterface;

/**
 * Retry Delivery Response DTO
 * Response for POST /api/failed-deliveries/{id}/retry
 */
class RetryDeliveryResponse
{
    public function __construct(
        public readonly int $id,
        public readonly string $status,
        public readonly int $retryCount,
        public readonly ?DateTimeInterface $nextRetryAt,
        public readonly string $message
    ) {}
}
