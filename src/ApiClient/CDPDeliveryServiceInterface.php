<?php

declare(strict_types=1);

namespace App\ApiClient;

use App\Model\FailedDelivery;
use App\Model\Lead;

/**
 * CDP Delivery Service Interface
 * Handles sending leads to CDP systems (SalesManago, Murapol, DomDevelopment)
 */
interface CDPDeliveryServiceInterface
{
    /**
     * Send lead to all configured CDP systems
     *
     * @param Lead $lead
     * @return void
     */
    public function sendLeadToCDP(Lead $lead): void;

    /**
     * Send lead to single CDP system
     *
     * @param Lead $lead
     * @param string $cdpSystem CDP system name
     * @return void
     */
    public function sendToSingleCDP(Lead $lead, string $cdpSystem): void;

    /**
     * Retry failed delivery
     *
     * @param FailedDelivery $failedDelivery Delivery to retry
     * @return void
     */
    public function retryFailedDelivery(FailedDelivery $failedDelivery): void;

    /**
     * Get list of configured CDP systems
     *
     * @return array<string>
     */
    public function getConfiguredSystems(): array;
}































