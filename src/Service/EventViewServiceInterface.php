<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\EventFiltersDto;
use App\DTO\EventsListApiResponse;

/**
 * Event View Service Interface
 * Handles event list retrieval and display operations
 */
interface EventViewServiceInterface
{
    /**
     * Get paginated and filtered list of events
     *
     * @param EventFiltersDto $filters Filter criteria
     * @param int $page Page number (1-based)
     * @param int $limit Items per page
     * @return EventsListApiResponse
     */
    public function getEventsList(EventFiltersDto $filters, int $page, int $limit): EventsListApiResponse;
}

