<?php

declare(strict_types=1);

namespace App\DTO;

use DateTimeInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Event Filters DTO
 * Contains all possible filter parameters for events query string
 */
class EventFiltersDto
{
    public function __construct(
        public readonly ?string $eventType = null,
        public readonly ?string $entityType = null,
        public readonly ?int $entityId = null,
        public readonly ?int $userId = null,
        public readonly ?string $leadUuid = null,
        public readonly ?DateTimeInterface $createdFrom = null,
        public readonly ?DateTimeInterface $createdTo = null
    ) {}
    
    /**
     * Create EventFiltersDto from Symfony Request
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
        
        // Parse integer fields
        $entityId = $request->query->get('entity_id');
        $parsedEntityId = null;
        if ($entityId && is_numeric($entityId)) {
            $parsedEntityId = (int)$entityId;
        }
        
        $userId = $request->query->get('user_id');
        $parsedUserId = null;
        if ($userId && is_numeric($userId)) {
            $parsedUserId = (int)$userId;
        }
        
        return new self(
            eventType: $getOrNull('event_type'),
            entityType: $getOrNull('entity_type'),
            entityId: $parsedEntityId,
            userId: $parsedUserId,
            leadUuid: $getOrNull('lead_uuid'),
            createdFrom: $createdFrom,
            createdTo: $createdTo
        );
    }
    
    /**
     * Convert EventFiltersDto to query parameters array
     *
     * @return array<string, string>
     */
    public function toQueryParams(): array
    {
        $params = [];
        
        if ($this->eventType !== null) {
            $params['event_type'] = $this->eventType;
        }
        
        if ($this->entityType !== null) {
            $params['entity_type'] = $this->entityType;
        }
        
        if ($this->entityId !== null) {
            $params['entity_id'] = (string)$this->entityId;
        }
        
        if ($this->userId !== null) {
            $params['user_id'] = (string)$this->userId;
        }
        
        if ($this->leadUuid !== null) {
            $params['lead_uuid'] = $this->leadUuid;
        }
        
        if ($this->createdFrom !== null) {
            $params['created_from'] = $this->createdFrom->format('Y-m-d');
        }
        
        if ($this->createdTo !== null) {
            $params['created_to'] = $this->createdTo->format('Y-m-d');
        }
        
        return $params;
    }
    
    /**
     * Convert to array for Twig template
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'event_type' => $this->eventType,
            'entity_type' => $this->entityType,
            'entity_id' => $this->entityId,
            'user_id' => $this->userId,
            'lead_uuid' => $this->leadUuid,
            'created_from' => $this->createdFrom?->format('Y-m-d'),
            'created_to' => $this->createdTo?->format('Y-m-d'),
        ];
    }
}

