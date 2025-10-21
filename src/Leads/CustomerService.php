<?php

declare(strict_types=1);

namespace App\Leads;

use App\DTO\CreateCustomerDto;
use App\Model\Customer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;

/**
 * Customer service implementation
 * Handles customer creation with deduplication based on email+phone
 */
class CustomerService implements CustomerServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
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
}


