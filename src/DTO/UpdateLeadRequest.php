<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Update lead request DTO
 * Based on leads table status field only
 */
class UpdateLeadRequest
{
    public function __construct(
        public readonly string $status
    ) {}
}
