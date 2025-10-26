<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model;

use App\Model\FailedDelivery;
use App\Model\Lead;
use App\Model\Customer;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for FailedDelivery model
 */
class FailedDeliveryModelTest extends TestCase
{
    public function testFailedDeliveryConstructor(): void
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
        
        $failedDelivery = new FailedDelivery($lead, 'test-cdp-system');
        
        $this->assertSame($lead, $failedDelivery->getLead());
        $this->assertEquals('test-cdp-system', $failedDelivery->getCdpSystemName());
    }

    public function testFailedDeliveryWithDifferentCdpSystems(): void
    {
        $cdpSystems = ['salesforce', 'hubspot', 'pipedrive', 'zoho', 'monday'];
        
        foreach ($cdpSystems as $system) {
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
            
            $failedDelivery = new FailedDelivery($lead, $system);
            
            $this->assertEquals($system, $failedDelivery->getCdpSystemName());
        }
    }

    public function testFailedDeliveryRetryCount(): void
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
        
        $failedDelivery = new FailedDelivery($lead, 'test-cdp-system');
        
        $this->assertEquals(0, $failedDelivery->getRetryCount());
        
        $failedDelivery->setRetryCount(1);
        $this->assertEquals(1, $failedDelivery->getRetryCount());
        
        $failedDelivery->setRetryCount(5);
        $this->assertEquals(5, $failedDelivery->getRetryCount());
    }

    public function testFailedDeliveryErrorMessageUpdate(): void
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
        
        $failedDelivery = new FailedDelivery($lead, 'test-cdp-system');
        
        $this->assertNull($failedDelivery->getErrorMessage());
        
        $failedDelivery->setErrorMessage('SMTP connection failed');
        $this->assertEquals('SMTP connection failed', $failedDelivery->getErrorMessage());
        
        $failedDelivery->setErrorMessage('Updated error message');
        $this->assertEquals('Updated error message', $failedDelivery->getErrorMessage());
    }

    public function testFailedDeliveryErrorCodeUpdate(): void
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
        
        $failedDelivery = new FailedDelivery($lead, 'test-cdp-system');
        
        $this->assertNull($failedDelivery->getErrorCode());
        
        $failedDelivery->setErrorCode('SMTP_001');
        $this->assertEquals('SMTP_001', $failedDelivery->getErrorCode());
        
        $failedDelivery->setErrorCode('API_404');
        $this->assertEquals('API_404', $failedDelivery->getErrorCode());
    }

    public function testFailedDeliveryStatusUpdate(): void
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
        
        $failedDelivery = new FailedDelivery($lead, 'test-cdp-system');
        
        $this->assertEquals('pending', $failedDelivery->getStatus());
        
        $failedDelivery->setStatus('retrying');
        $this->assertEquals('retrying', $failedDelivery->getStatus());
        
        $failedDelivery->setStatus('resolved');
        $this->assertEquals('resolved', $failedDelivery->getStatus());
    }

    public function testFailedDeliveryMaxRetries(): void
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
        
        $failedDelivery = new FailedDelivery($lead, 'test-cdp-system');
        
        $this->assertEquals(3, $failedDelivery->getMaxRetries());
        
        $failedDelivery->setMaxRetries(5);
        $this->assertEquals(5, $failedDelivery->getMaxRetries());
        
        $failedDelivery->setMaxRetries(1);
        $this->assertEquals(1, $failedDelivery->getMaxRetries());
    }

    public function testFailedDeliveryWithLongErrorMessage(): void
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
        
        $failedDelivery = new FailedDelivery($lead, 'test-cdp-system');
        
        $longErrorMessage = 'This is a very long error message that contains detailed information about what went wrong during the delivery process. It includes technical details, error codes, timestamps, and other relevant information that might be useful for debugging purposes.';
        
        $failedDelivery->setErrorMessage($longErrorMessage);
        $this->assertEquals($longErrorMessage, $failedDelivery->getErrorMessage());
    }
}
