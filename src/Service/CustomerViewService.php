<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\CustomerDetailDto;
use App\DTO\CustomerFiltersDto;
use App\DTO\CustomerStatsDto;
use App\DTO\CustomersListApiResponse;
use App\DTO\PaginationDto;
use App\DTO\UpdatePreferencesRequest;
use App\Leads\CustomerServiceInterface;
use Psr\Log\LoggerInterface;

/**
 * Customer view service
 * Handles business logic for customer view operations
 */
class CustomerViewService implements CustomerViewServiceInterface
{
    public function __construct(
        private readonly CustomerServiceInterface $customerService,
        private readonly ?LoggerInterface $logger = null
    ) {}

    /**
     * Get customers list with filters and pagination
     *
     * @param CustomerFiltersDto $filters
     * @param int $page
     * @param int $limit
     * @return CustomersListApiResponse
     */
    public function getCustomersList(CustomerFiltersDto $filters, int $page = 1, int $limit = 20): CustomersListApiResponse
    {
        try {
            $result = $this->customerService->getCustomersList($filters, $page, $limit);
            
            $pagination = PaginationDto::fromArray([
                'current_page' => $result['pagination']['current_page'],
                'per_page' => $result['pagination']['items_per_page'],
                'total' => $result['pagination']['total_items'],
                'last_page' => $result['pagination']['total_pages']
            ]);

            return new CustomersListApiResponse(
                $result['customers'],
                $pagination
            );
        } catch (\Exception $e) {
            if ($this->logger !== null) {
                $this->logger->error('Failed to get customers list', [
                    'filters' => $filters->toQueryParams(),
                    'page' => $page,
                    'limit' => $limit,
                    'error' => $e->getMessage()
                ]);
            }
            throw $e;
        }
    }

    /**
     * Get customer details with leads and preferences
     *
     * @param int $customerId
     * @return CustomerDetailDto
     */
    public function getCustomerDetails(int $customerId): CustomerDetailDto
    {
        try {
            return $this->customerService->getCustomerDetails($customerId);
        } catch (\Exception $e) {
            if ($this->logger !== null) {
                $this->logger->error('Failed to get customer details', [
                    'customer_id' => $customerId,
                    'error' => $e->getMessage()
                ]);
            }
            throw $e;
        }
    }

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
    ): array {
        try {
            return $this->customerService->updateCustomerPreferences(
                $customerId,
                $request,
                $userId,
                $ipAddress,
                $userAgent
            );
        } catch (\Exception $e) {
            if ($this->logger !== null) {
                $this->logger->error('Failed to update customer preferences', [
                    'customer_id' => $customerId,
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
            }
            throw $e;
        }
    }

    /**
     * Get customer statistics
     *
     * @return CustomerStatsDto
     */
    public function getCustomerStats(): CustomerStatsDto
    {
        try {
            return $this->customerService->getCustomerStats();
        } catch (\Exception $e) {
            if ($this->logger !== null) {
                $this->logger->error('Failed to get customer stats', [
                    'error' => $e->getMessage()
                ]);
            }
            throw $e;
        }
    }
}
