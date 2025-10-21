<?php

declare(strict_types=1);

namespace App\ViewModel;

use App\DTO\FiltersDto;
use App\DTO\LeadItemDto;
use App\DTO\PaginationDto;
use App\DTO\StatsDto;

/**
 * Leads List View Model
 * Aggregates all data needed for the leads list view
 */
class LeadsListViewModel
{
    /**
     * @param LeadItemDto[] $leads
     * @param PaginationDto $pagination
     * @param FiltersDto $filters
     * @param StatsDto $stats
     * @param int $newLeadsCount
     * @param string $lastCheckTimestamp
     */
    public function __construct(
        public readonly array $leads,
        public readonly PaginationDto $pagination,
        public readonly FiltersDto $filters,
        public readonly StatsDto $stats,
        public readonly int $newLeadsCount,
        public readonly string $lastCheckTimestamp
    ) {}
}



























