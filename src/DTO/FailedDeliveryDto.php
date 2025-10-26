<?php

declare(strict_types=1);

namespace App\DTO;

use DateTimeInterface;

/**
 * Failed Delivery DTO
 * Represents a failed CDP delivery for API responses
 */
class FailedDeliveryDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $leadUuid,
        public readonly string $cdpSystemName,
        public readonly ?string $errorCode,
        public readonly ?string $errorMessage,
        public readonly int $retryCount,
        public readonly int $maxRetries,
        public readonly ?DateTimeInterface $nextRetryAt,
        public readonly string $status,
        public readonly DateTimeInterface $createdAt,
        public readonly LeadSummaryDto $lead
    ) {}
}
