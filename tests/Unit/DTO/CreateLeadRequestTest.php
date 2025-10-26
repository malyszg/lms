<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\CreateLeadRequest;
use App\DTO\CreateCustomerDto;
use App\DTO\PropertyDto;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CreateLeadRequest
 */
class CreateLeadRequestTest extends TestCase
{
    public function testCreateLeadRequestConstructor(): void
    {
        $customerDto = new CreateCustomerDto(
            email: 'test@example.com',
            phone: '+48123456789',
            firstName: 'Test',
            lastName: 'User'
        );
        
        $propertyDto = new PropertyDto(
            propertyId: 'prop-123',
            developmentId: 'dev-456',
            partnerId: 'partner-789',
            propertyType: 'apartment',
            price: 500000.0,
            location: 'Centrum miasta',
            city: 'Warszawa'
        );
        
        $request = new CreateLeadRequest(
            leadUuid: '550e8400-e29b-41d4-a716-446655440000',
            applicationName: 'Test App',
            customer: $customerDto,
            property: $propertyDto
        );
        
        $this->assertEquals('550e8400-e29b-41d4-a716-446655440000', $request->leadUuid);
        $this->assertEquals('Test App', $request->applicationName);
        $this->assertInstanceOf(CreateCustomerDto::class, $request->customer);
        $this->assertInstanceOf(PropertyDto::class, $request->property);
    }

    public function testCreateLeadRequestWithMinimalData(): void
    {
        $customerDto = new CreateCustomerDto(
            email: 'minimal@example.com',
            phone: '+48111111111',
            firstName: '',
            lastName: ''
        );
        
        $propertyDto = new PropertyDto(
            propertyId: null,
            developmentId: null,
            partnerId: null,
            propertyType: null,
            price: null,
            location: null,
            city: null
        );
        
        $request = new CreateLeadRequest(
            leadUuid: '550e8400-e29b-41d4-a716-446655440000',
            applicationName: 'Minimal App',
            customer: $customerDto,
            property: $propertyDto
        );
        
        $this->assertEquals('550e8400-e29b-41d4-a716-446655440000', $request->leadUuid);
        $this->assertEquals('Minimal App', $request->applicationName);
        $this->assertEquals('minimal@example.com', $request->customer->email);
        $this->assertNull($request->property->price);
    }

    public function testCreateLeadRequestWithDifferentApplicationNames(): void
    {
        $applicationNames = [
            'Website Form',
            'Mobile App',
            'API Integration',
            'Partner Portal',
            'Call Center'
        ];
        
        foreach ($applicationNames as $appName) {
            $customerDto = new CreateCustomerDto(
                email: 'test@example.com',
                phone: '+48123456789',
                firstName: 'Test',
                lastName: 'User'
            );
            
            $propertyDto = new PropertyDto(
                propertyId: 'prop-123',
                developmentId: 'dev-456',
                partnerId: 'partner-789',
                propertyType: 'apartment',
                price: 500000.0,
                location: 'Test Location',
                city: 'Test City'
            );
            
            $request = new CreateLeadRequest(
                leadUuid: '550e8400-e29b-41d4-a716-446655440000',
                applicationName: $appName,
                customer: $customerDto,
                property: $propertyDto
            );
            
            $this->assertEquals($appName, $request->applicationName);
        }
    }

    public function testCreateLeadRequestWithDifferentUuids(): void
    {
        $uuids = [
            '550e8400-e29b-41d4-a716-446655440000',
            '6ba7b810-9dad-11d1-80b4-00c04fd430c8',
            '6ba7b811-9dad-11d1-80b4-00c04fd430c8',
            '6ba7b812-9dad-11d1-80b4-00c04fd430c8',
            '6ba7b813-9dad-11d1-80b4-00c04fd430c8'
        ];
        
        foreach ($uuids as $uuid) {
            $customerDto = new CreateCustomerDto(
                email: 'test@example.com',
                phone: '+48123456789',
                firstName: 'Test',
                lastName: 'User'
            );
            
            $propertyDto = new PropertyDto(
                propertyId: 'prop-123',
                developmentId: 'dev-456',
                partnerId: 'partner-789',
                propertyType: 'apartment',
                price: 500000.0,
                location: 'Test Location',
                city: 'Test City'
            );
            
            $request = new CreateLeadRequest(
                leadUuid: $uuid,
                applicationName: 'Test App',
                customer: $customerDto,
                property: $propertyDto
            );
            
            $this->assertEquals($uuid, $request->leadUuid);
        }
    }

    public function testCreateLeadRequestWithComplexData(): void
    {
        $customerDto = new CreateCustomerDto(
            email: 'complex+tag@verylongdomainname.com',
            phone: '+48 123 456 789',
            firstName: 'José María',
            lastName: 'García-López'
        );
        
        $propertyDto = new PropertyDto(
            propertyId: 'very-long-property-id-12345',
            developmentId: 'very-long-development-id-67890',
            partnerId: 'very-long-partner-id-abcdef',
            propertyType: 'commercial',
            price: 2500000.50,
            location: 'Very Long Location Name That Should Still Work',
            city: 'Very Long City Name That Should Still Work'
        );
        
        $request = new CreateLeadRequest(
            leadUuid: '550e8400-e29b-41d4-a716-446655440000',
            applicationName: 'Very Long Application Name That Should Still Work',
            customer: $customerDto,
            property: $propertyDto
        );
        
        $this->assertEquals('550e8400-e29b-41d4-a716-446655440000', $request->leadUuid);
        $this->assertEquals('Very Long Application Name That Should Still Work', $request->applicationName);
        $this->assertEquals('complex+tag@verylongdomainname.com', $request->customer->email);
        $this->assertEquals('José María', $request->customer->firstName);
        $this->assertEquals('García-López', $request->customer->lastName);
        $this->assertEquals(2500000.50, $request->property->price);
        $this->assertEquals('commercial', $request->property->propertyType);
    }
}
