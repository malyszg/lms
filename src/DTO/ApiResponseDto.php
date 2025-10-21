<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Generic API response DTO
 */
class ApiResponseDto
{
    public function __construct(
        public readonly array $data,
        public readonly ?PaginationDto $pagination = null
    ) {}
}
