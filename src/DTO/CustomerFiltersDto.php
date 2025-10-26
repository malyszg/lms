<?php

declare(strict_types=1);

namespace App\DTO;

use DateTimeInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Customer filters DTO
 * Contains all possible filter parameters for customer search
 */
class CustomerFiltersDto
{
    public function __construct(
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
        public readonly ?DateTimeInterface $createdFrom = null,
        public readonly ?DateTimeInterface $createdTo = null,
        public readonly ?int $minLeads = null,
        public readonly ?int $maxLeads = null,
        public readonly string $sort = 'created_at',
        public readonly string $order = 'desc'
    ) {}
    
    /**
     * Create CustomerFiltersDto from Symfony Request
     *
     * @param Request $request
     * @return self
     */
    public static function fromRequest(Request $request): self
    {
        $createdFrom = null;
        $createdTo = null;
        
        if ($request->query->get('created_from')) {
            try {
                $createdFrom = new \DateTime($request->query->get('created_from'));
            } catch (\Exception $e) {
                // Invalid date format, ignore
            }
        }
        
        if ($request->query->get('created_to')) {
            try {
                $createdTo = new \DateTime($request->query->get('created_to'));
            } catch (\Exception $e) {
                // Invalid date format, ignore
            }
        }
        
        // Helper function to convert empty strings to null
        $getOrNull = fn($key) => $request->query->get($key) ?: null;
        
        return new self(
            email: $getOrNull('email'),
            phone: $getOrNull('phone'),
            createdFrom: $createdFrom,
            createdTo: $createdTo,
            minLeads: $request->query->get('min_leads') ? (int) $request->query->get('min_leads') : null,
            maxLeads: $request->query->get('max_leads') ? (int) $request->query->get('max_leads') : null,
            sort: $request->query->get('sort', 'created_at'),
            order: $request->query->get('order', 'desc')
        );
    }
    
    /**
     * Convert CustomerFiltersDto to query parameters array
     *
     * @return array<string, string>
     */
    public function toQueryParams(): array
    {
        $params = [];
        
        if ($this->email !== null) {
            $params['email'] = $this->email;
        }
        
        if ($this->phone !== null) {
            $params['phone'] = $this->phone;
        }
        
        if ($this->createdFrom !== null) {
            $params['created_from'] = $this->createdFrom->format('Y-m-d');
        }
        
        if ($this->createdTo !== null) {
            $params['created_to'] = $this->createdTo->format('Y-m-d');
        }
        
        if ($this->minLeads !== null) {
            $params['min_leads'] = (string) $this->minLeads;
        }
        
        if ($this->maxLeads !== null) {
            $params['max_leads'] = (string) $this->maxLeads;
        }
        
        if ($this->sort !== 'created_at') {
            $params['sort'] = $this->sort;
        }
        
        if ($this->order !== 'desc') {
            $params['order'] = $this->order;
        }
        
        return $params;
    }
}
