<?php

declare(strict_types=1);

namespace App\DTO;

use DateTimeInterface;

/**
 * Lead Item DTO for list view
 * Extended LeadDto with additional display fields
 */
class LeadItemDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $leadUuid,
        public readonly string $status,
        public readonly string $statusLabel,
        public readonly DateTimeInterface $createdAt,
        public readonly CustomerDto $customer,
        public readonly string $applicationName,
        public readonly PropertyDto $property,
        public readonly string $cdpDeliveryStatus,
        public ?LeadScoreResult $aiScore = null
    ) {}
    
    /**
     * Get status label in Polish
     *
     * @param string $status
     * @return string
     */
    public static function getStatusLabel(string $status): string
    {
        return match($status) {
            'new' => 'Nowy',
            'contacted' => 'Skontaktowano',
            'qualified' => 'Zakwalifikowano',
            'converted' => 'Przekonwertowano',
            'rejected' => 'Odrzucono',
            default => $status,
        };
    }
}








