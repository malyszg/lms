<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\StatsDto;
use App\Model\Customer;
use App\Model\FailedDelivery;
use App\Model\Lead;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Stats Service
 * Implementation of dashboard statistics retrieval
 */
class StatsService implements StatsServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {}
    
    /**
     * @inheritDoc
     */
    public function getDashboardStats(): StatsDto
    {
        $leadsToday = $this->countLeadsToday();
        $failedDeliveries = $this->countFailedDeliveries();
        $totalCustomers = $this->countTotalCustomers();
        
        return new StatsDto(
            leadsToday: $leadsToday,
            failedDeliveries: $failedDeliveries,
            totalCustomers: $totalCustomers
        );
    }
    
    /**
     * Count leads created today
     *
     * @return int
     */
    private function countLeadsToday(): int
    {
        $today = new \DateTime('today');
        
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(l.id)')
            ->from(Lead::class, 'l')
            ->where('l.createdAt >= :today')
            ->setParameter('today', $today);
        
        return (int) $qb->getQuery()->getSingleScalarResult();
    }
    
    /**
     * Count failed deliveries
     *
     * @return int
     */
    private function countFailedDeliveries(): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(fd.id)')
            ->from(FailedDelivery::class, 'fd')
            ->where('fd.status IN (:statuses)')
            ->setParameter('statuses', ['failed', 'pending', 'retrying']);
        
        return (int) $qb->getQuery()->getSingleScalarResult();
    }
    
    /**
     * Count total customers
     *
     * @return int
     */
    private function countTotalCustomers(): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('COUNT(c.id)')
            ->from(Customer::class, 'c');
        
        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}



























