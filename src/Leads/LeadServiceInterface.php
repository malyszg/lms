<?php

declare(strict_types=1);

namespace App\Leads;

use App\DTO\CreateLeadRequest;
use App\DTO\CreateLeadResponse;
use App\Model\Lead;

/**
 * Lead service interface
 * Handles lead creation and management
 */
interface LeadServiceInterface
{
    /**
     * Create new lead with customer and property data
     * Implements full transaction: customer deduplication, lead creation, property creation
     *
     * @param CreateLeadRequest $request
     * @param string|null $ipAddress Client IP address for logging
     * @param string|null $userAgent Client user agent for logging
     * @return CreateLeadResponse
     * @throws \App\Exception\LeadAlreadyExistsException If lead with given UUID already exists
     * @throws \Exception On database errors
     */
    public function createLead(
        CreateLeadRequest $request,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): CreateLeadResponse;

    /**
     * Check if lead with given UUID already exists
     *
     * @param string $leadUuid
     * @return bool
     */
    public function leadExists(string $leadUuid): bool;

    /**
     * Find lead by UUID
     *
     * @param string $leadUuid
     * @return Lead|null
     */
    public function findByUuid(string $leadUuid): ?Lead;

    /**
     * Delete lead by UUID
     * Implements full transaction: logging event, cascade delete
     *
     * @param string $leadUuid UUID of lead to delete
     * @param string|null $ipAddress Client IP address for logging
     * @param string|null $userAgent Client user agent for logging
     * @return void
     * @throws \App\Exception\LeadNotFoundException If lead with given UUID doesn't exist
     * @throws \Exception On database errors
     */
    public function deleteLead(
        string $leadUuid,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void;
}

