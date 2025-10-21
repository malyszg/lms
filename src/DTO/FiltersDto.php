<?php

declare(strict_types=1);

namespace App\DTO;

use DateTimeInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Filters DTO for leads list
 * Contains all possible filter parameters from query string
 */
class FiltersDto
{
    public function __construct(
        public readonly ?string $status = null,
        public readonly ?string $applicationName = null,
        public readonly ?string $customerEmail = null,
        public readonly ?string $customerPhone = null,
        public readonly ?DateTimeInterface $createdFrom = null,
        public readonly ?DateTimeInterface $createdTo = null,
        public readonly string $sort = 'created_at',
        public readonly string $order = 'desc'
    ) {}
    
    /**
     * Create FiltersDto from Symfony Request
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
            status: $getOrNull('status'),
            applicationName: $getOrNull('application_name'),
            customerEmail: $getOrNull('customer_email'),
            customerPhone: $getOrNull('customer_phone'),
            createdFrom: $createdFrom,
            createdTo: $createdTo,
            sort: $request->query->get('sort', 'created_at'),
            order: $request->query->get('order', 'desc')
        );
    }
    
    /**
     * Convert FiltersDto to query parameters array
     *
     * @return array<string, string>
     */
    public function toQueryParams(): array
    {
        $params = [];
        
        if ($this->status !== null) {
            $params['status'] = $this->status;
        }
        
        if ($this->applicationName !== null) {
            $params['application_name'] = $this->applicationName;
        }
        
        if ($this->customerEmail !== null) {
            $params['customer_email'] = $this->customerEmail;
        }
        
        if ($this->customerPhone !== null) {
            $params['customer_phone'] = $this->customerPhone;
        }
        
        if ($this->createdFrom !== null) {
            $params['created_from'] = $this->createdFrom->format('Y-m-d');
        }
        
        if ($this->createdTo !== null) {
            $params['created_to'] = $this->createdTo->format('Y-m-d');
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

