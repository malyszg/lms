<?php

declare(strict_types=1);

namespace App\DTO;

use DateTimeInterface;

/**
 * Delete lead response DTO
 * Response for successful lead deletion operation
 */
class DeleteLeadResponse
{
    public function __construct(
        public readonly string $leadUuid,
        public readonly DateTimeInterface $deletedAt,
        public readonly string $message
    ) {}
}
