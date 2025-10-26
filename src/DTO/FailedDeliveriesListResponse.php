<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Failed Deliveries List Response DTO
 * Response for GET /api/failed-deliveries
 */
class FailedDeliveriesListResponse
{
    public function __construct(
        public readonly array $data, // FailedDeliveryDto[]
        public readonly PaginationDto $pagination
    ) {}
}

