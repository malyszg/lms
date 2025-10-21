<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Stats DTO for dashboard statistics
 * Contains counters for main dashboard metrics
 */
class StatsDto
{
    public function __construct(
        public readonly int $leadsToday,
        public readonly int $failedDeliveries,
        public readonly int $totalCustomers
    ) {}
}



























