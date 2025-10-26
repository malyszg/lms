<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\CustomerDto;
use App\DTO\FiltersDto;
use App\DTO\LeadItemDto;
use App\DTO\LeadScoreResult;
use App\DTO\LeadsListApiResponse;
use App\DTO\PaginationDto;
use App\DTO\PropertyDto;
use App\Model\Event;
use App\Model\FailedDelivery;
use App\Model\Lead;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * Lead View Service
 * Implementation of lead list retrieval and display operations
 */
class LeadViewService implements LeadViewServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {}
    
    /**
     * @inheritDoc
     */
    public function getLeadsList(FiltersDto $filters, int $page, int $limit): LeadsListApiResponse
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        $qb->select('l', 'c', 'lp')
            ->from(Lead::class, 'l')
            ->leftJoin('l.customer', 'c')
            ->leftJoin('l.property', 'lp');
        
        // Apply filters
        if ($filters->status !== null) {
            $qb->andWhere('l.status = :status')
                ->setParameter('status', $filters->status);
        }
        
        if ($filters->applicationName !== null) {
            $qb->andWhere('l.applicationName = :applicationName')
                ->setParameter('applicationName', $filters->applicationName);
        }
        
        if ($filters->customerEmail !== null) {
            $qb->andWhere('c.email LIKE :email')
                ->setParameter('email', '%' . $filters->customerEmail . '%');
        }
        
        if ($filters->customerPhone !== null) {
            $qb->andWhere('c.phone LIKE :phone')
                ->setParameter('phone', '%' . $filters->customerPhone . '%');
        }
        
        if ($filters->createdFrom !== null) {
            $qb->andWhere('l.createdAt >= :createdFrom')
                ->setParameter('createdFrom', $filters->createdFrom);
        }
        
        if ($filters->createdTo !== null) {
            $qb->andWhere('l.createdAt <= :createdTo')
                ->setParameter('createdTo', $filters->createdTo);
        }
        
        // Apply sorting
        $sortField = match($filters->sort) {
            'status' => 'l.status',
            'application_name' => 'l.applicationName',
            default => 'l.createdAt',
        };
        
        $qb->orderBy($sortField, $filters->order);
        
        // Apply pagination
        $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);
        
        $paginator = new Paginator($qb->getQuery(), true);
        $total = count($paginator);
        $lastPage = (int) ceil($total / $limit);
        
        // Convert entities to DTOs
        $leads = [];
        foreach ($paginator as $lead) {
            /** @var Lead $lead */
            $leads[] = $this->convertLeadToItemDto($lead);
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
        
        return new LeadsListApiResponse($leads, $pagination);
    }
    
    /**
     * @inheritDoc
     */
    public function countNewLeadsSince(string $since): int
    {
        try {
            $sinceDateTime = new \DateTime($since);
        } catch (\Exception $e) {
            return 0;
        }
        
        $qb = $this->entityManager->createQueryBuilder();
        
        $qb->select('COUNT(l.id)')
            ->from(Lead::class, 'l')
            ->where('l.createdAt > :since')
            ->setParameter('since', $sinceDateTime);
        
        return (int) $qb->getQuery()->getSingleScalarResult();
    }
    
    /**
     * Convert Lead entity to LeadItemDto
     *
     * @param Lead $lead
     * @return LeadItemDto
     */
    private function convertLeadToItemDto(Lead $lead): LeadItemDto
    {
        $customer = $lead->getCustomer();
        $property = $lead->getProperty();
        
        $customerDto = new CustomerDto(
            id: $customer->getId(),
            email: $customer->getEmail(),
            phone: $customer->getPhone(),
            firstName: $customer->getFirstName(),
            lastName: $customer->getLastName(),
            createdAt: $customer->getCreatedAt()
        );
        
        $propertyDto = new PropertyDto(
            propertyId: $property?->getPropertyId(),
            developmentId: $property?->getDevelopmentId(),
            partnerId: $property?->getPartnerId(),
            propertyType: $property?->getPropertyType(),
            price: $property?->getPrice(),
            location: $property?->getLocation(),
            city: $property?->getCity()
        );
        
        // Determine CDP delivery status
        $cdpDeliveryStatus = $this->determineCdpDeliveryStatus($lead);
        
        // Load AI score from cache if available
        $aiScore = null;
        if ($lead->isAiScored()) {
            $aiScore = new LeadScoreResult(
                score: $lead->getAiScore(),
                category: $lead->getAiCategory(),
                reasoning: $lead->getAiReasoning(),
                suggestions: $lead->getAiSuggestions() ?? []
            );
        }
        
        return new LeadItemDto(
            id: $lead->getId(),
            leadUuid: $lead->getLeadUuid(),
            status: $lead->getStatus(),
            statusLabel: LeadItemDto::getStatusLabel($lead->getStatus()),
            createdAt: $lead->getCreatedAt(),
            customer: $customerDto,
            applicationName: $lead->getApplicationName(),
            property: $propertyDto,
            cdpDeliveryStatus: $cdpDeliveryStatus,
            aiScore: $aiScore
        );
    }
    
    /**
     * Determine CDP delivery status for a lead
     * 
     * Status priority:
     * 1. 'failed' - if there are any non-resolved failed deliveries
     * 2. 'success' - if there are successful delivery events and no failures
     * 3. 'pending' - otherwise
     * 
     * @param Lead $lead
     * @return string
     */
    private function determineCdpDeliveryStatus(Lead $lead): string
    {
        // Check for failed deliveries (not resolved)
        $qb1 = $this->entityManager->createQueryBuilder();
        $hasFailedDelivery = $qb1
            ->select('COUNT(fd.id)')
            ->from(FailedDelivery::class, 'fd')
            ->where('fd.lead = :lead')
            ->andWhere('fd.status != :resolvedStatus')
            ->setParameter('lead', $lead)
            ->setParameter('resolvedStatus', 'resolved')
            ->getQuery()
            ->getSingleScalarResult();
        
        if ($hasFailedDelivery > 0) {
            return 'failed';
        }
        
        // Check for successful delivery events
        $qb2 = $this->entityManager->createQueryBuilder();
        $hasSuccessEvent = $qb2
            ->select('COUNT(e.id)')
            ->from(Event::class, 'e')
            ->where('e.entityType = :entityType')
            ->andWhere('e.entityId = :entityId')
            ->andWhere('e.eventType = :eventType')
            ->setParameter('entityType', 'lead')
            ->setParameter('entityId', $lead->getId())
            ->setParameter('eventType', 'cdp_delivery_success')
            ->getQuery()
            ->getSingleScalarResult();
        
        if ($hasSuccessEvent > 0) {
            return 'success';
        }
        
        return 'pending';
    }
}

