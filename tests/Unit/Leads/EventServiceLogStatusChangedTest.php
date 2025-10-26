<?php

declare(strict_types=1);

namespace App\Tests\Unit\Leads;

use App\Leads\EventService;
use App\Model\Event;
use App\Model\Lead;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for EventService::logLeadStatusChanged method
 */
class EventServiceLogStatusChangedTest extends TestCase
{
    private EventService $eventService;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->eventService = new EventService($this->entityManager);
    }

    public function testLogLeadStatusChangedSuccess(): void
    {
        // Arrange
        $lead = $this->createMock(Lead::class);
        $customer = $this->createMock(\App\Model\Customer::class);
        
        $lead->method('getId')->willReturn(123);
        $lead->method('getLeadUuid')->willReturn('123e4567-e89b-12d3-a456-426614174000');
        $lead->method('getCustomer')->willReturn($customer);
        $lead->method('getApplicationName')->willReturn('test-app');
        
        $customer->method('getId')->willReturn(456);

        $oldStatus = 'new';
        $newStatus = 'contacted';
        $userId = 789;
        $ipAddress = '192.168.1.1';
        $userAgent = 'Mozilla/5.0';

        // Expect entity manager calls
        $this->entityManager->expects($this->once())->method('persist')->with($this->isInstanceOf(Event::class));
        $this->entityManager->expects($this->once())->method('flush');

        // Act
        $result = $this->eventService->logLeadStatusChanged(
            $lead,
            $oldStatus,
            $newStatus,
            $userId,
            $ipAddress,
            $userAgent
        );

        // Assert
        $this->assertInstanceOf(Event::class, $result);
        $this->assertEquals('lead_status_changed', $result->getEventType());
        $this->assertEquals('lead', $result->getEntityType());
        $this->assertEquals(123, $result->getEntityId());
        $this->assertEquals($userId, $result->getUserId());
        $this->assertEquals($ipAddress, $result->getIpAddress());
        $this->assertEquals($userAgent, $result->getUserAgent());

        $details = $result->getDetails();
        $this->assertIsArray($details);
        $this->assertEquals('123e4567-e89b-12d3-a456-426614174000', $details['lead_uuid']);
        $this->assertEquals(456, $details['customer_id']);
        $this->assertEquals('test-app', $details['application_name']);
        $this->assertEquals($oldStatus, $details['old_status']);
        $this->assertEquals($newStatus, $details['new_status']);
    }

    public function testLogLeadStatusChangedWithMinimalData(): void
    {
        // Arrange
        $lead = $this->createMock(Lead::class);
        $customer = $this->createMock(\App\Model\Customer::class);
        
        $lead->method('getId')->willReturn(123);
        $lead->method('getLeadUuid')->willReturn('123e4567-e89b-12d3-a456-426614174000');
        $lead->method('getCustomer')->willReturn($customer);
        $lead->method('getApplicationName')->willReturn('test-app');
        
        $customer->method('getId')->willReturn(456);

        $oldStatus = 'new';
        $newStatus = 'contacted';

        // Expect entity manager calls
        $this->entityManager->expects($this->once())->method('persist')->with($this->isInstanceOf(Event::class));
        $this->entityManager->expects($this->once())->method('flush');

        // Act
        $result = $this->eventService->logLeadStatusChanged(
            $lead,
            $oldStatus,
            $newStatus
        );

        // Assert
        $this->assertInstanceOf(Event::class, $result);
        $this->assertEquals('lead_status_changed', $result->getEventType());
        $this->assertEquals('lead', $result->getEntityType());
        $this->assertEquals(123, $result->getEntityId());
        $this->assertNull($result->getUserId());
        $this->assertNull($result->getIpAddress());
        $this->assertNull($result->getUserAgent());

        $details = $result->getDetails();
        $this->assertIsArray($details);
        $this->assertEquals('123e4567-e89b-12d3-a456-426614174000', $details['lead_uuid']);
        $this->assertEquals(456, $details['customer_id']);
        $this->assertEquals('test-app', $details['application_name']);
        $this->assertEquals($oldStatus, $details['old_status']);
        $this->assertEquals($newStatus, $details['new_status']);
    }

    public function testLogLeadStatusChangedWithAllStatuses(): void
    {
        $statuses = ['new', 'contacted', 'qualified', 'converted', 'rejected'];
        
        foreach ($statuses as $oldStatus) {
            foreach ($statuses as $newStatus) {
                if ($oldStatus === $newStatus) {
                    continue; // Skip same status
                }

                // Arrange
                $lead = $this->createMock(Lead::class);
                $customer = $this->createMock(\App\Model\Customer::class);
                
                $lead->method('getId')->willReturn(123);
                $lead->method('getLeadUuid')->willReturn('123e4567-e89b-12d3-a456-426614174000');
                $lead->method('getCustomer')->willReturn($customer);
                $lead->method('getApplicationName')->willReturn('test-app');
                
                $customer->method('getId')->willReturn(456);

                // Expect entity manager calls for this iteration
                $this->entityManager->expects($this->once())->method('persist')->with($this->isInstanceOf(Event::class));
                $this->entityManager->expects($this->once())->method('flush');

                // Act
                $result = $this->eventService->logLeadStatusChanged(
                    $lead,
                    $oldStatus,
                    $newStatus
                );

                // Assert
                $this->assertInstanceOf(Event::class, $result);
                $this->assertEquals('lead_status_changed', $result->getEventType());
                
                $details = $result->getDetails();
                $this->assertEquals($oldStatus, $details['old_status']);
                $this->assertEquals($newStatus, $details['new_status']);
                
                // Reset the mock for the next iteration
                $this->setUp();
            }
        }
    }
}
