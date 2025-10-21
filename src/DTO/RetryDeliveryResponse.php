<?php

declare(strict_types=1);

namespace App\DTO;

use DateTimeInterface;

/**
 * Retry delivery response DTO
 * Based on failed_deliveries table with additional message field
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
