<?php

declare(strict_types=1);

namespace App\Leads;

use App\Model\FailedDelivery;
use App\Model\Lead;

/**
 * Failed Delivery Service Interface
 * Handles failed CDP delivery management
 */
interface FailedDeliveryServiceInterface
{
    /**
     * Create failed delivery record
     *
     * @param Lead $lead Lead that failed to deliver
     * @param string $cdpSystem CDP system name (SalesManago, Murapol, DomDevelopment)
     * @param string $errorMessage Error message
     * @param string|null $errorCode Error code (HTTP status code)
     * @return FailedDelivery Created failed delivery
     */
    public function createFailedDelivery(
        Lead $lead,
        string $cdpSystem,
        string $errorMessage,
        ?string $errorCode = null
    ): FailedDelivery;

    /**
     * Get pending deliveries that need retry
     *
     * @param int $limit Maximum number of deliveries to retrieve
     * @return array<FailedDelivery> Array of pending FailedDelivery records
     */
    public function getPendingDeliveries(int $limit = 100): array;

    /**
     * Retry failed delivery
     *
     * @param FailedDelivery $failedDelivery Delivery to retry
     * @return void
     */
    public function retryDelivery(FailedDelivery $failedDelivery): void;

    /**
     * Mark delivery as resolved (successfully sent)
     *
     * @param FailedDelivery $failedDelivery Delivery to mark as resolved
     * @return void
     */
    public function markAsResolved(FailedDelivery $failedDelivery): void;

    /**
     * Mark delivery as final failure (retry limit exceeded)
     *
     * @param FailedDelivery $failedDelivery Delivery to mark as failed
     * @return void
     */
    public function markAsFailed(FailedDelivery $failedDelivery): void;

    /**
     * Find failed delivery by ID
     *
     * @param int $id Failed delivery ID
     * @return FailedDelivery|null
     */
    public function findById(int $id): ?FailedDelivery;

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
    ): void;
}

