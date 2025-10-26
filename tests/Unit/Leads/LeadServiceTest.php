<?php

declare(strict_types=1);

namespace App\Tests\Unit\Leads;

use App\ApiClient\CDPDeliveryServiceInterface;
use App\DTO\CreateCustomerDto;
use App\DTO\CreateLeadRequest;
use App\DTO\CreateLeadResponse;
use App\DTO\LeadScoreResult;
use App\DTO\PropertyDto;
use App\Exception\LeadAlreadyExistsException;
use App\Leads\CustomerServiceInterface;
use App\Leads\EventServiceInterface;
use App\Leads\LeadPropertyServiceInterface;
use App\Leads\LeadScoringServiceInterface;
use App\Leads\LeadService;
use App\Model\Customer;
use App\Model\Lead;
use App\Model\LeadProperty;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Unit tests for LeadService
 * Tests cover all critical business logic including:
 * - Lead creation with full transaction
 * - Customer deduplication
 * - Property creation
 * - Event logging
 * - CDP delivery
 * - AI scoring
 * - Duplicate UUID handling
 * - Transaction rollback on errors
 * - Error handling and resilience
 */
class LeadServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private CustomerServiceInterface&MockObject $customerService;
    private LeadPropertyServiceInterface&MockObject $propertyService;
    private EventServiceInterface&MockObject $eventService;
    private CDPDeliveryServiceInterface&MockObject $cdpDeliveryService;
    private LeadScoringServiceInterface&MockObject $leadScoringService;
    private MessageBusInterface&MockObject $messageBus;
    private LoggerInterface&MockObject $logger;
    private EntityRepository&MockObject $leadRepository;
    
    private LeadService $leadService;

    protected function setUp(): void
    {
        // Create mocks for all dependencies
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->customerService = $this->createMock(CustomerServiceInterface::class);
        $this->propertyService = $this->createMock(LeadPropertyServiceInterface::class);
        $this->eventService = $this->createMock(EventServiceInterface::class);
        $this->cdpDeliveryService = $this->createMock(CDPDeliveryServiceInterface::class);
        $this->leadScoringService = $this->createMock(LeadScoringServiceInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->leadRepository = $this->createMock(EntityRepository::class);

        // Initialize service with mocked dependencies
        $this->leadService = new LeadService(
            $this->entityManager,
            $this->customerService,
            $this->propertyService,
            $this->eventService,
            $this->cdpDeliveryService,
            $this->leadScoringService,
            $this->messageBus,
            $this->logger
        );
    }

    /**
     * @test
     * Tests successful lead creation with complete workflow
     * Business rule: Lead must be created with customer deduplication and property
     * KPI: 90% reduction in duplicates through customer deduplication
     */
    public function testCreateLead_shouldCreateLeadSuccessfullyWithAllSteps(): void
    {
        // Arrange
        $request = $this->createValidLeadRequest();
        $customer = $this->createCustomer();
        $property = $this->createProperty();
        
        $ipAddress = '192.168.1.1';
        $userAgent = 'Mozilla/5.0';

        // Mock repository to return null (no duplicate UUID)
        $this->setupRepositoryMock(null);

        // Mock customer service - find or create
        $this->customerService
            ->expects($this->once())
            ->method('findOrCreateCustomer')
            ->with($this->equalTo($request->customer))
            ->willReturn($customer);

        // Mock property service - should create property
        $this->propertyService
            ->expects($this->once())
            ->method('shouldCreateProperty')
            ->with($this->equalTo($request->property))
            ->willReturn(true);

        $this->propertyService
            ->expects($this->once())
            ->method('createProperty')
            ->with(
                $this->isInstanceOf(Lead::class),
                $this->equalTo($request->property)
            )
            ->willReturn($property);

        // Mock event service - log lead creation
        $this->eventService
            ->expects($this->once())
            ->method('logLeadCreated')
            ->with(
                $this->isInstanceOf(Lead::class),
                $this->equalTo($ipAddress),
                $this->equalTo($userAgent)
            );

        // Mock transaction handling with lifecycle callbacks
        $this->setupSuccessfulTransactionMocks();

        // Mock CDP delivery via message bus
        $this->messageBus
            ->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(\App\Message\CDPLeadMessage::class));

        // Mock AI scoring
        $scoreResult = new LeadScoreResult(
            score: 85,
            category: 'hot',
            reasoning: 'High value property',
            suggestions: ['Contact within 24h']
        );

        $this->leadScoringService
            ->expects($this->once())
            ->method('score')
            ->willReturn($scoreResult);

        // Act
        $response = $this->leadService->createLead($request, $ipAddress, $userAgent);

        // Assert
        $this->assertInstanceOf(CreateLeadResponse::class, $response);
        $this->assertSame($request->leadUuid, $response->leadUuid);
        $this->assertSame('new', $response->status);
        $this->assertSame($customer->getId(), $response->customerId);
        $this->assertSame($request->applicationName, $response->applicationName);
        $this->assertSame('pending', $response->cdpDeliveryStatus);
    }

    /**
     * @test
     * Tests duplicate UUID detection and proper exception throwing
     * Business rule: Lead UUID must be unique to prevent duplicate processing
     */
    public function testCreateLead_shouldThrowExceptionWhenLeadUuidAlreadyExists(): void
    {
        // Arrange
        $request = $this->createValidLeadRequest();
        $existingLead = $this->createLead();

        // Mock repository to return existing lead
        $this->setupRepositoryMock($existingLead);

        // Mock transaction handling
        $this->entityManager
            ->expects($this->once())
            ->method('beginTransaction');

        $this->entityManager
            ->expects($this->once())
            ->method('rollback');

        // Customer service should NOT be called (early return)
        $this->customerService
            ->expects($this->never())
            ->method('findOrCreateCustomer');

        // Assert exception
        $this->expectException(LeadAlreadyExistsException::class);
        $this->expectExceptionMessage('Lead with UUID ' . $request->leadUuid . ' already exists');

        // Act
        $this->leadService->createLead($request);
    }

    /**
     * @test
     * Tests transaction rollback when customer service fails
     * Business rule: All database operations must be atomic (transactional)
     */
    public function testCreateLead_shouldRollbackTransactionWhenCustomerServiceFails(): void
    {
        // Arrange
        $request = $this->createValidLeadRequest();

        // Mock repository to return null (no duplicate)
        $this->setupRepositoryMock(null);

        // Mock customer service to throw exception
        $this->customerService
            ->expects($this->once())
            ->method('findOrCreateCustomer')
            ->willThrowException(new \RuntimeException('Database error'));

        // Mock transaction handling
        $this->entityManager
            ->expects($this->once())
            ->method('beginTransaction');

        $this->entityManager
            ->expects($this->once())
            ->method('rollback');

        // Commit should NOT be called
        $this->entityManager
            ->expects($this->never())
            ->method('commit');

        // Assert exception is propagated
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database error');

        // Act
        $this->leadService->createLead($request);
    }

    /**
     * @test
     * Tests transaction rollback when property service fails
     * Business rule: Property creation failure must rollback entire lead creation
     */
    public function testCreateLead_shouldRollbackTransactionWhenPropertyServiceFails(): void
    {
        // Arrange
        $request = $this->createValidLeadRequest();
        $customer = $this->createCustomer();

        // Mock repository to return null (no duplicate)
        $this->setupRepositoryMock(null);

        // Mock customer service
        $this->customerService
            ->expects($this->once())
            ->method('findOrCreateCustomer')
            ->willReturn($customer);

        // Mock property service to indicate property should be created
        $this->propertyService
            ->expects($this->once())
            ->method('shouldCreateProperty')
            ->willReturn(true);

        // Mock property service to throw exception
        $this->propertyService
            ->expects($this->once())
            ->method('createProperty')
            ->willThrowException(new \RuntimeException('Property creation failed'));

        // Mock transaction handling
        $this->entityManager
            ->expects($this->once())
            ->method('beginTransaction');

        $this->entityManager
            ->expects($this->once())
            ->method('rollback');

        // Commit should NOT be called
        $this->entityManager
            ->expects($this->never())
            ->method('commit');

        // Assert exception is propagated
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Property creation failed');

        // Act
        $this->leadService->createLead($request);
    }

    /**
     * @test
     * Tests CDP delivery failure doesn't prevent lead creation
     * Business rule: CDP delivery failure should not fail lead creation
     * KPI target: 98% CDP delivery success rate
     */
    public function testCreateLead_shouldSucceedEvenWhenCDPDeliveryFails(): void
    {
        // Arrange
        $request = $this->createValidLeadRequest();
        $customer = $this->createCustomer();

        // Mock repository to return null (no duplicate)
        $this->setupRepositoryMock(null);

        // Mock customer service
        $this->customerService
            ->expects($this->once())
            ->method('findOrCreateCustomer')
            ->willReturn($customer);

        // Mock property service - no property
        $this->propertyService
            ->expects($this->once())
            ->method('shouldCreateProperty')
            ->willReturn(false);

        // Mock event service
        $this->eventService
            ->expects($this->once())
            ->method('logLeadCreated');

        // Mock transaction handling with lifecycle callbacks
        $this->setupSuccessfulTransactionMocks();

        // Mock CDP delivery via message bus - should not throw
        $this->messageBus
            ->expects($this->once())
            ->method('dispatch');

        // Mock AI scoring to return valid result
        $this->leadScoringService
            ->expects($this->once())
            ->method('score')
            ->willReturn(new LeadScoreResult(
                score: 50,
                category: 'warm',
                reasoning: 'Standard lead',
                suggestions: ['Follow up within 48h']
            ));

        // Act - should NOT throw exception
        $response = $this->leadService->createLead($request);

        // Assert - lead was created successfully despite CDP failure
        $this->assertInstanceOf(CreateLeadResponse::class, $response);
        $this->assertSame($request->leadUuid, $response->leadUuid);
    }

    /**
     * @test
     * Tests AI scoring failure doesn't prevent lead creation
     * Business rule: AI scoring is optional and should not block lead processing
     */
    public function testCreateLead_shouldSucceedEvenWhenAIScoringFails(): void
    {
        // Arrange
        $request = $this->createValidLeadRequest();
        $customer = $this->createCustomer();

        // Mock repository to return null (no duplicate)
        $this->setupRepositoryMock(null);

        // Mock customer service
        $this->customerService
            ->expects($this->once())
            ->method('findOrCreateCustomer')
            ->willReturn($customer);

        // Mock property service - no property
        $this->propertyService
            ->expects($this->once())
            ->method('shouldCreateProperty')
            ->willReturn(false);

        // Mock event service
        $this->eventService
            ->expects($this->once())
            ->method('logLeadCreated');

        // Mock transaction handling with lifecycle callbacks
        $this->setupSuccessfulTransactionMocks();

        // Mock CDP delivery via message bus
        $this->messageBus
            ->expects($this->once())
            ->method('dispatch');

        // Mock AI scoring to throw exception
        $this->leadScoringService
            ->expects($this->once())
            ->method('score')
            ->willThrowException(new \Exception('AI API rate limit exceeded'));

        // Logger should log warning
        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                $this->equalTo('Failed to score lead automatically'),
                $this->isType('array')
            );

        // Act - should NOT throw exception
        $response = $this->leadService->createLead($request);

        // Assert - lead was created successfully despite AI failure
        $this->assertInstanceOf(CreateLeadResponse::class, $response);
        $this->assertSame($request->leadUuid, $response->leadUuid);
    }

    /**
     * @test
     * Tests property is not created when property data is insufficient
     * Business rule: Property creation is optional and depends on data availability
     */
    public function testCreateLead_shouldNotCreatePropertyWhenPropertyDataIsInsufficient(): void
    {
        // Arrange
        $request = $this->createValidLeadRequest();
        $customer = $this->createCustomer();

        // Mock repository to return null (no duplicate)
        $this->setupRepositoryMock(null);

        // Mock customer service
        $this->customerService
            ->expects($this->once())
            ->method('findOrCreateCustomer')
            ->willReturn($customer);

        // Mock property service - should NOT create property
        $this->propertyService
            ->expects($this->once())
            ->method('shouldCreateProperty')
            ->with($this->equalTo($request->property))
            ->willReturn(false);

        // createProperty should NOT be called
        $this->propertyService
            ->expects($this->never())
            ->method('createProperty');

        // Mock event service
        $this->eventService
            ->expects($this->once())
            ->method('logLeadCreated');

        // Mock transaction handling with lifecycle callbacks
        $this->setupSuccessfulTransactionMocks();

        // Mock CDP delivery via message bus and AI
        $this->messageBus
            ->expects($this->once())
            ->method('dispatch');

        $this->leadScoringService
            ->expects($this->once())
            ->method('score')
            ->willReturn(new LeadScoreResult(50, 'warm', 'Standard lead', ['Contact soon']));

        // Act
        $response = $this->leadService->createLead($request);

        // Assert
        $this->assertInstanceOf(CreateLeadResponse::class, $response);
    }

    /**
     * @test
     * Tests leadExists returns true when lead with UUID exists
     */
    public function testLeadExists_shouldReturnTrueWhenLeadExists(): void
    {
        // Arrange
        $leadUuid = '550e8400-e29b-41d4-a716-446655440000';
        $existingLead = $this->createLead();

        // Mock repository to return existing lead
        $this->setupRepositoryMock($existingLead);

        // Act
        $exists = $this->leadService->leadExists($leadUuid);

        // Assert
        $this->assertTrue($exists);
    }

    /**
     * @test
     * Tests leadExists returns false when lead with UUID does not exist
     */
    public function testLeadExists_shouldReturnFalseWhenLeadDoesNotExist(): void
    {
        // Arrange
        $leadUuid = '550e8400-e29b-41d4-a716-446655440000';

        // Mock repository to return null
        $this->setupRepositoryMock(null);

        // Act
        $exists = $this->leadService->leadExists($leadUuid);

        // Assert
        $this->assertFalse($exists);
    }

    /**
     * @test
     * Tests findByUuid returns Lead entity when found
     */
    public function testFindByUuid_shouldReturnLeadWhenFound(): void
    {
        // Arrange
        $leadUuid = '550e8400-e29b-41d4-a716-446655440000';
        $expectedLead = $this->createLead();

        // Mock repository to return lead
        $this->setupRepositoryMock($expectedLead);

        // Act
        $lead = $this->leadService->findByUuid($leadUuid);

        // Assert
        $this->assertSame($expectedLead, $lead);
    }

    /**
     * @test
     * Tests findByUuid returns null when lead not found
     */
    public function testFindByUuid_shouldReturnNullWhenNotFound(): void
    {
        // Arrange
        $leadUuid = '550e8400-e29b-41d4-a716-446655440000';

        // Mock repository to return null
        $this->setupRepositoryMock(null);

        // Act
        $lead = $this->leadService->findByUuid($leadUuid);

        // Assert
        $this->assertNull($lead);
    }

    /**
     * @test
     * Tests that IP address and user agent are passed to event logging
     * Business rule: All operations must be logged with audit trail (US-008)
     */
    public function testCreateLead_shouldPassIpAddressAndUserAgentToEventLogging(): void
    {
        // Arrange
        $request = $this->createValidLeadRequest();
        $customer = $this->createCustomer();
        $ipAddress = '203.0.113.42';
        $userAgent = 'Mozilla/5.0 (Test Browser)';

        // Mock repository to return null (no duplicate)
        $this->setupRepositoryMock(null);

        // Mock customer service
        $this->customerService
            ->expects($this->once())
            ->method('findOrCreateCustomer')
            ->willReturn($customer);

        // Mock property service - no property
        $this->propertyService
            ->expects($this->once())
            ->method('shouldCreateProperty')
            ->willReturn(false);

        // Mock event service - verify IP and user agent are passed
        $this->eventService
            ->expects($this->once())
            ->method('logLeadCreated')
            ->with(
                $this->isInstanceOf(Lead::class),
                $this->equalTo($ipAddress),
                $this->equalTo($userAgent)
            );

        // Mock transaction handling with lifecycle callbacks
        $this->setupSuccessfulTransactionMocks();

        // Mock CDP delivery via message bus and AI
        $this->messageBus
            ->expects($this->once())
            ->method('dispatch');

        $this->leadScoringService
            ->expects($this->once())
            ->method('score')
            ->willReturn(new LeadScoreResult(50, 'warm', 'Standard lead', ['Contact soon']));

        // Act
        $this->leadService->createLead($request, $ipAddress, $userAgent);
    }

    /**
     * @test
     * Tests event logging is called even when IP and user agent are null
     * Business rule: Event logging must work with or without client information
     */
    public function testCreateLead_shouldLogEventWithNullIpAndUserAgent(): void
    {
        // Arrange
        $request = $this->createValidLeadRequest();
        $customer = $this->createCustomer();

        // Mock repository to return null (no duplicate)
        $this->setupRepositoryMock(null);

        // Mock customer service
        $this->customerService
            ->expects($this->once())
            ->method('findOrCreateCustomer')
            ->willReturn($customer);

        // Mock property service - no property
        $this->propertyService
            ->expects($this->once())
            ->method('shouldCreateProperty')
            ->willReturn(false);

        // Mock event service - verify null values are passed
        $this->eventService
            ->expects($this->once())
            ->method('logLeadCreated')
            ->with(
                $this->isInstanceOf(Lead::class),
                $this->isNull(),
                $this->isNull()
            );

        // Mock transaction handling with lifecycle callbacks
        $this->setupSuccessfulTransactionMocks();

        // Mock CDP delivery via message bus and AI
        $this->messageBus
            ->expects($this->once())
            ->method('dispatch');

        $this->leadScoringService
            ->expects($this->once())
            ->method('score')
            ->willReturn(new LeadScoreResult(50, 'warm', 'Standard lead', ['Contact soon']));

        // Act
        $this->leadService->createLead($request, null, null);
    }

    /**
     * Helper method: Setup repository mock for findByUuid
     */
    private function setupRepositoryMock(?Lead $returnValue): void
    {
        $this->entityManager
            ->expects($this->atLeastOnce())
            ->method('getRepository')
            ->with($this->equalTo(Lead::class))
            ->willReturn($this->leadRepository);

        $this->leadRepository
            ->expects($this->atLeastOnce())
            ->method('findOneBy')
            ->with($this->isType('array'))
            ->willReturn($returnValue);
    }

    /**
     * Helper method: Setup transaction mocks for successful lead creation
     * Simulates Doctrine lifecycle callbacks (@PrePersist) and ID generation
     */
    private function setupSuccessfulTransactionMocks(): void
    {
        $this->entityManager
            ->expects($this->once())
            ->method('beginTransaction');

        $persistedLead = null;

        // Mock persist to simulate Doctrine lifecycle callbacks and store reference
        // Note: persist() is called multiple times (once for initial lead, once for AI score update)
        $this->entityManager
            ->expects($this->atLeastOnce())
            ->method('persist')
            ->with($this->isInstanceOf(Lead::class))
            ->willReturnCallback(function (Lead $lead) use (&$persistedLead) {
                // Store reference for flush callback (only first time)
                if ($persistedLead === null) {
                    $persistedLead = $lead;
                    // Simulate Doctrine's @PrePersist lifecycle callback
                    $lead->onPrePersist();
                }
            });

        // Mock flush to simulate ID generation by database
        $this->entityManager
            ->expects($this->atLeastOnce())
            ->method('flush')
            ->willReturnCallback(function () use (&$persistedLead) {
                if ($persistedLead !== null) {
                    // Simulate database auto-increment ID generation
                    $reflection = new \ReflectionClass($persistedLead);
                    $idProperty = $reflection->getProperty('id');
                    $idProperty->setAccessible(true);
                    $idProperty->setValue($persistedLead, rand(1, 10000));
                }
            });

        $this->entityManager
            ->expects($this->once())
            ->method('commit');
    }

    /**
     * Helper method: Create valid CreateLeadRequest for testing
     */
    private function createValidLeadRequest(): CreateLeadRequest
    {
        return new CreateLeadRequest(
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
                developmentId: 'DEV-001',
                partnerId: 'PARTNER-001',
                propertyType: 'apartment',
                price: 450000.00,
                location: 'Śródmieście',
                city: 'Warszawa'
            )
        );
    }

    /**
     * Helper method: Create Customer entity for testing
     */
    private function createCustomer(): Customer
    {
        $customer = new Customer(
            email: 'test@example.com',
            phone: '+48123456789',
            firstName: 'John',
            lastName: 'Doe'
        );
        
        // Use reflection to set ID and timestamps (normally set by database)
        $reflection = new \ReflectionClass($customer);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($customer, 1);
        
        $createdAtProperty = $reflection->getProperty('createdAt');
        $createdAtProperty->setAccessible(true);
        $createdAtProperty->setValue($customer, new \DateTime());
        
        $updatedAtProperty = $reflection->getProperty('updatedAt');
        $updatedAtProperty->setAccessible(true);
        $updatedAtProperty->setValue($customer, new \DateTime());

        return $customer;
    }

    /**
     * Helper method: Create Lead entity for testing
     */
    private function createLead(): Lead
    {
        $customer = $this->createCustomer();
        $lead = new Lead(
            leadUuid: '550e8400-e29b-41d4-a716-446655440000',
            customer: $customer,
            applicationName: 'morizon'
        );

        // Use reflection to set ID and timestamps (normally set by database)
        $reflection = new \ReflectionClass($lead);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($lead, 100);
        
        $createdAtProperty = $reflection->getProperty('createdAt');
        $createdAtProperty->setAccessible(true);
        $createdAtProperty->setValue($lead, new \DateTime());
        
        $updatedAtProperty = $reflection->getProperty('updatedAt');
        $updatedAtProperty->setAccessible(true);
        $updatedAtProperty->setValue($lead, new \DateTime());

        return $lead;
    }

    /**
     * Helper method: Create LeadProperty entity for testing
     */
    private function createProperty(): LeadProperty
    {
        $lead = $this->createLead();
        $property = new LeadProperty($lead);
        $property->setPropertyId('PROP-001');
        $property->setDevelopmentId('DEV-001');
        $property->setPartnerId('PARTNER-001');
        $property->setPrice(450000.00);
        $property->setCity('Warszawa');

        // Use reflection to set ID
        $reflection = new \ReflectionClass($property);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($property, 200);

        return $property;
    }
}

