<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * System config DTO for list responses
 * Based on system_config table
 */
class SystemConfigDto
{
    public function __construct(
        public readonly string $configKey,
        public readonly array $configValue,
        public readonly ?string $description,
        public readonly bool $isActive
    ) {}
}
