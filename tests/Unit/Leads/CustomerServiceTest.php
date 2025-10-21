<?php

declare(strict_types=1);

namespace App\Tests\Unit\Leads;

use App\DTO\CreateCustomerDto;
use App\Leads\CustomerService;
use App\Model\Customer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Query;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CustomerService
 */
class CustomerServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private CustomerService $customerService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->customerService = new CustomerService($this->entityManager);
    }

    public function testFindOrCreateCustomerCreatesNewCustomerWhenNotExists(): void
    {
        $customerDto = new CreateCustomerDto(
            email: 'new@example.com',
            phone: '+48123456789',
            firstName: 'John',
            lastName: 'Doe'
        );

        // Mock repository query to return null (customer doesn't exist)
        $query = $this->createMock(Query::class);
        $query->expects($this->once())
            ->method('setLockMode')
            ->willReturnSelf();
        $query->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn(null);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())
            ->method('where')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('andWhere')
            ->willReturnSelf();
        $queryBuilder->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('setMaxResults')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(Customer::class)
            ->willReturn($repository);

        $this->entityManager->expects($this->once())
            ->method('persist');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $customer = $this->customerService->findOrCreateCustomer($customerDto);

        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertEquals('new@example.com', $customer->getEmail());
        $this->assertEquals('+48123456789', $customer->getPhone());
        $this->assertEquals('John', $customer->getFirstName());
        $this->assertEquals('Doe', $customer->getLastName());
    }

    public function testFindOrCreateCustomerReturnsExistingCustomerWhenExists(): void
    {
        $customerDto = new CreateCustomerDto(
            email: 'existing@example.com',
            phone: '+48987654321'
        );

        $existingCustomer = new Customer(
            email: 'existing@example.com',
            phone: '+48987654321',
            firstName: 'Jane',
            lastName: 'Smith'
        );

        // Mock repository query to return existing customer
        $query = $this->createMock(Query::class);
        $query->expects($this->once())
            ->method('setLockMode')
            ->willReturnSelf();
        $query->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn($existingCustomer);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())
            ->method('where')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('andWhere')
            ->willReturnSelf();
        $queryBuilder->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('setMaxResults')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(Customer::class)
            ->willReturn($repository);

        // Should NOT call persist or flush for existing customer
        $this->entityManager->expects($this->never())
            ->method('persist');

        $this->entityManager->expects($this->never())
            ->method('flush');

        $customer = $this->customerService->findOrCreateCustomer($customerDto);

        $this->assertSame($existingCustomer, $customer);
        $this->assertEquals('existing@example.com', $customer->getEmail());
    }

    public function testFindByEmailAndPhoneReturnsCustomerWhenFound(): void
    {
        $email = 'test@example.com';
        $phone = '+48123456789';

        $foundCustomer = new Customer(
            email: $email,
            phone: $phone
        );

        $query = $this->createMock(Query::class);
        $query->expects($this->once())
            ->method('setLockMode')
            ->willReturnSelf();
        $query->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn($foundCustomer);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())
            ->method('where')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('andWhere')
            ->willReturnSelf();
        $queryBuilder->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('setMaxResults')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(Customer::class)
            ->willReturn($repository);

        $result = $this->customerService->findByEmailAndPhone($email, $phone);

        $this->assertSame($foundCustomer, $result);
    }

    public function testFindByEmailAndPhoneReturnsNullWhenNotFound(): void
    {
        $query = $this->createMock(Query::class);
        $query->expects($this->once())
            ->method('setLockMode')
            ->willReturnSelf();
        $query->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn(null);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())
            ->method('where')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('andWhere')
            ->willReturnSelf();
        $queryBuilder->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('setMaxResults')
            ->willReturnSelf();
        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query);

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($queryBuilder);

        $this->entityManager->expects($this->once())
            ->method('getRepository')
            ->with(Customer::class)
            ->willReturn($repository);

        $result = $this->customerService->findByEmailAndPhone('notfound@example.com', '+48000000000');

        $this->assertNull($result);
    }
}


