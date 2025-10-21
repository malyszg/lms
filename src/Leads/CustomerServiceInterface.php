<?php

declare(strict_types=1);

namespace App\Leads;

use App\DTO\CreateCustomerDto;
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
}


