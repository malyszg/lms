<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Exception thrown when attempting to access a lead that doesn't exist
 * Results in HTTP 404 Not Found response
 */
class LeadNotFoundException extends \RuntimeException
{
    public function __construct(string $leadUuid)
    {
        parent::__construct(
            sprintf('Lead with UUID %s not found', $leadUuid)
        );
    }
}
