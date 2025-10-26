<?php

declare(strict_types=1);

namespace App\DTO;

use DateTimeInterface;

/**
 * Update preferences response DTO
 * Response for customer preferences update operations
 */
class UpdatePreferencesResponse
{
    public function __construct(
        public readonly int $id,
        public readonly int $customerId,
        public readonly ?float $priceMin,
        public readonly ?float $priceMax,
        public readonly ?string $location,
        public readonly ?string $city,
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
            'customer_id' => $this->customerId,
            'price_min' => $this->priceMin,
            'price_max' => $this->priceMax,
            'location' => $this->location,
            'city' => $this->city,
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
            'message' => $this->message,
        ];
    }
}
