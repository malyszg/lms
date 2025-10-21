<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\DTO\FiltersDto;
use App\Service\LeadViewService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for LeadViewService
 * 
 * Note: This service uses Doctrine EntityManager directly.
 * For full testing, integration tests would be more appropriate.
 */
class LeadViewServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private LeadViewService $service;
    
    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->service = new LeadViewService($this->entityManager);
    }
    
    public function testServiceIsInstantiable(): void
    {
        // Assert
        $this->assertInstanceOf(LeadViewService::class, $this->service);
    }
    
    public function testFiltersDtoCreatesCorrectly(): void
    {
        // Arrange & Act
        $filters = new FiltersDto(
            status: 'new',
            applicationName: 'morizon',
            sort: 'created_at',
            order: 'desc'
        );
        
        // Assert
        $this->assertEquals('new', $filters->status);
        $this->assertEquals('morizon', $filters->applicationName);
        $this->assertEquals('created_at', $filters->sort);
        $this->assertEquals('desc', $filters->order);
    }
    
    /**
     * Note: Full testing of getLeadsList would require database integration
     * This is marked as skipped for unit tests
     */
    public function testGetLeadsListRequiresIntegrationTest(): void
    {
        $this->markTestSkipped(
            'getLeadsList method requires database integration testing. ' .
            'See tests/Functional/Service/LeadViewServiceIntegrationTest.php'
        );
    }
    
    /**
     * Note: Full testing of countNewLeadsSince would require database integration
     * This is marked as skipped for unit tests
     */
    public function testCountNewLeadsSinceRequiresIntegrationTest(): void
    {
        $this->markTestSkipped(
            'countNewLeadsSince method requires database integration testing. ' .
            'See tests/Functional/Service/LeadViewServiceIntegrationTest.php'
        );
    }
}

