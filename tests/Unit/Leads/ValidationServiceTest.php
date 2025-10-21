<?php

declare(strict_types=1);

namespace App\Tests\Unit\Leads;

use App\DTO\CreateCustomerDto;
use App\DTO\CreateLeadRequest;
use App\DTO\PropertyDto;
use App\Leads\ValidationService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ValidationService
 */
class ValidationServiceTest extends TestCase
{
    private ValidationService $validationService;

    protected function setUp(): void
    {
        $this->validationService = new ValidationService();
    }

    public function testIsValidUuidReturnsTrueForValidUuidV4(): void
    {
        $validUuid = '550e8400-e29b-41d4-a716-446655440000';
        
        $this->assertTrue($this->validationService->isValidUuid($validUuid));
    }

    public function testIsValidUuidReturnsFalseForInvalidUuid(): void
    {
        $invalidUuids = [
            'invalid-uuid',
            '12345',
            '',
            'not-a-uuid-at-all',
            '550e8400-e29b-41d4-a716-44665544000', // too short
        ];

        foreach ($invalidUuids as $invalidUuid) {
            $this->assertFalse(
                $this->validationService->isValidUuid($invalidUuid),
                "UUID '$invalidUuid' should be invalid"
            );
        }
    }

    public function testIsValidEmailReturnsTrueForValidEmail(): void
    {
        $validEmails = [
            'test@example.com',
            'user+tag@domain.co.uk',
            'first.last@company.com',
        ];

        foreach ($validEmails as $email) {
            $this->assertTrue(
                $this->validationService->isValidEmail($email),
                "Email '$email' should be valid"
            );
        }
    }

    public function testIsValidEmailReturnsFalseForInvalidEmail(): void
    {
        $invalidEmails = [
            'invalid',
            'no-at-sign',
            '@no-local-part.com',
            'no-domain@',
            '',
        ];

        foreach ($invalidEmails as $email) {
            $this->assertFalse(
                $this->validationService->isValidEmail($email),
                "Email '$email' should be invalid"
            );
        }
    }

    public function testIsValidPhoneReturnsTrueForValidPhone(): void
    {
        $validPhones = [
            '+48123456789',
            '48123456789',
            '+1234567890',
            '+12 345 678 901',
            '123-456-789',
        ];

        foreach ($validPhones as $phone) {
            $this->assertTrue(
                $this->validationService->isValidPhone($phone),
                "Phone '$phone' should be valid"
            );
        }
    }

    public function testIsValidPhoneReturnsFalseForInvalidPhone(): void
    {
        $invalidPhones = [
            '123', // too short
            'not-a-phone',
            '',
            '12', // too short
            '+' . str_repeat('1', 20), // too long
        ];

        foreach ($invalidPhones as $phone) {
            $this->assertFalse(
                $this->validationService->isValidPhone($phone),
                "Phone '$phone' should be invalid"
            );
        }
    }

    public function testIsValidApplicationNameReturnsTrueForValidNames(): void
    {
        $validNames = ['morizon', 'gratka', 'homsters', 'Morizon', 'GRATKA'];

        foreach ($validNames as $name) {
            $this->assertTrue(
                $this->validationService->isValidApplicationName($name),
                "Application name '$name' should be valid"
            );
        }
    }

    public function testIsValidApplicationNameReturnsFalseForInvalidNames(): void
    {
        $invalidNames = ['invalid', 'unknown', '', 'other-app'];

        foreach ($invalidNames as $name) {
            $this->assertFalse(
                $this->validationService->isValidApplicationName($name),
                "Application name '$name' should be invalid"
            );
        }
    }

    public function testValidateCreateLeadRequestReturnsEmptyArrayForValidRequest(): void
    {
        $request = new CreateLeadRequest(
            leadUuid: '550e8400-e29b-41d4-a716-446655440000',
            applicationName: 'morizon',
            customer: new CreateCustomerDto(
                email: 'test@example.com',
                phone: '+48123456789',
                firstName: 'John',
                lastName: 'Doe'
            ),
            property: new PropertyDto(
                propertyId: 'PROP-001',
                developmentId: null,
                partnerId: null,
                propertyType: null,
                price: 450000.00,
                location: null,
                city: 'Warszawa'
            )
        );

        $errors = $this->validationService->validateCreateLeadRequest($request);

        $this->assertEmpty($errors, 'Valid request should have no validation errors');
    }

    public function testValidateCreateLeadRequestReturnsErrorsForInvalidData(): void
    {
        $request = new CreateLeadRequest(
            leadUuid: 'invalid-uuid',
            applicationName: 'unknown-app',
            customer: new CreateCustomerDto(
                email: 'invalid-email',
                phone: '123',
                firstName: null,
                lastName: null
            ),
            property: new PropertyDto(
                propertyId: null,
                developmentId: null,
                partnerId: null,
                propertyType: null,
                price: null,
                location: null,
                city: null
            )
        );

        $errors = $this->validationService->validateCreateLeadRequest($request);

        $this->assertNotEmpty($errors);
        $this->assertArrayHasKey('lead_uuid', $errors);
        $this->assertArrayHasKey('application_name', $errors);
        $this->assertArrayHasKey('customer.email', $errors);
        $this->assertArrayHasKey('customer.phone', $errors);
    }

    public function testValidateCreateLeadRequestReturnsErrorForNegativePrice(): void
    {
        $request = new CreateLeadRequest(
            leadUuid: '550e8400-e29b-41d4-a716-446655440000',
            applicationName: 'morizon',
            customer: new CreateCustomerDto(
                email: 'test@example.com',
                phone: '+48123456789'
            ),
            property: new PropertyDto(
                propertyId: null,
                developmentId: null,
                partnerId: null,
                propertyType: null,
                price: -100.00, // negative price
                location: null,
                city: null
            )
        );

        $errors = $this->validationService->validateCreateLeadRequest($request);

        $this->assertArrayHasKey('property.price', $errors);
        $this->assertStringContainsString('positive', $errors['property.price']);
    }

    public function testValidateCreateLeadRequestReturnsErrorForTooLongFields(): void
    {
        $request = new CreateLeadRequest(
            leadUuid: '550e8400-e29b-41d4-a716-446655440000',
            applicationName: 'morizon',
            customer: new CreateCustomerDto(
                email: str_repeat('a', 250) . '@example.com', // > 255 chars
                phone: '+48123456789',
                firstName: str_repeat('A', 101), // > 100 chars
                lastName: null
            ),
            property: new PropertyDto(
                propertyId: null,
                developmentId: null,
                partnerId: null,
                propertyType: null,
                price: null,
                location: null,
                city: null
            )
        );

        $errors = $this->validationService->validateCreateLeadRequest($request);

        $this->assertArrayHasKey('customer.email', $errors);
        $this->assertArrayHasKey('customer.first_name', $errors);
    }
}































