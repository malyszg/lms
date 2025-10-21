<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\FiltersDto;
use App\DTO\LeadsListApiResponse;

/**
 * Lead View Service Interface
 * Handles lead list retrieval and display operations
 */
interface LeadViewServiceInterface
{
    /**
     * Get paginated and filtered list of leads
     *
     * @param FiltersDto $filters Filter criteria
     * @param int $page Page number (1-based)
     * @param int $limit Items per page
     * @return LeadsListApiResponse
     */
    public function getLeadsList(FiltersDto $filters, int $page, int $limit): LeadsListApiResponse;
    
    /**
     * Count new leads since given timestamp
     *
     * @param string $since ISO 8601 datetime string
     * @return int
     */
    public function countNewLeadsSince(string $since): int;
}



























