<?php

declare(strict_types=1);

namespace App\DTO;

use DateTimeInterface;

/**
 * Event DTO for list responses
 * Based on events table
 */
class EventDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $eventType,
        public readonly ?string $entityType,
        public readonly ?int $entityId,
        public readonly ?int $userId,
        public readonly ?array $details,
        public readonly ?string $ipAddress,
        public readonly DateTimeInterface $createdAt
    ) {}
}
