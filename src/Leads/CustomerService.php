<?php

declare(strict_types=1);

namespace App\Leads;

use App\DTO\CreateCustomerDto;
use App\DTO\UpdatePreferencesRequest;
use App\Exception\CustomerNotFoundException;
use App\Exception\ValidationException;
use App\Model\Customer;
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
            return $repository->createQueryBuilder('c')
                ->where('c.email = :email')
                ->andWhere('c.phone = :phone')
                ->setParameter('email', $email)
                ->setParameter('phone', $phone)
                ->setMaxResults(1)
                ->getQuery()
                ->setLockMode(LockMode::PESSIMISTIC_WRITE)
                ->getOneOrNullResult();
        } catch (\Doctrine\ORM\NoResultException $e) {
            // No result is expected behavior, return null
            return null;
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
            $sql = 'SELECT price_min, price_max, location, city FROM customer_preferences WHERE customer_id = :customer_id';
            $result = $this->entityManager->getConnection()->executeQuery($sql, ['customer_id' => $customerId]);
            
            $data = $result->fetchAssociative();
            return $data !== false ? $data : null;
        } catch (\Exception $e) {
            if ($this->logger !== null) {
                $this->logger->error('Failed to get current preferences', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
            throw $e;
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
}


