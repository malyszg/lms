<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Pagination DTO for list responses
 */
class PaginationDto
{
    public function __construct(
        public readonly int $currentPage,
        public readonly int $perPage,
        public readonly int $total,
        public readonly int $lastPage,
        public readonly int $from,
        public readonly int $to
    ) {}
    
    /**
     * Create PaginationDto from array (typically from API response)
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $currentPage = (int) $data['current_page'];
        $perPage = (int) $data['per_page'];
        $total = (int) $data['total'];
        $lastPage = (int) $data['last_page'];
        
        $from = $total > 0 ? ($currentPage - 1) * $perPage + 1 : 0;
        $to = min($currentPage * $perPage, $total);
        
        return new self(
            currentPage: $currentPage,
            perPage: $perPage,
            total: $total,
            lastPage: $lastPage,
            from: $from,
            to: $to
        );
    }
}
