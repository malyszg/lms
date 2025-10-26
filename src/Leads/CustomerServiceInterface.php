<?php

declare(strict_types=1);

namespace App\Leads;

use App\DTO\CreateCustomerDto;
use App\DTO\UpdatePreferencesRequest;
use App\DTO\CustomerDetailDto;
use App\DTO\CustomerStatsDto;
use App\DTO\CustomerFiltersDto;
use App\Model\Customer;

/**
 * Customer service interface
 * Handles customer creation and deduplication
 */
interface CustomerServiceInterface
{
    /**
     * Find existing customer by email and phone, or create new one
     * Implements deduplication logic based on unique email+phone combination
     *
     * @param CreateCustomerDto $customerDto
     * @return Customer
     */
    public function findOrCreateCustomer(CreateCustomerDto $customerDto): Customer;

    /**
     * Find customer by email and phone combination
     *
     * @param string $email
     * @param string $phone
     * @return Customer|null
     * @throws \RuntimeException On database errors
     */
    public function findByEmailAndPhone(string $email, string $phone): ?Customer;

    /**
     * Update customer preferences
     * Implements full transaction: validation, preferences update, logging event
     *
     * @param int $customerId Customer ID
     * @param UpdatePreferencesRequest $request Request with new preferences
     * @param int|null $userId User ID who made the change
     * @param string|null $ipAddress Client IP address for logging
     * @param string|null $userAgent Client user agent for logging
     * @return array Updated preferences data
     * @throws \App\Exception\ValidationException If validation fails
     * @throws \App\Exception\CustomerNotFoundException If customer doesn't exist
     * @throws \Exception On database errors
     */
    public function updateCustomerPreferences(
        int $customerId,
        UpdatePreferencesRequest $request,
        ?int $userId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array;

    /**
     * Get customer preferences
     *
     * @param int $customerId
     * @return array|null
     */
    public function getCustomerPreferences(int $customerId): ?array;

    /**
     * Get customers list with filters and pagination
     *
     * @param CustomerFiltersDto $filters
     * @param int $page
     * @param int $limit
     * @return array{customers: array, pagination: array}
     */
    public function getCustomersList(CustomerFiltersDto $filters, int $page = 1, int $limit = 20): array;

    /**
     * Get customer details with leads and preferences
     *
     * @param int $customerId
     * @return CustomerDetailDto
     * @throws \App\Exception\CustomerNotFoundException
     */
    public function getCustomerDetails(int $customerId): CustomerDetailDto;

    /**
     * Get customer statistics
     *
     * @return CustomerStatsDto
     */
    public function getCustomerStats(): CustomerStatsDto;
}


