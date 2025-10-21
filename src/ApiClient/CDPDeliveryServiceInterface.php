<?php

declare(strict_types=1);

namespace App\ApiClient;

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
     * Get list of configured CDP systems
     *
     * @return array<string>
     */
    public function getConfiguredSystems(): array;
}































