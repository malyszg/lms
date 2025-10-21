<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Update customer preferences request DTO
 * Based on customer_preferences table fields
 */
class UpdatePreferencesRequest
{
    public function __construct(
        public readonly ?float $priceMin,
        public readonly ?float $priceMax,
        public readonly ?string $location,
        public readonly ?string $city
    ) {}
}
