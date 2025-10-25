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
     * Test that getLeadsList method exists and has correct signature
     */
    public function testGetLeadsListMethodExists(): void
    {
        // Verify that getLeadsList method exists
        $this->assertTrue(
            method_exists(LeadViewService::class, 'getLeadsList'),
            'LeadViewService should have getLeadsList method'
        );
        
        // Verify method signature
        $reflection = new \ReflectionMethod(LeadViewService::class, 'getLeadsList');
        $this->assertEquals(3, $reflection->getNumberOfParameters(), 'getLeadsList should have 3 parameters');
        $this->assertEquals('App\DTO\LeadsListApiResponse', $reflection->getReturnType()->getName(), 'getLeadsList should return LeadsListApiResponse');
    }
    
    /**
     * Test that countNewLeadsSince method exists and has correct signature
     */
    public function testCountNewLeadsSinceMethodExists(): void
    {
        // Verify that countNewLeadsSince method exists
        $this->assertTrue(
            method_exists(LeadViewService::class, 'countNewLeadsSince'),
            'LeadViewService should have countNewLeadsSince method'
        );
        
        // Verify method signature
        $reflection = new \ReflectionMethod(LeadViewService::class, 'countNewLeadsSince');
        $this->assertEquals(1, $reflection->getNumberOfParameters(), 'countNewLeadsSince should have 1 parameter');
        $this->assertEquals('int', $reflection->getReturnType()->getName(), 'countNewLeadsSince should return int');
    }
}

