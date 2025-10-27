<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Events List API Response DTO
 * Wraps data array and pagination for events API responses
 */
class EventsListApiResponse
{
    /**
     * @param EventDto[] $data
     * @param PaginationDto $pagination
     */
    public function __construct(
        public readonly array $data,
        public readonly PaginationDto $pagination
    ) {}
}

