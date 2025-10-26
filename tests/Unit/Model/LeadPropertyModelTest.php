<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model;

use App\Model\LeadProperty;
use App\Model\Lead;
use App\Model\Customer;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for LeadProperty model
 */
class LeadPropertyModelTest extends TestCase
{
    public function testLeadPropertyConstructor(): void
    {
        $customer = new Customer(
            email: 'test@example.com',
            phone: '+48123456789',
            firstName: 'Test',
            lastName: 'User'
        );
        
        $lead = new Lead(
            leadUuid: '550e8400-e29b-41d4-a716-446655440000',
            customer: $customer,
            applicationName: 'Test App'
        );
        
        $property = new LeadProperty($lead);
        
        $this->assertSame($lead, $property->getLead());
    }

    public function testLeadPropertyPropertyIdUpdate(): void
    {
        $customer = new Customer(
            email: 'test@example.com',
            phone: '+48123456789',
            firstName: 'Test',
            lastName: 'User'
        );
        
        $lead = new Lead(
            leadUuid: '550e8400-e29b-41d4-a716-446655440000',
            customer: $customer,
            applicationName: 'Test App'
        );
        
        $property = new LeadProperty($lead);
        
        $this->assertNull($property->getPropertyId());
        
        $property->setPropertyId('prop-123');
        $this->assertEquals('prop-123', $property->getPropertyId());
        
        $property->setPropertyId('prop-456');
        $this->assertEquals('prop-456', $property->getPropertyId());
    }

    public function testLeadPropertyPriceUpdate(): void
    {
        $customer = new Customer(
            email: 'test@example.com',
            phone: '+48123456789',
            firstName: 'Test',
            lastName: 'User'
        );
        
        $lead = new Lead(
            leadUuid: '550e8400-e29b-41d4-a716-446655440000',
            customer: $customer,
            applicationName: 'Test App'
        );
        
        $property = new LeadProperty($lead);
        
        $this->assertNull($property->getPrice());
        
        $property->setPrice(500000.0);
        $this->assertEquals(500000.0, $property->getPrice());
        
        $property->setPrice(600000.0);
        $this->assertEquals(600000.0, $property->getPrice());
        
        $property->setPrice(0.0);
        $this->assertEquals(0.0, $property->getPrice());
    }

    public function testLeadPropertyLocationUpdate(): void
    {
        $customer = new Customer(
            email: 'test@example.com',
            phone: '+48123456789',
            firstName: 'Test',
            lastName: 'User'
        );
        
        $lead = new Lead(
            leadUuid: '550e8400-e29b-41d4-a716-446655440000',
            customer: $customer,
            applicationName: 'Test App'
        );
        
        $property = new LeadProperty($lead);
        
        $this->assertNull($property->getLocation());
        $this->assertNull($property->getCity());
        
        $property->setLocation('Centrum miasta');
        $property->setCity('Warszawa');
        
        $this->assertEquals('Centrum miasta', $property->getLocation());
        $this->assertEquals('Warszawa', $property->getCity());
    }

    public function testLeadPropertyPropertyTypeUpdate(): void
    {
        $customer = new Customer(
            email: 'test@example.com',
            phone: '+48123456789',
            firstName: 'Test',
            lastName: 'User'
        );
        
        $lead = new Lead(
            leadUuid: '550e8400-e29b-41d4-a716-446655440000',
            customer: $customer,
            applicationName: 'Test App'
        );
        
        $property = new LeadProperty($lead);
        
        $this->assertNull($property->getPropertyType());
        
        $propertyTypes = ['apartment', 'house', 'commercial', 'land', 'office'];
        
        foreach ($propertyTypes as $type) {
            $property->setPropertyType($type);
            $this->assertEquals($type, $property->getPropertyType());
        }
    }

    public function testLeadPropertyDevelopmentAndPartnerId(): void
    {
        $customer = new Customer(
            email: 'test@example.com',
            phone: '+48123456789',
            firstName: 'Test',
            lastName: 'User'
        );
        
        $lead = new Lead(
            leadUuid: '550e8400-e29b-41d4-a716-446655440000',
            customer: $customer,
            applicationName: 'Test App'
        );
        
        $property = new LeadProperty($lead);
        
        $this->assertNull($property->getDevelopmentId());
        $this->assertNull($property->getPartnerId());
        
        $property->setDevelopmentId('dev-123');
        $property->setPartnerId('partner-456');
        
        $this->assertEquals('dev-123', $property->getDevelopmentId());
        $this->assertEquals('partner-456', $property->getPartnerId());
    }

    public function testLeadPropertyWithNullValues(): void
    {
        $customer = new Customer(
            email: 'test@example.com',
            phone: '+48123456789',
            firstName: 'Test',
            lastName: 'User'
        );
        
        $lead = new Lead(
            leadUuid: '550e8400-e29b-41d4-a716-446655440000',
            customer: $customer,
            applicationName: 'Test App'
        );
        
        $property = new LeadProperty($lead);
        
        // Set values first
        $property->setPropertyId('prop-123');
        $property->setPrice(500000.0);
        $property->setLocation('Test Location');
        $property->setCity('Test City');
        
        // Then set to null
        $property->setPropertyId(null);
        $property->setPrice(null);
        $property->setLocation(null);
        $property->setCity(null);
        
        $this->assertNull($property->getPropertyId());
        $this->assertNull($property->getPrice());
        $this->assertNull($property->getLocation());
        $this->assertNull($property->getCity());
    }
}
