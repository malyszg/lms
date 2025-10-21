<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Preferences DTO
 * Based on customer_preferences table
 */
class PreferencesDto
{
    public function __construct(
        public readonly ?float $priceMin,
        public readonly ?float $priceMax,
        public readonly ?string $location,
        public readonly ?string $city
    ) {}
}
