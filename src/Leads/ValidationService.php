<?php

declare(strict_types=1);

namespace App\Leads;

use App\DTO\CreateLeadRequest;

/**
 * Validation service for lead creation
 * Validates UUID, email, phone, application name, and string lengths
 */
class ValidationService implements ValidationServiceInterface
{
    private const ALLOWED_APPLICATIONS = ['morizon', 'gratka', 'homsters'];
    
    private const MAX_LENGTHS = [
        'email' => 255,
        'phone' => 20,
        'firstName' => 100,
        'lastName' => 100,
        'applicationName' => 50,
        'propertyId' => 100,
        'developmentId' => 100,
        'partnerId' => 100,
        'propertyType' => 50,
        'location' => 255,
        'city' => 100,
    ];

    /**
     * Validate create lead request
     *
     * @param CreateLeadRequest $request
     * @return array<string, string>
     */
    public function validateCreateLeadRequest(CreateLeadRequest $request): array
    {
        $errors = [];

        // Validate lead UUID
        if (!$this->isValidUuid($request->leadUuid)) {
            $errors['lead_uuid'] = 'Invalid UUID format. Expected UUID v4 format.';
        }

        // Validate application name
        if (!$this->isValidApplicationName($request->applicationName)) {
            $errors['application_name'] = sprintf(
                'Invalid application name. Allowed values: %s',
                implode(', ', self::ALLOWED_APPLICATIONS)
            );
        }

        // Validate application name length
        if (mb_strlen($request->applicationName) > self::MAX_LENGTHS['applicationName']) {
            $errors['application_name'] = sprintf(
                'Application name too long. Maximum %d characters.',
                self::MAX_LENGTHS['applicationName']
            );
        }

        // Validate customer email
        if (!$this->isValidEmail($request->customer->email)) {
            $errors['customer.email'] = 'Invalid email format.';
        }

        if (mb_strlen($request->customer->email) > self::MAX_LENGTHS['email']) {
            $errors['customer.email'] = sprintf(
                'Email too long. Maximum %d characters.',
                self::MAX_LENGTHS['email']
            );
        }

        // Validate customer phone
        if (!$this->isValidPhone($request->customer->phone)) {
            $errors['customer.phone'] = 'Invalid phone format.';
        }

        if (mb_strlen($request->customer->phone) > self::MAX_LENGTHS['phone']) {
            $errors['customer.phone'] = sprintf(
                'Phone too long. Maximum %d characters.',
                self::MAX_LENGTHS['phone']
            );
        }

        // Validate optional customer fields
        if ($request->customer->firstName !== null && mb_strlen($request->customer->firstName) > self::MAX_LENGTHS['firstName']) {
            $errors['customer.first_name'] = sprintf(
                'First name too long. Maximum %d characters.',
                self::MAX_LENGTHS['firstName']
            );
        }

        if ($request->customer->lastName !== null && mb_strlen($request->customer->lastName) > self::MAX_LENGTHS['lastName']) {
            $errors['customer.last_name'] = sprintf(
                'Last name too long. Maximum %d characters.',
                self::MAX_LENGTHS['lastName']
            );
        }

        // Validate optional property fields
        if ($request->property->propertyId !== null && mb_strlen($request->property->propertyId) > self::MAX_LENGTHS['propertyId']) {
            $errors['property.property_id'] = sprintf(
                'Property ID too long. Maximum %d characters.',
                self::MAX_LENGTHS['propertyId']
            );
        }

        if ($request->property->developmentId !== null && mb_strlen($request->property->developmentId) > self::MAX_LENGTHS['developmentId']) {
            $errors['property.development_id'] = sprintf(
                'Development ID too long. Maximum %d characters.',
                self::MAX_LENGTHS['developmentId']
            );
        }

        if ($request->property->partnerId !== null && mb_strlen($request->property->partnerId) > self::MAX_LENGTHS['partnerId']) {
            $errors['property.partner_id'] = sprintf(
                'Partner ID too long. Maximum %d characters.',
                self::MAX_LENGTHS['partnerId']
            );
        }

        if ($request->property->propertyType !== null && mb_strlen($request->property->propertyType) > self::MAX_LENGTHS['propertyType']) {
            $errors['property.property_type'] = sprintf(
                'Property type too long. Maximum %d characters.',
                self::MAX_LENGTHS['propertyType']
            );
        }

        if ($request->property->location !== null && mb_strlen($request->property->location) > self::MAX_LENGTHS['location']) {
            $errors['property.location'] = sprintf(
                'Location too long. Maximum %d characters.',
                self::MAX_LENGTHS['location']
            );
        }

        if ($request->property->city !== null && mb_strlen($request->property->city) > self::MAX_LENGTHS['city']) {
            $errors['property.city'] = sprintf(
                'City too long. Maximum %d characters.',
                self::MAX_LENGTHS['city']
            );
        }

        // Validate price is positive if provided
        if ($request->property->price !== null && $request->property->price <= 0) {
            $errors['property.price'] = 'Price must be a positive number.';
        }

        // Validate price doesn't exceed maximum (15 digits, 2 decimals = max 9999999999999.99)
        if ($request->property->price !== null && $request->property->price >= 10000000000000) {
            $errors['property.price'] = 'Price exceeds maximum allowed value.';
        }

        return $errors;
    }

    /**
     * Validate UUID v4 format
     *
     * @param string $uuid
     * @return bool
     */
    public function isValidUuid(string $uuid): bool
    {
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
        return preg_match($pattern, $uuid) === 1;
    }

    /**
     * Validate email format (RFC 5322)
     *
     * @param string $email
     * @return bool
     */
    public function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate phone format
     * Accepts various formats: +48123456789, 48123456789, 123456789, +48 123 456 789, etc.
     *
     * @param string $phone
     * @return bool
     */
    public function isValidPhone(string $phone): bool
    {
        // Remove spaces, dashes, and parentheses
        $cleaned = preg_replace('/[\s\-\(\)]/', '', $phone);
        
        // Must start with optional + and contain only digits after that
        // Minimum 9 digits, maximum 15 digits (E.164 standard)
        $pattern = '/^\+?[0-9]{9,15}$/';
        
        return preg_match($pattern, $cleaned) === 1;
    }

    /**
     * Validate application name against allowed list
     *
     * @param string $applicationName
     * @return bool
     */
    public function isValidApplicationName(string $applicationName): bool
    {
        return in_array(strtolower($applicationName), self::ALLOWED_APPLICATIONS, true);
    }
}


