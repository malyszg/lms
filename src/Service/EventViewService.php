<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\EventDto;
use App\DTO\EventFiltersDto;
use App\DTO\EventsListApiResponse;
use App\DTO\PaginationDto;
use App\Model\Event;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * Event View Service
 * Implementation of event list retrieval and display operations
 */
class EventViewService implements EventViewServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {}
    
    /**
     * @inheritDoc
     */
    public function getEventsList(EventFiltersDto $filters, int $page, int $limit): EventsListApiResponse
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        $qb->select('e')
            ->from(Event::class, 'e')
            ->orderBy('e.createdAt', 'DESC');
        
        // Apply filters
        if ($filters->eventType !== null) {
            $qb->andWhere('e.eventType = :eventType')
                ->setParameter('eventType', $filters->eventType);
        }
        
        if ($filters->entityType !== null) {
            $qb->andWhere('e.entityType = :entityType')
                ->setParameter('entityType', $filters->entityType);
        }
        
        if ($filters->entityId !== null) {
            $qb->andWhere('e.entityId = :entityId')
                ->setParameter('entityId', $filters->entityId);
        }
        
        if ($filters->userId !== null) {
            $qb->andWhere('e.userId = :userId')
                ->setParameter('userId', $filters->userId);
        }
        
        if ($filters->createdFrom !== null) {
            $qb->andWhere('e.createdAt >= :createdFrom')
                ->setParameter('createdFrom', $filters->createdFrom);
        }
        
        if ($filters->createdTo !== null) {
            $qb->andWhere('e.createdAt <= :createdTo')
                ->setParameter('createdTo', $filters->createdTo);
        }
        
        // Filter by lead UUID if provided (need to join with lead entity)
        if ($filters->leadUuid !== null) {
            // Note: This requires checking the details JSON field
            // Since we can't easily join, we'll filter after fetching
            // For now, we'll search in details JSON field
            $qb->andWhere('e.details LIKE :leadUuid')
                ->setParameter('leadUuid', '%' . $filters->leadUuid . '%');
        }
        
        // Apply pagination
        $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);
        
        $paginator = new Paginator($qb->getQuery(), true);
        $total = count($paginator);
        $lastPage = (int) ceil($total / $limit);
        
        // Convert entities to DTOs
        $events = [];
        foreach ($paginator as $event) {
            /** @var Event $event */
            $events[] = $this->convertEventToDto($event);
        }
        
        $pagination = new PaginationDto(
            currentPage: $page,
            perPage: $limit,
            total: $total,
            lastPage: max(1, $lastPage),
            from: $total > 0 ? ($page - 1) * $limit + 1 : 0,
            to: min($page * $limit, $total),
            hasNext: $page < $lastPage,
            hasPrevious: $page > 1
        );
        
        return new EventsListApiResponse($events, $pagination);
    }
    
    /**
     * Convert Event entity to EventDto
     *
     * @param Event $event
     * @return EventDto
     */
    private function convertEventToDto(Event $event): EventDto
    {
        return new EventDto(
            id: $event->getId() ?? 0,
            eventType: $event->getEventType(),
            entityType: $event->getEntityType(),
            entityId: $event->getEntityId(),
            userId: $event->getUserId(),
            details: $event->getDetails(),
            ipAddress: $event->getIpAddress(),
            createdAt: $event->getCreatedAt()
        );
    }
}

