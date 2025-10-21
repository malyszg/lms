<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * DTO for creating a new customer (input data)
 * Used for customer creation in POST /api/leads endpoint
 */
class CreateCustomerDto
{
    public function __construct(
        public readonly string $email,
        public readonly string $phone,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null
    ) {}
}


