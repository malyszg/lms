<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Update system config request DTO
 * Based on system_config table fields
 */
class UpdateConfigRequest
{
    public function __construct(
        public readonly array $configValue,
        public readonly ?string $description
    ) {}
}
