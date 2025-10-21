<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Leads List API Response DTO
 * Wraps data array and pagination for API responses
 */
class LeadsListApiResponse
{
    /**
     * @param LeadItemDto[] $data
     * @param PaginationDto $pagination
     */
    public function __construct(
        public readonly array $data,
        public readonly PaginationDto $pagination
    ) {}
}



























