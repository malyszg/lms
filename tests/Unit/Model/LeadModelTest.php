<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model;

use App\Model\Customer;
use App\Model\Lead;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Lead model
 */
class LeadModelTest extends TestCase
{
    public function testLeadConstructor(): void
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
        
        $this->assertEquals('550e8400-e29b-41d4-a716-446655440000', $lead->getLeadUuid());
        $this->assertSame($customer, $lead->getCustomer());
        $this->assertEquals('Test App', $lead->getApplicationName());
        $this->assertEquals('new', $lead->getStatus());
    }

    public function testLeadStatusUpdate(): void
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
        
        $this->assertEquals('new', $lead->getStatus());
        
        $lead->setStatus('contacted');
        $this->assertEquals('contacted', $lead->getStatus());
        
        $lead->setStatus('qualified');
        $this->assertEquals('qualified', $lead->getStatus());
    }

    public function testLeadWithDifferentStatuses(): void
    {
        $statuses = ['new', 'contacted', 'qualified', 'converted', 'rejected'];
        
        foreach ($statuses as $status) {
            $customer = new Customer(
                email: "test-{$status}@example.com",
                phone: '+48123456789',
                firstName: 'Test',
                lastName: 'User'
            );
            
            $lead = new Lead(
                leadUuid: '550e8400-e29b-41d4-a716-446655440000',
                customer: $customer,
                applicationName: 'Test App'
            );
            
            $lead->setStatus($status);
            $this->assertEquals($status, $lead->getStatus());
        }
    }

    public function testLeadUuidGeneration(): void
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
        
        $uuid = $lead->getLeadUuid();
        $this->assertIsString($uuid);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid);
    }

    public function testLeadApplicationNameUpdate(): void
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
            applicationName: 'Original App'
        );
        
        $this->assertEquals('Original App', $lead->getApplicationName());
        
        $lead->setApplicationName('Updated App');
        $this->assertEquals('Updated App', $lead->getApplicationName());
    }
}
