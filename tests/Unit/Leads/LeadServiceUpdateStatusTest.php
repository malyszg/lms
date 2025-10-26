<?php

declare(strict_types=1);

namespace App\Tests\Unit\Leads;

use App\DTO\UpdateLeadRequest;
use App\Exception\LeadNotFoundException;
use App\Exception\ValidationException;
use App\Leads\EventServiceInterface;
use App\Leads\LeadService;
use App\Model\Event;
use App\Model\Lead;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Unit tests for LeadService::updateLeadStatus method
 */
class LeadServiceUpdateStatusTest extends TestCase
{
    private LeadService $leadService;
    private EntityManagerInterface $entityManager;
    private EventServiceInterface $eventService;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->eventService = $this->createMock(EventServiceInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->leadService = new LeadService(
            $this->entityManager,
            $this->createMock(\App\Leads\CustomerServiceInterface::class),
            $this->createMock(\App\Leads\LeadPropertyServiceInterface::class),
            $this->eventService,
            $this->createMock(\App\ApiClient\CDPDeliveryServiceInterface::class),
            $this->createMock(\App\Leads\LeadScoringServiceInterface::class),
            $this->createMock(\Symfony\Component\Messenger\MessageBusInterface::class),
            $this->logger
        );
    }

    public function testUpdateLeadStatusSuccess(): void
    {
        // Arrange
        $leadUuid = '123e4567-e89b-12d3-a456-426614174000';
        $request = new UpdateLeadRequest(status: 'contacted');
        $ipAddress = '192.168.1.1';
        $userAgent = 'Mozilla/5.0';

        $lead = $this->createMock(Lead::class);
        $lead->method('getLeadUuid')->willReturn($leadUuid);
        $lead->method('getStatus')->willReturn('new');
        $lead->method('getUpdatedAt')->willReturn(new \DateTime());

        $event = $this->createMock(Event::class);

        // Mock findByUuid to return lead
        $this->leadService = $this->getMockBuilder(LeadService::class)
            ->setConstructorArgs([
                $this->entityManager,
                $this->createMock(\App\Leads\CustomerServiceInterface::class),
                $this->createMock(\App\Leads\LeadPropertyServiceInterface::class),
                $this->eventService,
                $this->createMock(\App\ApiClient\CDPDeliveryServiceInterface::class),
                $this->createMock(\App\Leads\LeadScoringServiceInterface::class),
                $this->createMock(\Symfony\Component\Messenger\MessageBusInterface::class),
                $this->logger
            ])
            ->onlyMethods(['findByUuid'])
            ->getMock();

        $this->leadService->method('findByUuid')->willReturn($lead);

        // Expect transaction methods
        $this->entityManager->expects($this->once())->method('beginTransaction');
        $this->entityManager->expects($this->once())->method('commit');
        $this->entityManager->expects($this->never())->method('rollback');

        // Expect lead update
        $lead->expects($this->once())->method('setStatus')->with('contacted');
        $lead->expects($this->once())->method('setUpdatedAt')->with($this->isInstanceOf(\DateTime::class));

        // Expect persistence
        $this->entityManager->expects($this->once())->method('persist')->with($lead);
        $this->entityManager->expects($this->once())->method('flush');

        // Expect event logging
        $this->eventService->expects($this->once())
            ->method('logLeadStatusChanged')
            ->with($lead, 'new', 'contacted', null, $ipAddress, $userAgent)
            ->willReturn($event);

        // Expect success logging
        $this->logger->expects($this->once())
            ->method('info')
            ->with('Lead status updated successfully', $this->isType('array'));

        // Act
        $result = $this->leadService->updateLeadStatus($leadUuid, $request, $ipAddress, $userAgent);

        // Assert
        $this->assertSame($lead, $result);
    }

    public function testUpdateLeadStatusWithInvalidStatus(): void
    {
        // Arrange
        $leadUuid = '123e4567-e89b-12d3-a456-426614174000';
        $request = new UpdateLeadRequest(status: 'invalid_status');

        // Act & Assert
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Validation failed');

        $this->leadService->updateLeadStatus($leadUuid, $request);
    }

    public function testUpdateLeadStatusLeadNotFound(): void
    {
        // Arrange
        $leadUuid = '123e4567-e89b-12d3-a456-426614174000';
        $request = new UpdateLeadRequest(status: 'contacted');

        // Mock findByUuid to return null
        $this->leadService = $this->getMockBuilder(LeadService::class)
            ->setConstructorArgs([
                $this->entityManager,
                $this->createMock(\App\Leads\CustomerServiceInterface::class),
                $this->createMock(\App\Leads\LeadPropertyServiceInterface::class),
                $this->eventService,
                $this->createMock(\App\ApiClient\CDPDeliveryServiceInterface::class),
                $this->createMock(\App\Leads\LeadScoringServiceInterface::class),
                $this->createMock(\Symfony\Component\Messenger\MessageBusInterface::class),
                $this->logger
            ])
            ->onlyMethods(['findByUuid'])
            ->getMock();

        $this->leadService->method('findByUuid')->willReturn(null);

        // Expect transaction methods
        $this->entityManager->expects($this->once())->method('beginTransaction');
        $this->entityManager->expects($this->once())->method('rollback');
        $this->entityManager->expects($this->never())->method('commit');

        // Expect error logging
        $this->logger->expects($this->once())
            ->method('error')
            ->with('Failed to update lead status', $this->isType('array'));

        // Act & Assert
        $this->expectException(LeadNotFoundException::class);

        $this->leadService->updateLeadStatus($leadUuid, $request);
    }

