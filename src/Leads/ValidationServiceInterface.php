<?php

declare(strict_types=1);

namespace App\Leads;

use App\DTO\CreateLeadRequest;

/**
 * Validation service interface for lead creation
 */
interface ValidationServiceInterface
{
    /**
     * Validate create lead request
     * Returns array of validation errors (empty array if valid)
     *
     * @param CreateLeadRequest $request
     * @return array<string, string> Array of field => error message
     */
    public function validateCreateLeadRequest(CreateLeadRequest $request): array;

    /**
     * Validate UUID format
     *
     * @param string $uuid
     * @return bool
     */
    public function isValidUuid(string $uuid): bool;

    /**
     * Validate email format
     *
     * @param string $email
     * @return bool
     */
    public function isValidEmail(string $email): bool;

    /**
     * Validate phone format
     *
     * @param string $phone
     * @return bool
     */
    public function isValidPhone(string $phone): bool;

    /**
     * Validate application name
     *
     * @param string $applicationName
     * @return bool
     */
    public function isValidApplicationName(string $applicationName): bool;
}


