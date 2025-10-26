<?php

declare(strict_types=1);

namespace App\Leads;

use App\DTO\CreateCustomerDto;
use App\DTO\UpdatePreferencesRequest;
use App\DTO\CustomerDetailDto;
use App\DTO\CustomerStatsDto;
use App\DTO\CustomerFiltersDto;
use App\DTO\PreferencesDto;
use App\DTO\LeadItemDto;
use App\Exception\CustomerNotFoundException;
use App\Exception\ValidationException;
use App\Model\Customer;
use App\Model\Event;
use App\Model\FailedDelivery;
use App\Model\Lead;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;
use Psr\Log\LoggerInterface;

/**
 * Customer service implementation
 * Handles customer creation with deduplication based on email+phone
 */
class CustomerService implements CustomerServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EventServiceInterface $eventService,
        private readonly ?LoggerInterface $logger = null
    ) {}

    /**
     * Find existing customer or create new one
     * Uses pessimistic locking to prevent race conditions during deduplication
     *
     * @param CreateCustomerDto $customerDto
     * @return Customer
     */
    public function findOrCreateCustomer(CreateCustomerDto $customerDto): Customer
    {
        // Try to find existing customer with pessimistic lock
        $existingCustomer = $this->findByEmailAndPhone($customerDto->email, $customerDto->phone);

        if ($existingCustomer !== null) {
            return $existingCustomer;
        }

        // Create new customer
        $customer = new Customer(
            $customerDto->email,
            $customerDto->phone,
            $customerDto->firstName,
            $customerDto->lastName
        );

        $this->entityManager->persist($customer);
        $this->entityManager->flush();

        return $customer;
    }

    /**
     * Find customer by email and phone combination
     * Uses pessimistic write lock to prevent concurrent inserts of same customer
     *
     * @param string $email
     * @param string $phone
     * @return Customer|null
     * @throws \Doctrine\DBAL\Exception On database errors
     */
    public function findByEmailAndPhone(string $email, string $phone): ?Customer
    {
        $repository = $this->entityManager->getRepository(Customer::class);

        try {
            // Use pessimistic write lock for deduplication
            $customers = $repository->createQueryBuilder('c')
                ->where('c.email = :email')
                ->andWhere('c.phone = :phone')
                ->setParameter('email', $email)
                ->setParameter('phone', $phone)
                ->setMaxResults(1)
                ->getQuery()
                ->setLockMode(LockMode::PESSIMISTIC_WRITE)
                ->getResult();
            
            return $customers ? $customers[0] : null;
        } catch (\Doctrine\DBAL\Exception $e) {
            // Re-throw database exceptions with more context
            throw new \RuntimeException(
                sprintf('Database error while searching for customer (email: %s): %s', $email, $e->getMessage()),
                0,
                $e
            );
        }
    }

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
     * @throws ValidationException If validation fails
     * @throws CustomerNotFoundException If customer doesn't exist
     * @throws \Exception On database errors
     */
    public function updateCustomerPreferences(
        int $customerId,
        UpdatePreferencesRequest $request,
        ?int $userId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): array {
        // Validate preferences data
        $validationErrors = $this->validatePreferences($request);
        if (!empty($validationErrors)) {
            throw new ValidationException($validationErrors);
        }

        // Start transaction
        $this->entityManager->beginTransaction();

        try {
            // Find customer
            $customer = $this->entityManager->getRepository(Customer::class)->find($customerId);
            if (!$customer) {
                throw new CustomerNotFoundException($customerId);
            }

            // Get current preferences for logging
            if ($this->logger !== null) {
                $this->logger->debug('Getting current preferences', ['customer_id' => $customerId]);
            }
            $currentPreferences = $this->getCurrentPreferences($customerId);
            if ($this->logger !== null) {
                $this->logger->debug('Got current preferences', ['preferences' => $currentPreferences]);
            }

            // Update or create preferences
            $preferencesData = $this->updateOrCreatePreferences($customerId, $request);

            // Log preferences change event
            $this->eventService->logCustomerPreferencesChanged(
                $customer,
                $currentPreferences,
                $preferencesData,
                $userId,
                $ipAddress,
                $userAgent
            );

            // Commit transaction
            $this->entityManager->commit();

            // Log success
            if ($this->logger !== null) {
                $this->logger->info('Customer preferences updated successfully', [
                    'customer_id' => $customerId,
                    'user_id' => $userId,
                    'ip_address' => $ipAddress,
                    'preferences' => $preferencesData,
                ]);
            }

            return $preferencesData;

        } catch (\Exception $e) {
            // Rollback transaction on any error
            $this->entityManager->rollback();

            // Log error
            if ($this->logger !== null) {
                $this->logger->error('Failed to update customer preferences', [
                    'customer_id' => $customerId,
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                    'ip_address' => $ipAddress,
                ]);
            }

            throw $e;
        }
    }

    /**
     * Validate preferences data
     *
     * @param UpdatePreferencesRequest $request
     * @return array Validation errors
     */
    private function validatePreferences(UpdatePreferencesRequest $request): array
    {
        $errors = [];

        // Debug: log the values being validated
        if ($this->logger !== null) {
            $this->logger->debug('Validating preferences', [
                'price_min' => $request->priceMin,
                'price_max' => $request->priceMax,
                'price_min_type' => gettype($request->priceMin),
                'price_max_type' => gettype($request->priceMax),
            ]);
        }

        // Validate price range
        if ($request->priceMin !== null && $request->priceMax !== null) {
            if ($request->priceMin >= $request->priceMax) {
                $errors['price_range'] = 'Cena maksymalna nie może być mniejsza lub równa cenie minimalnej';
                if ($this->logger !== null) {
                    $this->logger->debug('Price range validation failed', [
                        'price_min' => $request->priceMin,
                        'price_max' => $request->priceMax,
                        'errors' => $errors
                    ]);
                }
            }
        }

        // Validate price values
        if ($request->priceMin !== null && $request->priceMin < 0) {
            $errors['price_min'] = 'Cena minimalna nie może być ujemna';
        }

        if ($request->priceMax !== null && $request->priceMax < 0) {
            $errors['price_max'] = 'Cena maksymalna nie może być ujemna';
        }

        // Validate string lengths
        if ($request->location !== null && strlen($request->location) > 255) {
            $errors['location'] = 'Lokalizacja nie może przekraczać 255 znaków';
        }

        if ($request->city !== null && strlen($request->city) > 100) {
            $errors['city'] = 'Miasto nie może przekraczać 100 znaków';
        }

        if ($this->logger !== null && !empty($errors)) {
            $this->logger->debug('Validation errors found', ['errors' => $errors]);
        }
        
        return $errors;
    }

    /**
     * Get current preferences for customer
     *
     * @param int $customerId
     * @return array|null
     */
    private function getCurrentPreferences(int $customerId): ?array
    {
        try {
            $sql = 'SELECT price_min, price_max, location, city FROM customer_preferences WHERE customer_id = :customer_id LIMIT 1';
            $result = $this->entityManager->getConnection()->executeQuery($sql, ['customer_id' => $customerId]);
            
            $data = $result->fetchAssociative();
            return $data !== false ? $data : null;
        } catch (\Exception $e) {
            if ($this->logger !== null) {
                $this->logger->error('Failed to get current preferences', [
                    'customer_id' => $customerId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
            return null;
        }
    }

    /**
     * Update or create customer preferences
     *
     * @param int $customerId
     * @param UpdatePreferencesRequest $request
     * @return array Updated preferences data
     */
    private function updateOrCreatePreferences(int $customerId, UpdatePreferencesRequest $request): array
    {
        $sql = 'INSERT INTO customer_preferences (customer_id, price_min, price_max, location, city, created_at, updated_at) 
                VALUES (:customer_id, :price_min, :price_max, :location, :city, NOW(), NOW())
                ON DUPLICATE KEY UPDATE 
                price_min = VALUES(price_min),
                price_max = VALUES(price_max),
                location = VALUES(location),
                city = VALUES(city),
                updated_at = NOW()';

        try {
            $this->entityManager->getConnection()->executeStatement($sql, [
                'customer_id' => $customerId,
                'price_min' => $request->priceMin,
                'price_max' => $request->priceMax,
                'location' => $request->location,
                'city' => $request->city,
            ]);
            
            if ($this->logger !== null) {
                $this->logger->debug('Successfully executed preferences update query');
            }
        } catch (\Exception $e) {
            if ($this->logger !== null) {
                $this->logger->error('Failed to execute preferences update query', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
            throw $e;
        }

        return [
            'customer_id' => $customerId,
            'price_min' => $request->priceMin,
            'price_max' => $request->priceMax,
            'location' => $request->location,
            'city' => $request->city,
            'updated_at' => new \DateTime(),
        ];
    }

    /**
     * Get customer preferences
     *
     * @param int $customerId
     * @return array|null
     */
    public function getCustomerPreferences(int $customerId): ?array
    {
        return $this->getCurrentPreferences($customerId);
    }

    /**
     * Get customers list with filters and pagination
     *
     * @param CustomerFiltersDto $filters
     * @param int $page
     * @param int $limit
     * @return array{customers: array, pagination: array}
     */
    public function getCustomersList(CustomerFiltersDto $filters, int $page = 1, int $limit = 20): array
    {
        // Validate and sanitize parameters
        $page = max(1, $page);
        $limit = max(1, min(100, $limit)); // Limit between 1 and 100
        
        $offset = ($page - 1) * $limit;
        
        $qb = $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from(Customer::class, 'c')
            ->leftJoin(Lead::class, 'l', 'WITH', 'l.customer = c')
            ->groupBy('c.id');

        // Apply filters
        if ($filters->email !== null) {
            $qb->andWhere('c.email LIKE :email')
               ->setParameter('email', '%' . $filters->email . '%');
        }

        if ($filters->phone !== null) {
            $qb->andWhere('c.phone LIKE :phone')
               ->setParameter('phone', '%' . $filters->phone . '%');
        }

        if ($filters->createdFrom !== null) {
            $qb->andWhere('c.createdAt >= :createdFrom')
               ->setParameter('createdFrom', $filters->createdFrom);
        }

        if ($filters->createdTo !== null) {
            // Add time to include the entire day
            $endOfDay = \DateTime::createFromFormat('Y-m-d H:i:s', $filters->createdTo->format('Y-m-d') . ' 23:59:59');
            $qb->andWhere('c.createdAt <= :createdTo')
               ->setParameter('createdTo', $endOfDay);
        }

        if ($filters->minLeads !== null || $filters->maxLeads !== null) {
            $havingConditions = [];
            $parameters = [];
            
            if ($filters->minLeads !== null) {
                $havingConditions[] = 'COUNT(l.id) >= :minLeads';
                $parameters['minLeads'] = $filters->minLeads;
            }
            
            if ($filters->maxLeads !== null) {
                $havingConditions[] = 'COUNT(l.id) <= :maxLeads';
                $parameters['maxLeads'] = $filters->maxLeads;
            }
            
            $qb->having(implode(' AND ', $havingConditions));
            
            foreach ($parameters as $key => $value) {
                $qb->setParameter($key, $value);
            }
        }

        // Apply sorting
        $allowedSorts = ['created_at', 'email', 'phone', 'leads_count'];
        $sort = in_array($filters->sort, $allowedSorts) ? $filters->sort : 'created_at';
        $order = in_array($filters->order, ['asc', 'desc']) ? $filters->order : 'desc';

        if ($sort === 'leads_count') {
            $qb->orderBy('COUNT(l.id)', $order);
        } else {
            // Map database field names to entity property names
            $fieldMapping = [
                'created_at' => 'createdAt',
                'email' => 'email',
                'phone' => 'phone'
            ];
            $entityField = $fieldMapping[$sort] ?? $sort;
            $qb->orderBy('c.' . $entityField, $order);
        }

        // Get total count for pagination
        $countQb = clone $qb;
        $countQb->select('COUNT(DISTINCT c.id)');
        
        try {
            $totalCount = (int) $countQb->getQuery()->getSingleScalarResult();
        } catch (\Doctrine\ORM\NoResultException | \Doctrine\ORM\NonUniqueResultException $e) {
            // If no result is found (e.g., no customers match filters), default to 0
            $totalCount = 0;
        }

        // Apply pagination
        $qb->setFirstResult($offset)
           ->setMaxResults($limit);

        $customers = $qb->getQuery()->getResult();
        
        // Ensure customers is always an array
        if ($customers === null) {
            $customers = [];
        }

        // Convert to DTOs
        $customerDtos = [];
        foreach ($customers as $customer) {
            // Count leads for this customer
            $leadsCountQb = $this->entityManager->getRepository(Lead::class)
                ->createQueryBuilder('l')
                ->select('COUNT(l.id)')
                ->where('l.customer = :customer')
                ->setParameter('customer', $customer);
            
            try {
                $leadsCount = (int) $leadsCountQb->getQuery()->getSingleScalarResult();
            } catch (\Doctrine\ORM\NoResultException | \Doctrine\ORM\NonUniqueResultException $e) {
                $leadsCount = 0;
            }

            $customerDtos[] = new \App\DTO\CustomerDto(
                $customer->getId(),
                $customer->getEmail(),
                $customer->getPhone(),
                $customer->getFirstName(),
                $customer->getLastName(),
                $customer->getCreatedAt(),
                (int) $leadsCount
            );
        }

        $totalPages = max(1, (int) ceil($totalCount / $limit));
        
        // Ensure page is not greater than total pages
        $page = min($page, $totalPages);

        return [
            'customers' => $customerDtos,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_items' => $totalCount,
                'items_per_page' => $limit,
                'has_next' => $page < $totalPages,
                'has_previous' => $page > 1
            ]
        ];
    }

    /**
     * Get customer details with leads and preferences
     *
     * @param int $customerId
     * @return CustomerDetailDto
     * @throws CustomerNotFoundException
     */
    public function getCustomerDetails(int $customerId): CustomerDetailDto
    {
        $customer = $this->entityManager->getRepository(Customer::class)->find($customerId);
        if (!$customer) {
            throw new CustomerNotFoundException($customerId);
        }

        // Get preferences
        $preferencesData = $this->getCurrentPreferences($customerId);
        $preferences = $preferencesData ? new PreferencesDto(
            $preferencesData['price_min'] !== null ? (float) $preferencesData['price_min'] : null,
            $preferencesData['price_max'] !== null ? (float) $preferencesData['price_max'] : null,
            $preferencesData['location'],
            $preferencesData['city']
        ) : new PreferencesDto(null, null, null, null);

        // Get leads
        $leads = $this->entityManager->getRepository(Lead::class)
            ->createQueryBuilder('l')
            ->where('l.customer = :customer')
            ->setParameter('customer', $customer)
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $leadDtos = [];
        $newLeads = 0;
        $contactedLeads = 0;
        $qualifiedLeads = 0;
        $convertedLeads = 0;

        foreach ($leads as $lead) {
            $customerDto = new \App\DTO\CustomerDto(
                $lead->getCustomer()->getId(),
                $lead->getCustomer()->getEmail(),
                $lead->getCustomer()->getPhone(),
                $lead->getCustomer()->getFirstName(),
                $lead->getCustomer()->getLastName(),
                $lead->getCustomer()->getCreatedAt()
            );

            $propertyDto = $lead->getProperty() ? new \App\DTO\PropertyDto(
                (string) $lead->getProperty()->getId(),
                null, // developmentId
                null, // partnerId
                null, // propertyType
                $lead->getProperty()->getPrice(),
                $lead->getProperty()->getLocation(),
                $lead->getProperty()->getCity()
            ) : new \App\DTO\PropertyDto(null, null, null, null, null, null, null);

            // Determine CDP delivery status
            $cdpDeliveryStatus = $this->determineCdpDeliveryStatus($lead);
            
            $leadDtos[] = new LeadItemDto(
                $lead->getId(),
                $lead->getLeadUuid(),
                $lead->getStatus(),
                LeadItemDto::getStatusLabel($lead->getStatus()),
                $lead->getCreatedAt(),
                $customerDto,
                $lead->getApplicationName(),
                $propertyDto,
                $cdpDeliveryStatus
            );

            // Count leads by status
            switch ($lead->getStatus()) {
                case 'new':
                    $newLeads++;
                    break;
                case 'contacted':
                    $contactedLeads++;
                    break;
                case 'qualified':
                    $qualifiedLeads++;
                    break;
                case 'converted':
                    $convertedLeads++;
                    break;
            }
        }

        return new CustomerDetailDto(
            $customer->getId(),
            $customer->getEmail(),
            $customer->getPhone(),
            $customer->getFirstName(),
            $customer->getLastName(),
            $customer->getCreatedAt(),
            $customer->getUpdatedAt(),
            $preferences,
            $leadDtos,
            count($leads),
            $newLeads,
            $contactedLeads,
            $qualifiedLeads,
            $convertedLeads
        );
    }

    /**
     * Get customer statistics
     *
     * @return CustomerStatsDto
     */
    public function getCustomerStats(): CustomerStatsDto
    {
        // Total customers
        $totalCustomersQb = $this->entityManager->getRepository(Customer::class)
            ->createQueryBuilder('c')
            ->select('COUNT(c.id)');
        
        try {
            $totalCustomers = (int) $totalCustomersQb->getQuery()->getSingleScalarResult();
        } catch (\Doctrine\ORM\NoResultException | \Doctrine\ORM\NonUniqueResultException $e) {
            $totalCustomers = 0;
        }

        // Customers with leads
        $customersWithLeadsQb = $this->entityManager->getRepository(Customer::class)
            ->createQueryBuilder('c')
            ->select('COUNT(DISTINCT c.id)')
            ->join(Lead::class, 'l', 'WITH', 'l.customer = c');
        
        try {
            $customersWithLeads = (int) $customersWithLeadsQb->getQuery()->getSingleScalarResult();
        } catch (\Doctrine\ORM\NoResultException | \Doctrine\ORM\NonUniqueResultException $e) {
            $customersWithLeads = 0;
        }

        // Customers with preferences
        try {
            $preferencesResult = $this->entityManager->getConnection()
                ->executeQuery('SELECT COUNT(DISTINCT customer_id) as count FROM customer_preferences')
                ->fetchAssociative();
            $customersWithPreferences = $preferencesResult ? (int) $preferencesResult['count'] : 0;
        } catch (\Exception $e) {
            if ($this->logger !== null) {
                $this->logger->warning('Failed to get customers with preferences', ['error' => $e->getMessage()]);
            }
            $customersWithPreferences = 0;
        }

        // New customers today
        $today = new \DateTime('today');
        $newCustomersTodayQb = $this->entityManager->getRepository(Customer::class)
            ->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.createdAt >= :today')
            ->setParameter('today', $today);
        
        try {
            $newCustomersToday = (int) $newCustomersTodayQb->getQuery()->getSingleScalarResult();
        } catch (\Doctrine\ORM\NoResultException | \Doctrine\ORM\NonUniqueResultException $e) {
            $newCustomersToday = 0;
        }

        return new CustomerStatsDto(
            (int) $totalCustomers,
            (int) $customersWithLeads,
            (int) $customersWithPreferences,
            (int) $newCustomersToday
        );
    }
    
    /**
     * Determine CDP delivery status for a lead
     * 
     * Status priority:
     * 1. 'failed' - if there are any non-resolved failed deliveries
     * 2. 'success' - if there are successful delivery events and no failures
     * 3. 'pending' - otherwise
     * 
     * @param Lead $lead
     * @return string
     */
    private function determineCdpDeliveryStatus(Lead $lead): string
    {
        // Check for failed deliveries (not resolved)
        $qb1 = $this->entityManager->createQueryBuilder();
        $hasFailedDelivery = $qb1
            ->select('COUNT(fd.id)')
            ->from(FailedDelivery::class, 'fd')
            ->where('fd.lead = :lead')
            ->andWhere('fd.status != :resolvedStatus')
            ->setParameter('lead', $lead)
            ->setParameter('resolvedStatus', 'resolved')
            ->getQuery()
            ->getSingleScalarResult();
        
        if ($hasFailedDelivery > 0) {
            return 'failed';
        }
        
        // Check for successful delivery events
        $qb2 = $this->entityManager->createQueryBuilder();
        $hasSuccessEvent = $qb2
            ->select('COUNT(e.id)')
            ->from(Event::class, 'e')
            ->where('e.entityType = :entityType')
            ->andWhere('e.entityId = :entityId')
            ->andWhere('e.eventType = :eventType')
            ->setParameter('entityType', 'lead')
            ->setParameter('entityId', $lead->getId())
            ->setParameter('eventType', 'cdp_delivery_success')
            ->getQuery()
            ->getSingleScalarResult();
        
        if ($hasSuccessEvent > 0) {
            return 'success';
        }
        
        return 'pending';
    }
}


