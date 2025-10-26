<?php

declare(strict_types=1);

namespace App\ApiClient;

use App\Model\Lead;

/**
 * CDP Payload Transformer Interface
 * Transforms lead data to CDP system-specific format
 */
interface CDPPayloadTransformerInterface
{
    /**
     * Transform lead data for SalesManago
     *
     * @param Lead $lead Lead to transform
     * @return array<string, mixed> SalesManago payload
     */
    public function transformForSalesManago(Lead $lead): array;

    /**
     * Transform lead data for Murapol
     *
     * @param Lead $lead Lead to transform
     * @return array<string, mixed> Murapol payload
     */
    public function transformForMurapol(Lead $lead): array;

    /**
     * Transform lead data for DomDevelopment
     *
     * @param Lead $lead Lead to transform
     * @return array<string, mixed> DomDevelopment payload
     */
    public function transformForDomDevelopment(Lead $lead): array;
}