    public function testUpdateLeadStatusWithDatabaseError(): void
    {
        // Arrange
        $leadUuid = '123e4567-e89b-12d3-a456-426614174000';
        $request = new UpdateLeadRequest(status: 'contacted');

        $lead = $this->createMock(Lead::class);
        $lead->method('getLeadUuid')->willReturn($leadUuid);
        $lead->method('getStatus')->willReturn('new');

        // Mock findByUuid to return lead
        $this->leadService = $this->getMockBuilder(LeadService::class)
            ->setConstructorArgs([
                $this->entityManager,
                $this->createMock(\App\Leads\CustomerServiceInterface::class),
                $this->createMock(\App\Leads\LeadPropertyServiceInterface::class),
                $this->eventService,
                $this->createMock(\App\ApiClient\CDPDeliveryServiceInterface::class),
                $this->createMock(\App\Leads\LeadScoringServiceInterface::class),
                $this->createMock(\Symfony\Component\Messenger\MessageBusInterface::class),
                $this->logger
            ])
            ->onlyMethods(['findByUuid'])
            ->getMock();

        $this->leadService->method('findByUuid')->willReturn($lead);

        // Mock database error
        $this->entityManager->expects($this->once())->method('beginTransaction');
        $this->entityManager->expects($this->once())->method('flush')->willThrowException(new \Exception('Database error'));
        $this->entityManager->expects($this->once())->method('rollback');
        $this->entityManager->expects($this->never())->method('commit');

        // Expect error logging
        $this->logger->expects($this->once())
            ->method('error')
            ->with('Failed to update lead status', $this->isType('array'));

        // Act & Assert
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Database error');

        $this->leadService->updateLeadStatus($leadUuid, $request);
    }

    public function testUpdateLeadStatusWithAllValidStatuses(): void
    {
        $validStatuses = ['new', 'contacted', 'qualified', 'converted', 'rejected'];
        
        foreach ($validStatuses as $status) {
            // Reset mocks for each iteration
            $this->setUp();
            
            // Arrange
            $leadUuid = '123e4567-e89b-12d3-a456-426614174000';
            $request = new UpdateLeadRequest(status: $status);

            $lead = $this->createMock(Lead::class);
            $lead->method('getLeadUuid')->willReturn($leadUuid);
            $lead->method('getStatus')->willReturn('new');
            $lead->method('getUpdatedAt')->willReturn(new \DateTime());

            $event = $this->createMock(Event::class);

            // Mock findByUuid to return lead
            $this->leadService = $this->getMockBuilder(LeadService::class)
                ->setConstructorArgs([
                    $this->entityManager,
                    $this->createMock(\App\Leads\CustomerServiceInterface::class),
                    $this->createMock(\App\Leads\LeadPropertyServiceInterface::class),
                    $this->eventService,
                    $this->createMock(\App\ApiClient\CDPDeliveryServiceInterface::class),
                    $this->createMock(\App\Leads\LeadScoringServiceInterface::class),
                    $this->createMock(\Symfony\Component\Messenger\MessageBusInterface::class),
                    $this->logger
                ])
                ->onlyMethods(['findByUuid'])
                ->getMock();

            $this->leadService->method('findByUuid')->willReturn($lead);

            // Expect transaction methods
            $this->entityManager->expects($this->once())->method('beginTransaction');
            $this->entityManager->expects($this->once())->method('commit');
            $this->entityManager->expects($this->never())->method('rollback');

            // Expect lead update
            $lead->expects($this->once())->method('setStatus')->with($status);
            $lead->expects($this->once())->method('setUpdatedAt')->with($this->isInstanceOf(\DateTime::class));

            // Expect persistence
            $this->entityManager->expects($this->once())->method('persist')->with($lead);
            $this->entityManager->expects($this->once())->method('flush');

            // Expect event logging
            $this->eventService->expects($this->once())
                ->method('logLeadStatusChanged')
                ->with($lead, 'new', $status, null, null, null)
                ->willReturn($event);

            // Act
            $result = $this->leadService->updateLeadStatus($leadUuid, $request);

            // Assert
            $this->assertSame($lead, $result, "Status '{$status}' should be valid");
        }
    }
}
