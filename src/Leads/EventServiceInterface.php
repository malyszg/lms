<?php

declare(strict_types=1);

namespace App\Leads;

use App\Model\Event;
use App\Model\Lead;

/**
 * Event service interface
 * Logs system events for audit and monitoring
 */
interface EventServiceInterface
{
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
    ): Event;

    /**
     * Log lead created event
     *
     * @param Lead $lead
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @return Event
     */
    public function logLeadCreated(Lead $lead, ?string $ipAddress = null, ?string $userAgent = null): Event;

    /**
     * Log CDP delivery success event
     *
     * @param Lead $lead
     * @param string $cdpSystemName
     * @return Event
     */
    public function logCdpDeliverySuccess(Lead $lead, string $cdpSystemName): Event;

    /**
     * Log CDP delivery failed event
     *
     * @param Lead $lead
     * @param string $cdpSystemName
     * @param string $errorMessage
     * @return Event
     */
    public function logCdpDeliveryFailed(Lead $lead, string $cdpSystemName, string $errorMessage): Event;

    /**
     * Log login attempt (success or failure)
     *
     * @param string $username Email of user attempting login
     * @param bool $success Whether login was successful
     * @param string|null $ipAddress IP address of request
     * @param string|null $userAgent User agent string
     * @param string|null $failureReason Reason for failure if unsuccessful
     * @return Event
     */
    public function logLoginAttempt(
        string $username,
        bool $success,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $failureReason = null
    ): Event;

    /**
     * Log user logout
     *
     * @param int $userId User ID
     * @param string $username User email
     * @param string|null $ipAddress IP address of request
     * @param string|null $userAgent User agent string
     * @return Event
     */
    public function logLogout(
        int $userId,
        string $username,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): Event;

    /**
     * Log password change
     *
     * @param int $userId User ID
     * @param string $username User email
     * @param string|null $ipAddress IP address of request
     * @return Event
     */
    public function logPasswordChange(
        int $userId,
        string $username,
        ?string $ipAddress = null
    ): Event;

    /**
     * Log lead deleted event
     *
     * @param Lead $lead Lead that was deleted
     * @param string|null $ipAddress IP address of request
     * @param string|null $userAgent User agent string
     * @return Event
     */
    public function logLeadDeleted(Lead $lead, ?string $ipAddress = null, ?string $userAgent = null): Event;

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
    ): Event;
}

