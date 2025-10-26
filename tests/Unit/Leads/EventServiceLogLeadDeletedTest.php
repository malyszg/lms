<?php

declare(strict_types=1);

namespace App\Tests\Unit\Leads;

use App\Leads\EventService;
use App\Model\Event;
use App\Model\Lead;
use App\Model\Customer;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test for EventService::logLeadDeleted method
 */
class EventServiceLogLeadDeletedTest extends TestCase
{
    private EventService $eventService;
    private EntityManagerInterface&MockObject $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->eventService = new EventService($this->entityManager);
    }

    public function testLogLeadDeleted(): void
    {
        // Create test data
        $customer = $this->createMock(Customer::class);
        $customer->method('getId')->willReturn(123);

        $lead = $this->createMock(Lead::class);
        $lead->method('getId')->willReturn(456);
        $lead->method('getLeadUuid')->willReturn('550e8400-e29b-41d4-a716-446655440000');
        $lead->method('getCustomer')->willReturn($customer);
        $lead->method('getApplicationName')->willReturn('morizon');
        $lead->method('getStatus')->willReturn('new');

        $ipAddress = '192.168.1.1';
        $userAgent = 'Mozilla/5.0 (Test Browser)';

        // Mock entity manager behavior
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Event::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        // Execute the method
        /** @var Lead $lead */
        $event = $this->eventService->logLeadDeleted($lead, $ipAddress, $userAgent);

        // Assertions
        $this->assertInstanceOf(Event::class, $event);
        $this->assertSame('lead_deleted', $event->getEventType());
        $this->assertSame('lead', $event->getEntityType());
        $this->assertSame(456, $event->getEntityId());
        $this->assertSame($ipAddress, $event->getIpAddress());
        $this->assertSame($userAgent, $event->getUserAgent());

        $details = $event->getDetails();
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $details['lead_uuid']);
        $this->assertSame(123, $details['customer_id']);
        $this->assertSame('morizon', $details['application_name']);
        $this->assertSame('new', $details['status']);
    }

    public function testLogLeadDeletedWithoutIpAndUserAgent(): void
    {
        // Create test data
        $customer = $this->createMock(Customer::class);
        $customer->method('getId')->willReturn(123);

        $lead = $this->createMock(Lead::class);
        $lead->method('getId')->willReturn(456);
        $lead->method('getLeadUuid')->willReturn('550e8400-e29b-41d4-a716-446655440000');
        $lead->method('getCustomer')->willReturn($customer);
        $lead->method('getApplicationName')->willReturn('gratka');
        $lead->method('getStatus')->willReturn('contacted');

        // Mock entity manager behavior
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Event::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        // Execute the method without IP and User Agent
        /** @var Lead $lead */
        $event = $this->eventService->logLeadDeleted($lead);

        // Assertions
        $this->assertInstanceOf(Event::class, $event);
        $this->assertSame('lead_deleted', $event->getEventType());
        $this->assertSame('lead', $event->getEntityType());
        $this->assertSame(456, $event->getEntityId());
        $this->assertNull($event->getIpAddress());
        $this->assertNull($event->getUserAgent());

        $details = $event->getDetails();
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $details['lead_uuid']);
        $this->assertSame(123, $details['customer_id']);
        $this->assertSame('gratka', $details['application_name']);
        $this->assertSame('contacted', $details['status']);
    }

    public function testLogLeadDeletedWithDifferentStatus(): void
    {
        // Create test data
        $customer = $this->createMock(Customer::class);
        $customer->method('getId')->willReturn(789);

        $lead = $this->createMock(Lead::class);
        $lead->method('getId')->willReturn(101);
        $lead->method('getLeadUuid')->willReturn('123e4567-e89b-12d3-a456-426614174000');
        $lead->method('getCustomer')->willReturn($customer);
        $lead->method('getApplicationName')->willReturn('homsters');
        $lead->method('getStatus')->willReturn('qualified');

        // Mock entity manager behavior
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Event::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        // Execute the method
        /** @var Lead $lead */
        $event = $this->eventService->logLeadDeleted($lead, '10.0.0.1', 'Test Agent');

        // Assertions
        $this->assertInstanceOf(Event::class, $event);
        $this->assertSame('lead_deleted', $event->getEventType());
        $this->assertSame('lead', $event->getEntityType());
        $this->assertSame(101, $event->getEntityId());
        $this->assertSame('10.0.0.1', $event->getIpAddress());
        $this->assertSame('Test Agent', $event->getUserAgent());

        $details = $event->getDetails();
        $this->assertSame('123e4567-e89b-12d3-a456-426614174000', $details['lead_uuid']);
        $this->assertSame(789, $details['customer_id']);
        $this->assertSame('homsters', $details['application_name']);
        $this->assertSame('qualified', $details['status']);
    }
}
