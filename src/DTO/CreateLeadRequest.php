<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Create lead request DTO
 * Combines leads, customers, and lead_properties tables
 */
class CreateLeadRequest
{
    public function __construct(
        public readonly string $leadUuid,
        public readonly string $applicationName,
        public readonly CreateCustomerDto $customer,
        public readonly PropertyDto $property
    ) {}
}
