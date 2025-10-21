<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Exception thrown when attempting to create a lead with duplicate UUID
 * Results in HTTP 409 Conflict response
 */
class LeadAlreadyExistsException extends \RuntimeException
{
    public function __construct(
        string $leadUuid,
        private readonly ?int $existingLeadId = null
    ) {
        parent::__construct(
            sprintf('Lead with UUID %s already exists', $leadUuid)
        );
    }

    public function getExistingLeadId(): ?int
    {
        return $this->existingLeadId;
    }
}































