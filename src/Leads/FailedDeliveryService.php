<?php

declare(strict_types=1);

namespace App\Leads;

use App\Model\FailedDelivery;
use App\Model\Lead;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Failed Delivery Service
 * Manages failed CDP delivery records
 */
class FailedDeliveryService implements FailedDeliveryServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {}

    /**
     * Create failed delivery record
     *
     * @param Lead $lead Lead that failed to deliver
     * @param string $cdpSystem CDP system name
     * @param string $errorMessage Error message
     * @param string|null $errorCode Error code (HTTP status code)
     * @return FailedDelivery Created failed delivery
     */
    public function createFailedDelivery(
        Lead $lead,
        string $cdpSystem,
        string $errorMessage,
        ?string $errorCode = null
    ): FailedDelivery {
        $failedDelivery = new FailedDelivery($lead, $cdpSystem);
        $failedDelivery->setErrorMessage($errorMessage);
        $failedDelivery->setErrorCode($errorCode);

        $this->entityManager->persist($failedDelivery);
        $this->entityManager->flush();

        return $failedDelivery;
    }

    /**
     * Get pending deliveries that need retry
     *
     * @param int $limit Maximum number of deliveries to retrieve
     * @return array<FailedDelivery> Array of pending FailedDelivery records
     */
    public function getPendingDeliveries(int $limit = 100): array
    {
        $repository = $this->entityManager->getRepository(FailedDelivery::class);

        return $repository->createQueryBuilder('fd')
            ->where('fd.status = :status')
            ->andWhere('fd.nextRetryAt <= :now OR fd.nextRetryAt IS NULL')
            ->setParameter('status', 'pending')
            ->setParameter('now', new \DateTime())
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Retry failed delivery
     *
     * @param FailedDelivery $failedDelivery Delivery to retry
     * @return void
     */
    public function retryDelivery(FailedDelivery $failedDelivery): void
    {
        $failedDelivery->setStatus('retrying');
        $this->entityManager->flush();
    }

    /**
     * Mark delivery as resolved (successfully sent)
     *
     * @param FailedDelivery $failedDelivery Delivery to mark as resolved
     * @return void
     */
    public function markAsResolved(FailedDelivery $failedDelivery): void
    {
        $failedDelivery->setStatus('resolved');
        $failedDelivery->setResolvedAt(new \DateTime());
        $this->entityManager->flush();
    }

    /**
     * Mark delivery as final failure (retry limit exceeded)
     *
     * @param FailedDelivery $failedDelivery Delivery to mark as failed
     * @return void
     */
    public function markAsFailed(FailedDelivery $failedDelivery): void
    {
        $failedDelivery->setStatus('failed');
        $this->entityManager->flush();
    }

    /**
     * Find failed delivery by ID
     *
     * @param int $id Failed delivery ID
     * @return FailedDelivery|null
     */
    public function findById(int $id): ?FailedDelivery
    {
        $repository = $this->entityManager->getRepository(FailedDelivery::class);
        return $repository->find($id);
    }

    /**
     * Update retry count and next retry time
     *
     * @param FailedDelivery $failedDelivery Delivery to update
     * @param int $retryCount New retry count
     * @param \DateTimeInterface $nextRetryAt Next retry time
     * @return void
     */
    public function updateRetryInfo(
        FailedDelivery $failedDelivery,
        int $retryCount,
        \DateTimeInterface $nextRetryAt
    ): void {
        $failedDelivery->setRetryCount($retryCount);
        $failedDelivery->setNextRetryAt($nextRetryAt);
        $this->entityManager->flush();
    }
}

