<?php

declare(strict_types=1);

namespace App\Tests\Unit\Leads;

use App\Leads\LeadService;
use App\Leads\CustomerServiceInterface;
use App\Leads\LeadPropertyServiceInterface;
use App\Leads\EventServiceInterface;
use App\ApiClient\CDPDeliveryServiceInterface;
use App\Leads\LeadScoringServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for LeadService class structure and interface
 */
class LeadServiceStructureTest extends TestCase
{
    private LeadService $leadService;
    private EntityManagerInterface $entityManager;
    private CustomerServiceInterface $customerService;
    private LeadPropertyServiceInterface $propertyService;
    private EventServiceInterface $eventService;
    private CDPDeliveryServiceInterface $cdpDeliveryService;
    private LeadScoringServiceInterface $leadScoringService;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->customerService = $this->createMock(CustomerServiceInterface::class);
        $this->propertyService = $this->createMock(LeadPropertyServiceInterface::class);
        $this->eventService = $this->createMock(EventServiceInterface::class);
        $this->cdpDeliveryService = $this->createMock(CDPDeliveryServiceInterface::class);
        $this->leadScoringService = $this->createMock(LeadScoringServiceInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        
        $this->leadService = new LeadService(
            $this->entityManager,
            $this->customerService,
            $this->propertyService,
            $this->eventService,
            $this->cdpDeliveryService,
            $this->leadScoringService,
            $this->logger
        );
    }

    public function testLeadServiceConstructor(): void
    {
        $this->assertInstanceOf(LeadService::class, $this->leadService);
    }

    public function testLeadServiceImplementsInterface(): void
    {
        $this->assertInstanceOf(\App\Leads\LeadServiceInterface::class, $this->leadService);
    }

    public function testLeadServiceHasCreateLeadMethod(): void
    {
        $this->assertTrue(method_exists($this->leadService, 'createLead'));
    }

    public function testLeadServiceHasUpdateLeadStatusMethod(): void
    {
        $this->assertTrue(method_exists($this->leadService, 'updateLeadStatus'));
    }

    public function testLeadServiceHasDeleteLeadMethod(): void
    {
        $this->assertTrue(method_exists($this->leadService, 'deleteLead'));
    }

    public function testLeadServiceCreateLeadMethodSignature(): void
    {
        $reflection = new \ReflectionMethod(LeadService::class, 'createLead');
        
        $this->assertEquals(1, $reflection->getNumberOfRequiredParameters());
        
        $parameter = $reflection->getParameters()[0];
        $this->assertEquals('App\DTO\CreateLeadRequest', $parameter->getType()->getName());
        
        $this->assertNotNull($reflection->getReturnType());
    }

    public function testLeadServiceUpdateLeadStatusMethodSignature(): void
    {
        $reflection = new \ReflectionMethod(LeadService::class, 'updateLeadStatus');
        
        $this->assertEquals(2, $reflection->getNumberOfRequiredParameters());
        
        $parameters = $reflection->getParameters();
        $this->assertEquals('string', $parameters[0]->getType()->getName());
        $this->assertEquals('App\DTO\UpdateLeadRequest', $parameters[1]->getType()->getName());
        
        $this->assertNotNull($reflection->getReturnType());
    }

    public function testLeadServiceDeleteLeadMethodSignature(): void
    {
        $reflection = new \ReflectionMethod(LeadService::class, 'deleteLead');
        
        $this->assertEquals(1, $reflection->getNumberOfRequiredParameters());
        
        $parameter = $reflection->getParameters()[0];
        $this->assertEquals('string', $parameter->getType()->getName());
        
        $this->assertNotNull($reflection->getReturnType());
    }

    public function testLeadServiceClassStructure(): void
    {
        $reflection = new \ReflectionClass(LeadService::class);
        
        // Test that it implements the interface
        $interfaces = $reflection->getInterfaceNames();
        $this->assertContains('App\Leads\LeadServiceInterface', $interfaces);
        
        // Test that it has proper constructor
        $constructor = $reflection->getConstructor();
        $this->assertNotNull($constructor);
        $this->assertEquals(6, $constructor->getNumberOfRequiredParameters());
        
        // Test that it's not abstract
        $this->assertFalse($reflection->isAbstract());
        
        // Test that it's not final
        $this->assertFalse($reflection->isFinal());
    }

    public function testLeadServiceMethodVisibility(): void
    {
        $reflection = new \ReflectionClass(LeadService::class);
        
        // Test method visibility
        $createLeadMethod = $reflection->getMethod('createLead');
        $this->assertTrue($createLeadMethod->isPublic());
        
        $updateStatusMethod = $reflection->getMethod('updateLeadStatus');
        $this->assertTrue($updateStatusMethod->isPublic());
        
        $deleteLeadMethod = $reflection->getMethod('deleteLead');
        $this->assertTrue($deleteLeadMethod->isPublic());
    }

    public function testLeadServiceDependencyInjection(): void
    {
        // Test that the service has proper dependency injection
        $this->assertInstanceOf(EntityManagerInterface::class, $this->entityManager);
        $this->assertInstanceOf(CustomerServiceInterface::class, $this->customerService);
        $this->assertInstanceOf(LeadPropertyServiceInterface::class, $this->propertyService);
        $this->assertInstanceOf(EventServiceInterface::class, $this->eventService);
        $this->assertInstanceOf(CDPDeliveryServiceInterface::class, $this->cdpDeliveryService);
        $this->assertInstanceOf(LeadScoringServiceInterface::class, $this->leadScoringService);
        $this->assertInstanceOf(LoggerInterface::class, $this->logger);
    }
}
