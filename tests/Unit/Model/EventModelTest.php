<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model;

use App\Model\Event;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Event model
 */
class EventModelTest extends TestCase
{
    public function testEventConstructor(): void
    {
        $event = new Event('lead_created');
        
        $this->assertEquals('lead_created', $event->getEventType());
    }

    public function testEventWithDifferentEventTypes(): void
    {
        $eventTypes = [
            'lead_created',
            'lead_updated',
            'lead_deleted',
            'lead_status_changed',
            'customer_preferences_changed',
            'lead_scored',
            'lead_contacted'
        ];
        
        foreach ($eventTypes as $eventType) {
            $event = new Event($eventType);
            $this->assertEquals($eventType, $event->getEventType());
        }
    }

    public function testEventEntityTypeUpdate(): void
    {
        $event = new Event('lead_created');
        
        $this->assertNull($event->getEntityType());
        
        $event->setEntityType('lead');
        $this->assertEquals('lead', $event->getEntityType());
        
        $event->setEntityType('customer');
        $this->assertEquals('customer', $event->getEntityType());
    }

    public function testEventEntityIdUpdate(): void
    {
        $event = new Event('lead_created');
        
        $this->assertNull($event->getEntityId());
        
        $event->setEntityId(1);
        $this->assertEquals(1, $event->getEntityId());
        
        $event->setEntityId(999);
        $this->assertEquals(999, $event->getEntityId());
    }

    public function testEventDetailsUpdate(): void
    {
        $event = new Event('lead_created');
        
        $this->assertNull($event->getDetails());
        
        $details = ['lead_uuid' => '550e8400-e29b-41d4-a716-446655440000'];
        $event->setDetails($details);
        $this->assertEquals($details, $event->getDetails());
        
        $complexDetails = [
            'lead_uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'customer_id' => 1,
            'application_name' => 'Test App',
            'status' => 'new'
        ];
        $event->setDetails($complexDetails);
        $this->assertEquals($complexDetails, $event->getDetails());
    }

    public function testEventIpAddressAndUserAgent(): void
    {
        $event = new Event('lead_created');
        
        $this->assertNull($event->getIpAddress());
        $this->assertNull($event->getUserAgent());
        
        $event->setIpAddress('192.168.1.1');
        $event->setUserAgent('Mozilla/5.0 (Test Browser)');
        
        $this->assertEquals('192.168.1.1', $event->getIpAddress());
        $this->assertEquals('Mozilla/5.0 (Test Browser)', $event->getUserAgent());
    }

    public function testEventRetryCount(): void
    {
        $event = new Event('lead_created');
        
        $this->assertEquals(0, $event->getRetryCount());
        
        $event->setRetryCount(1);
        $this->assertEquals(1, $event->getRetryCount());
        
        $event->setRetryCount(5);
        $this->assertEquals(5, $event->getRetryCount());
    }

    public function testEventErrorMessage(): void
    {
        $event = new Event('lead_created');
        
        $this->assertNull($event->getErrorMessage());
        
        $event->setErrorMessage('Test error message');
        $this->assertEquals('Test error message', $event->getErrorMessage());
        
        $event->setErrorMessage('Updated error message');
        $this->assertEquals('Updated error message', $event->getErrorMessage());
    }
}
