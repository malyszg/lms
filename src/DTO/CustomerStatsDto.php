<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Customer statistics DTO
 * Contains aggregated statistics about customers
 */
class CustomerStatsDto
{
    public function __construct(
        public readonly int $totalCustomers,
        public readonly int $customersWithLeads,
        public readonly int $customersWithPreferences,
        public readonly int $newCustomersToday
    ) {}
}
