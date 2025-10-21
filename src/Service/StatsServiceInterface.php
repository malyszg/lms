<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\StatsDto;

/**
 * Stats Service Interface
 * Handles dashboard statistics retrieval
 */
interface StatsServiceInterface
{
    /**
     * Get dashboard statistics
     * Returns counts for: leads today, failed deliveries, total customers
     *
     * @return StatsDto
     */
    public function getDashboardStats(): StatsDto;
}



























