<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\CustomerDetailDto;
use App\DTO\CustomerFiltersDto;
use App\DTO\CustomerStatsDto;
use App\DTO\CustomersListApiResponse;
use App\DTO\UpdatePreferencesRequest;

/**
 * Customer view service interface
 * Handles business logic for customer view operations
 */
interface CustomerViewServiceInterface
{
    /**
     * Get customers list with filters and pagination
     *
     * @param CustomerFiltersDto $filters
     * @param int $page
     * @param int $limit
     * @return CustomersListApiResponse
     */
    public function getCustomersList(CustomerFiltersDto $filters, int $page = 1, int $limit = 20): CustomersListApiResponse;

    /**
     * Get customer details with leads and preferences
     *
     * @param int $customerId
     * @return CustomerDetailDto
     */
    public function getCustomerDetails(int $customerId): CustomerDetailDto;

    /**
     * Update customer preferences
     *
     * @param int $customerId
     * @param UpdatePreferencesRequest $request
     * @param int|null $userId
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @return array
     */
    public function updateCustomerPreferences(
        int $customerId,
        UpdatePreferencesRequest $request,
        ?int $userId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array;

    /**
     * Get customer statistics
     *
     * @return CustomerStatsDto
     */
    public function getCustomerStats(): CustomerStatsDto;
}
