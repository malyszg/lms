<?php

declare(strict_types=1);

namespace App\DTO;

use DateTimeInterface;

/**
 * Update lead response DTO
 * Response for lead status update operations
 */
class UpdateLeadResponse
{
    public function __construct(
        public readonly int $id,
        public readonly string $leadUuid,
        public readonly string $status,
        public readonly string $statusLabel,
        public readonly DateTimeInterface $updatedAt,
        public readonly string $message
    ) {}

    /**
     * Convert to array for JSON response
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'lead_uuid' => $this->leadUuid,
            'status' => $this->status,
            'status_label' => $this->statusLabel,
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
            'message' => $this->message,
        ];
    }
}
