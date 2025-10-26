<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Exception thrown when customer is not found
 */
class CustomerNotFoundException extends \Exception
{
    public function __construct(int $customerId)
    {
        parent::__construct(sprintf('Customer with ID %d not found', $customerId));
    }
}
