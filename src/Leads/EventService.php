<?php

declare(strict_types=1);

namespace App\Leads;

use App\Model\Event;
use App\Model\Lead;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Event service implementation
 * Logs system events for audit and monitoring
 */
class EventService implements EventServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {}

    /**
     * Log API request event
     *
     * @param string $endpoint
     * @param string $method
     * @param int $statusCode
     * @param array<string, mixed> $details
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @param string|null $errorMessage
     * @return Event
     */
    public function logApiRequest(
        string $endpoint,
        string $method,
        int $statusCode,
        array $details,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $errorMessage = null
    ): Event {
        $event = new Event('api_request');
        $event->setEntityType('api');
        $event->setDetails([
            'endpoint' => $endpoint,
            'method' => $method,
            'status_code' => $statusCode,
            ...$details,
        ]);

        if ($ipAddress !== null) {
            $event->setIpAddress($ipAddress);
        }

        if ($userAgent !== null) {
            $event->setUserAgent($userAgent);
        }

        if ($errorMessage !== null) {
            $event->setErrorMessage($errorMessage);
        }

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return $event;
    }

    /**
     * Log lead created event
     *
     * @param Lead $lead
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @return Event
     */
    public function logLeadCreated(Lead $lead, ?string $ipAddress = null, ?string $userAgent = null): Event
    {
        $event = new Event('lead_created');
        $event->setEntityType('lead');
        $event->setEntityId($lead->getId());
        $event->setDetails([
            'lead_uuid' => $lead->getLeadUuid(),
            'customer_id' => $lead->getCustomer()->getId(),
            'application_name' => $lead->getApplicationName(),
        ]);

        if ($ipAddress !== null) {
            $event->setIpAddress($ipAddress);
        }

        if ($userAgent !== null) {
            $event->setUserAgent($userAgent);
        }

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return $event;
    }

    /**
     * Log CDP delivery success event
     *
     * @param Lead $lead
     * @param string $cdpSystemName
     * @return Event
     */
    public function logCdpDeliverySuccess(Lead $lead, string $cdpSystemName): Event
    {
        $event = new Event('cdp_delivery_success');
        $event->setEntityType('lead');
        $event->setEntityId($lead->getId());
        $event->setDetails([
            'lead_uuid' => $lead->getLeadUuid(),
            'cdp_system' => $cdpSystemName,
        ]);

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return $event;
    }

    /**
     * Log CDP delivery failed event
     *
     * @param Lead $lead
     * @param string $cdpSystemName
     * @param string $errorMessage
     * @return Event
     */
    public function logCdpDeliveryFailed(Lead $lead, string $cdpSystemName, string $errorMessage): Event
    {
        $event = new Event('cdp_delivery_failed');
        $event->setEntityType('lead');
        $event->setEntityId($lead->getId());
        $event->setErrorMessage($errorMessage);
        $event->setDetails([
            'lead_uuid' => $lead->getLeadUuid(),
            'cdp_system' => $cdpSystemName,
        ]);

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return $event;
    }

    public function logLoginAttempt(
        string $username,
        bool $success,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $failureReason = null
    ): Event {
        $eventType = $success ? 'login_success' : 'login_failure';
        $event = new Event($eventType);
        $event->setEntityType('user');
        
        $details = [
            'email' => $username,
            'success' => $success,
        ];

        if ($failureReason) {
            $details['failure_reason'] = $failureReason;
        }

        $event->setDetails($details);

        if ($ipAddress !== null) {
            $event->setIpAddress($ipAddress);
        }

        if ($userAgent !== null) {
            $event->setUserAgent($userAgent);
        }

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return $event;
    }

    public function logLogout(
        int $userId,
        string $username,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): Event {
        $event = new Event('logout');
        $event->setEntityType('user');
        $event->setEntityId($userId);
        $event->setDetails([
            'user_id' => $userId,
            'email' => $username,
        ]);

        if ($ipAddress !== null) {
            $event->setIpAddress($ipAddress);
        }

        if ($userAgent !== null) {
            $event->setUserAgent($userAgent);
        }

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return $event;
    }

    public function logPasswordChange(
        int $userId,
        string $username,
        ?string $ipAddress = null
    ): Event {
        $event = new Event('password_change');
        $event->setEntityType('user');
        $event->setEntityId($userId);
        $event->setDetails([
            'user_id' => $userId,
            'email' => $username,
        ]);

        if ($ipAddress !== null) {
            $event->setIpAddress($ipAddress);
        }

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return $event;
    }

    /**
     * Log lead deleted event
     *
     * @param Lead $lead Lead that was deleted
     * @param string|null $ipAddress IP address of request
     * @param string|null $userAgent User agent string
     * @return Event
     */
    public function logLeadDeleted(Lead $lead, ?string $ipAddress = null, ?string $userAgent = null): Event
    {
        $event = new Event('lead_deleted');
        $event->setEntityType('lead');
        $event->setEntityId($lead->getId());
        $event->setDetails([
            'lead_uuid' => $lead->getLeadUuid(),
            'customer_id' => $lead->getCustomer()->getId(),
            'application_name' => $lead->getApplicationName(),
            'status' => $lead->getStatus(),
        ]);

        if ($ipAddress !== null) {
            $event->setIpAddress($ipAddress);
        }

        if ($userAgent !== null) {
            $event->setUserAgent($userAgent);
        }

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return $event;
    }

    /**
     * Log lead status changed event
     *
     * @param Lead $lead Lead that was updated
     * @param string $oldStatus Previous status
     * @param string $newStatus New status
     * @param int|null $userId User ID who made the change
     * @param string|null $ipAddress IP address of request
     * @param string|null $userAgent User agent string
     * @return Event
     */
    public function logLeadStatusChanged(
        Lead $lead,
        string $oldStatus,
        string $newStatus,
        ?int $userId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): Event {
        $event = new Event('lead_status_changed');
        $event->setEntityType('lead');
        $event->setEntityId($lead->getId());
        $event->setDetails([
            'lead_uuid' => $lead->getLeadUuid(),
            'customer_id' => $lead->getCustomer()->getId(),
            'application_name' => $lead->getApplicationName(),
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
        ]);

        if ($userId !== null) {
            $event->setUserId($userId);
        }

        if ($ipAddress !== null) {
            $event->setIpAddress($ipAddress);
        }

        if ($userAgent !== null) {
            $event->setUserAgent($userAgent);
        }

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return $event;
    }
}














