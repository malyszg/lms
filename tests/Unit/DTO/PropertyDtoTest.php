<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\PropertyDto;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PropertyDto
 */
class PropertyDtoTest extends TestCase
{
    public function testPropertyDtoConstructor(): void
    {
        $dto = new PropertyDto(
            propertyId: 'prop-123',
            developmentId: 'dev-456',
            partnerId: 'partner-789',
            propertyType: 'apartment',
            price: 500000.0,
            location: 'Centrum miasta',
            city: 'Warszawa'
        );
        
        $this->assertEquals('prop-123', $dto->propertyId);
        $this->assertEquals('dev-456', $dto->developmentId);
        $this->assertEquals('partner-789', $dto->partnerId);
        $this->assertEquals('apartment', $dto->propertyType);
        $this->assertEquals(500000.0, $dto->price);
        $this->assertEquals('Centrum miasta', $dto->location);
        $this->assertEquals('Warszawa', $dto->city);
    }

    public function testPropertyDtoWithNullValues(): void
    {
        $dto = new PropertyDto(
            propertyId: null,
            developmentId: null,
            partnerId: null,
            propertyType: null,
            price: null,
            location: null,
            city: null
        );
        
        $this->assertNull($dto->propertyId);
        $this->assertNull($dto->developmentId);
        $this->assertNull($dto->partnerId);
        $this->assertNull($dto->propertyType);
        $this->assertNull($dto->price);
        $this->assertNull($dto->location);
        $this->assertNull($dto->city);
    }

    public function testPropertyDtoWithPartialData(): void
    {
        $dto = new PropertyDto(
            propertyId: 'prop-123',
            developmentId: null,
            partnerId: 'partner-789',
            propertyType: null,
            price: 300000.0,
            location: 'Partial Location',
            city: null
        );
        
        $this->assertEquals('prop-123', $dto->propertyId);
        $this->assertNull($dto->developmentId);
        $this->assertEquals('partner-789', $dto->partnerId);
        $this->assertNull($dto->propertyType);
        $this->assertEquals(300000.0, $dto->price);
        $this->assertEquals('Partial Location', $dto->location);
        $this->assertNull($dto->city);
    }

    public function testPropertyDtoWithZeroPrice(): void
    {
        $dto = new PropertyDto(
            propertyId: 'prop-123',
            developmentId: 'dev-456',
            partnerId: 'partner-789',
            propertyType: 'apartment',
            price: 0.0,
            location: 'Centrum miasta',
            city: 'Warszawa'
        );
        
        $this->assertEquals(0.0, $dto->price);
    }

    public function testPropertyDtoWithDifferentPropertyTypes(): void
    {
        $propertyTypes = ['apartment', 'house', 'commercial', 'land', 'office'];
        
        foreach ($propertyTypes as $type) {
            $dto = new PropertyDto(
                propertyId: 'prop-123',
                developmentId: 'dev-456',
                partnerId: 'partner-789',
                propertyType: $type,
                price: 500000.0,
                location: 'Test Location',
                city: 'Test City'
            );
            
            $this->assertEquals($type, $dto->propertyType);
        }
    }
}
