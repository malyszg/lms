<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Property DTO
 * Based on lead_properties table
 */
class PropertyDto
{
    public function __construct(
        public readonly ?string $propertyId,
        public readonly ?string $developmentId,
        public readonly ?string $partnerId,
        public readonly ?string $propertyType,
        public readonly ?float $price,
        public readonly ?string $location,
        public readonly ?string $city
    ) {}
}
