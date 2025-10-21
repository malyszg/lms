<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\FiltersDto;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Unit test for FiltersDto
 */
class FiltersDtoTest extends TestCase
{
    public function testFromRequestWithAllParameters(): void
    {
        // Arrange
        $request = new Request([
            'status' => 'new',
            'application_name' => 'morizon',
            'customer_email' => 'test@example.com',
            'customer_phone' => '+48123456789',
            'created_from' => '2025-01-01',
            'created_to' => '2025-12-31',
            'sort' => 'status',
            'order' => 'asc'
        ]);
        
        // Act
        $filters = FiltersDto::fromRequest($request);
        
        // Assert
        $this->assertEquals('new', $filters->status);
        $this->assertEquals('morizon', $filters->applicationName);
        $this->assertEquals('test@example.com', $filters->customerEmail);
        $this->assertEquals('+48123456789', $filters->customerPhone);
        $this->assertInstanceOf(\DateTimeInterface::class, $filters->createdFrom);
        $this->assertInstanceOf(\DateTimeInterface::class, $filters->createdTo);
        $this->assertEquals('status', $filters->sort);
        $this->assertEquals('asc', $filters->order);
    }
    
    public function testFromRequestWithDefaultValues(): void
    {
        // Arrange
        $request = new Request([]);
        
        // Act
        $filters = FiltersDto::fromRequest($request);
        
        // Assert
        $this->assertNull($filters->status);
        $this->assertNull($filters->applicationName);
        $this->assertNull($filters->customerEmail);
        $this->assertNull($filters->customerPhone);
        $this->assertNull($filters->createdFrom);
        $this->assertNull($filters->createdTo);
        $this->assertEquals('created_at', $filters->sort);
        $this->assertEquals('desc', $filters->order);
    }
    
    public function testToQueryParamsWithAllValues(): void
    {
        // Arrange
        $filters = new FiltersDto(
            status: 'contacted',
            applicationName: 'gratka',
            customerEmail: 'jan@example.com',
            customerPhone: '+48999888777',
            createdFrom: new \DateTime('2025-06-01'),
            createdTo: new \DateTime('2025-06-30'),
            sort: 'application_name',
            order: 'asc'
        );
        
        // Act
        $params = $filters->toQueryParams();
        
        // Assert
        $this->assertArrayHasKey('status', $params);
        $this->assertEquals('contacted', $params['status']);
        $this->assertArrayHasKey('application_name', $params);
        $this->assertEquals('gratka', $params['application_name']);
        $this->assertArrayHasKey('customer_email', $params);
        $this->assertEquals('jan@example.com', $params['customer_email']);
        $this->assertArrayHasKey('customer_phone', $params);
        $this->assertEquals('+48999888777', $params['customer_phone']);
        $this->assertArrayHasKey('created_from', $params);
        $this->assertEquals('2025-06-01', $params['created_from']);
        $this->assertArrayHasKey('created_to', $params);
        $this->assertEquals('2025-06-30', $params['created_to']);
        $this->assertArrayHasKey('sort', $params);
        $this->assertEquals('application_name', $params['sort']);
        $this->assertArrayHasKey('order', $params);
        $this->assertEquals('asc', $params['order']);
    }
    
    public function testToQueryParamsWithDefaultValues(): void
    {
        // Arrange
        $filters = new FiltersDto();
        
        // Act
        $params = $filters->toQueryParams();
        
        // Assert - default values should not be included
        $this->assertArrayNotHasKey('status', $params);
        $this->assertArrayNotHasKey('application_name', $params);
        $this->assertArrayNotHasKey('customer_email', $params);
        $this->assertArrayNotHasKey('customer_phone', $params);
        $this->assertArrayNotHasKey('created_from', $params);
        $this->assertArrayNotHasKey('created_to', $params);
        $this->assertArrayNotHasKey('sort', $params); // default is created_at
        $this->assertArrayNotHasKey('order', $params); // default is desc
    }
    
    public function testFromRequestWithInvalidDateFormats(): void
    {
        // Arrange
        $request = new Request([
            'created_from' => 'invalid-date',
            'created_to' => 'also-invalid'
        ]);
        
        // Act
        $filters = FiltersDto::fromRequest($request);
        
        // Assert - invalid dates should be ignored (null)
        $this->assertNull($filters->createdFrom);
        $this->assertNull($filters->createdTo);
    }
}



























