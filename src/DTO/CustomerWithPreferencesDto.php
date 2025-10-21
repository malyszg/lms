<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Customer DTO with preferences for detailed responses
 * Combines customers and customer_preferences tables
 */
class CustomerWithPreferencesDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $phone,
        public readonly ?string $firstName,
        public readonly ?string $lastName,
        public readonly ?PreferencesDto $preferences
    ) {}
}
