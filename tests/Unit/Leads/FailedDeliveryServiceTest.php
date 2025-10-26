<?php

declare(strict_types=1);

namespace Tests\Unit\Leads;

use App\Leads\FailedDeliveryService;
use App\Model\Customer;
use App\Model\FailedDelivery;
use App\Model\Lead;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * FailedDeliveryService Test
 * Tests failed delivery management
 */
class FailedDeliveryServiceTest extends TestCase
{
    private FailedDeliveryService $service;
    private MockObject|EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->service = new FailedDeliveryService($this->entityManager);
    }

    public function testCreateFailedDelivery(): void
    {
        $customer = new Customer('test@example.com', '+48123456789');
        $lead = new Lead('test-uuid', $customer, 'morizon');
        
        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(FailedDelivery::class));
        
        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $failedDelivery = $this->service->createFailedDelivery(
            $lead,
            'SalesManago',
            'Connection timeout',
            'TIMEOUT'
        );

        $this->assertInstanceOf(FailedDelivery::class, $failedDelivery);
        $this->assertEquals('SalesManago', $failedDelivery->getCdpSystemName());
        $this->assertEquals('Connection timeout', $failedDelivery->getErrorMessage());
        $this->assertEquals('TIMEOUT', $failedDelivery->getErrorCode());
    }

    public function testGetPendingDeliveries(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        
        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(FailedDelivery::class)
            ->willReturn($repository);
        
        $repository
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $failedDelivery = $this->createMock(FailedDelivery::class);
        $failedDelivery->method('getLead')->willReturn($this->createMock(Lead::class));
        
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query
            ->expects($this->once())
            ->method('getResult')
            ->willReturn([$failedDelivery]);
        
        $queryBuilder
            ->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);
        
        $queryBuilder
            ->method('where')
            ->willReturnSelf();
        
        $queryBuilder
            ->method('andWhere')
            ->willReturnSelf();
        
        $queryBuilder
            ->method('setParameter')
            ->willReturnSelf();
        
        $queryBuilder
            ->method('setMaxResults')
            ->willReturnSelf();
        
        $queryBuilder
            ->method('orderBy')
            ->willReturnSelf();

        $result = $this->service->getPendingDeliveries(100);

        $this->assertIsArray($result);
    }

    public function testMarkAsResolved(): void
    {
        $failedDelivery = $this->createMock(FailedDelivery::class);
        
        $failedDelivery
            ->expects($this->once())
            ->method('setStatus')
            ->with('resolved');
        
        $failedDelivery
            ->expects($this->once())
            ->method('setResolvedAt')
            ->with($this->isInstanceOf(\DateTime::class));
        
        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->markAsResolved($failedDelivery);
    }

    public function testMarkAsFailed(): void
    {
        $failedDelivery = $this->createMock(FailedDelivery::class);
        
        $failedDelivery
            ->expects($this->once())
            ->method('setStatus')
            ->with('failed');
        
        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->markAsFailed($failedDelivery);
    }

    public function testUpdateRetryInfo(): void
    {
        $failedDelivery = $this->createMock(FailedDelivery::class);
        $nextRetry = new \DateTime('+5 minutes');
        
        $failedDelivery
            ->expects($this->once())
            ->method('setRetryCount')
            ->with(3);
        
        $failedDelivery
            ->expects($this->once())
            ->method('setNextRetryAt')
            ->with($nextRetry);
        
        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->service->updateRetryInfo($failedDelivery, 3, $nextRetry);
    }
}

