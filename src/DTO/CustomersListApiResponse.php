<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Customers list API response DTO
 * Contains paginated list of customers
 */
class CustomersListApiResponse
{
    public function __construct(
        public readonly array $customers, // CustomerDto[]
        public readonly PaginationDto $pagination
    ) {}
}
