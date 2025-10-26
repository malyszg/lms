<?php

declare(strict_types=1);

namespace App\Tests\Unit\Leads;

use App\Leads\EventService;
use App\Model\Customer;
use App\Model\Event;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for EventService::logCustomerPreferencesChanged()
 */
class EventServiceLogCustomerPreferencesChangedTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private EventService $eventService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->eventService = new EventService($this->entityManager);
    }

    /**
     * Test successful logging of customer preferences changed event
     */
    public function testLogCustomerPreferencesChangedSuccess(): void
    {
        // Arrange
        /** @var Customer&MockObject $customer */
        $customer = $this->createMock(Customer::class);
        $customer->method('getId')->willReturn(1);
        $customer->method('getEmail')->willReturn('test@example.com');
        $customer->method('getPhone')->willReturn('123456789');

        $oldPreferences = [
            'price_min' => 100000.0,
            'price_max' => 500000.0,
            'location' => 'Stara lokalizacja',
            'city' => 'Warszawa'
        ];

        $newPreferences = [
            'price_min' => 200000.0,
            'price_max' => 600000.0,
            'location' => 'Nowa lokalizacja',
            'city' => 'Kraków'
        ];

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        // Act
        $event = $this->eventService->logCustomerPreferencesChanged(
            $customer,
            $oldPreferences,
            $newPreferences,
            1, // userId
            '127.0.0.1', // ipAddress
            'Mozilla/5.0' // userAgent
        );

        // Assert
        $this->assertInstanceOf(Event::class, $event);
        $this->assertEquals('customer_preferences_changed', $event->getEventType());
        $this->assertEquals('customer', $event->getEntityType());
        $this->assertEquals(1, $event->getEntityId());
        $this->assertEquals(1, $event->getUserId());
        $this->assertEquals('127.0.0.1', $event->getIpAddress());
        $this->assertEquals('Mozilla/5.0', $event->getUserAgent());

        $details = $event->getDetails();
        $this->assertEquals(1, $details['customer_id']);
        $this->assertEquals('test@example.com', $details['customer_email']);
        $this->assertEquals('123456789', $details['customer_phone']);
        $this->assertEquals($oldPreferences, $details['old_preferences']);
        $this->assertEquals($newPreferences, $details['new_preferences']);

        // Check changes calculation
        $changes = $details['changes'];
        $this->assertArrayHasKey('price_min', $changes);
        $this->assertArrayHasKey('price_max', $changes);
        $this->assertArrayHasKey('location', $changes);
        $this->assertArrayHasKey('city', $changes);

        $this->assertEquals(['old' => 100000.0, 'new' => 200000.0], $changes['price_min']);
        $this->assertEquals(['old' => 500000.0, 'new' => 600000.0], $changes['price_max']);
        $this->assertEquals(['old' => 'Stara lokalizacja', 'new' => 'Nowa lokalizacja'], $changes['location']);
        $this->assertEquals(['old' => 'Warszawa', 'new' => 'Kraków'], $changes['city']);
    }

    /**
     * Test logging with null old preferences (new customer)
     */
    public function testLogCustomerPreferencesChangedWithNullOldPreferences(): void
    {
        // Arrange
        /** @var Customer&MockObject $customer */
        $customer = $this->createMock(Customer::class);
        $customer->method('getId')->willReturn(1);
        $customer->method('getEmail')->willReturn('new@example.com');
        $customer->method('getPhone')->willReturn('987654321');

        $newPreferences = [
            'price_min' => 300000.0,
            'price_max' => 700000.0,
            'location' => 'Pierwsza lokalizacja',
            'city' => 'Gdańsk'
        ];

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        // Act
        $event = $this->eventService->logCustomerPreferencesChanged(
            $customer,
            null, // oldPreferences
            $newPreferences,
            2, // userId
            '192.168.1.1', // ipAddress
            'Chrome/91.0' // userAgent
        );

        // Assert
        $this->assertInstanceOf(Event::class, $event);
        $details = $event->getDetails();
        $this->assertNull($details['old_preferences']);
        $this->assertEquals($newPreferences, $details['new_preferences']);

        // All fields should be marked as changed
        $changes = $details['changes'];
        $this->assertArrayHasKey('price_min', $changes);
        $this->assertArrayHasKey('price_max', $changes);
        $this->assertArrayHasKey('location', $changes);
        $this->assertArrayHasKey('city', $changes);

        $this->assertEquals(['old' => null, 'new' => 300000.0], $changes['price_min']);
        $this->assertEquals(['old' => null, 'new' => 700000.0], $changes['price_max']);
        $this->assertEquals(['old' => null, 'new' => 'Pierwsza lokalizacja'], $changes['location']);
        $this->assertEquals(['old' => null, 'new' => 'Gdańsk'], $changes['city']);
    }

    /**
     * Test logging with minimal context (no user, IP, user agent)
     */
    public function testLogCustomerPreferencesChangedMinimalContext(): void
    {
        // Arrange
        /** @var Customer&MockObject $customer */
        $customer = $this->createMock(Customer::class);
        $customer->method('getId')->willReturn(3);
        $customer->method('getEmail')->willReturn('minimal@example.com');
        $customer->method('getPhone')->willReturn('555666777');

        $oldPreferences = ['price_min' => 100000.0];
        $newPreferences = ['price_min' => 200000.0];

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        // Act
        $event = $this->eventService->logCustomerPreferencesChanged(
            $customer,
            $oldPreferences,
            $newPreferences
            // No userId, ipAddress, userAgent
        );

        // Assert
        $this->assertInstanceOf(Event::class, $event);
        $this->assertNull($event->getUserId());
        $this->assertNull($event->getIpAddress());
        $this->assertNull($event->getUserAgent());

        $details = $event->getDetails();
        $this->assertEquals(3, $details['customer_id']);
        $this->assertEquals('minimal@example.com', $details['customer_email']);
        $this->assertEquals('555666777', $details['customer_phone']);
    }

    /**
     * Test logging with no changes (same preferences)
     */
    public function testLogCustomerPreferencesChangedNoChanges(): void
    {
        // Arrange
        /** @var Customer&MockObject $customer */
        $customer = $this->createMock(Customer::class);
        $customer->method('getId')->willReturn(4);
        $customer->method('getEmail')->willReturn('same@example.com');
        $customer->method('getPhone')->willReturn('111222333');

        $preferences = [
            'price_min' => 100000.0,
            'price_max' => 500000.0,
            'location' => 'Ta sama lokalizacja',
            'city' => 'To samo miasto'
        ];

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        // Act
        $event = $this->eventService->logCustomerPreferencesChanged(
            $customer,
            $preferences,
            $preferences, // Same preferences
            5, // userId
            '10.0.0.1', // ipAddress
            'Firefox/89.0' // userAgent
        );

        // Assert
        $this->assertInstanceOf(Event::class, $event);
        $details = $event->getDetails();
        
        // No changes should be detected
        $changes = $details['changes'];
        $this->assertEmpty($changes);
    }

    /**
     * Test logging with partial changes
     */
    public function testLogCustomerPreferencesChangedPartialChanges(): void
    {
        // Arrange
        /** @var Customer&MockObject $customer */
        $customer = $this->createMock(Customer::class);
        $customer->method('getId')->willReturn(5);
        $customer->method('getEmail')->willReturn('partial@example.com');
        $customer->method('getPhone')->willReturn('444555666');

        $oldPreferences = [
            'price_min' => 100000.0,
            'price_max' => 500000.0,
            'location' => 'Stara lokalizacja',
            'city' => 'Warszawa'
        ];

        $newPreferences = [
            'price_min' => 100000.0, // Same
            'price_max' => 600000.0, // Changed
            'location' => 'Stara lokalizacja', // Same
            'city' => 'Kraków' // Changed
        ];

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        // Act
        $event = $this->eventService->logCustomerPreferencesChanged(
            $customer,
            $oldPreferences,
            $newPreferences,
            6, // userId
            '172.16.0.1', // ipAddress
            'Safari/14.0' // userAgent
        );

        // Assert
        $this->assertInstanceOf(Event::class, $event);
        $details = $event->getDetails();
        
        // Only changed fields should be in changes
        $changes = $details['changes'];
        $this->assertCount(2, $changes);
        $this->assertArrayHasKey('price_max', $changes);
        $this->assertArrayHasKey('city', $changes);
        $this->assertArrayNotHasKey('price_min', $changes);
        $this->assertArrayNotHasKey('location', $changes);

        $this->assertEquals(['old' => 500000.0, 'new' => 600000.0], $changes['price_max']);
        $this->assertEquals(['old' => 'Warszawa', 'new' => 'Kraków'], $changes['city']);
    }
}
