<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\CreateCustomerDto;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CreateCustomerDto
 */
class CreateCustomerDtoTest extends TestCase
{
    public function testCreateCustomerDtoConstructor(): void
    {
        $dto = new CreateCustomerDto(
            email: 'test@example.com',
            phone: '+48123456789',
            firstName: 'Test',
            lastName: 'User'
        );
        
        $this->assertEquals('test@example.com', $dto->email);
        $this->assertEquals('+48123456789', $dto->phone);
        $this->assertEquals('Test', $dto->firstName);
        $this->assertEquals('User', $dto->lastName);
    }

    public function testCreateCustomerDtoWithEmptyFields(): void
    {
        $dto = new CreateCustomerDto(
            email: 'minimal@example.com',
            phone: '+48111111111',
            firstName: '',
            lastName: ''
        );
        
        $this->assertEquals('minimal@example.com', $dto->email);
        $this->assertEquals('+48111111111', $dto->phone);
        $this->assertEquals('', $dto->firstName);
        $this->assertEquals('', $dto->lastName);
    }

    public function testCreateCustomerDtoWithSpecialCharacters(): void
    {
        $dto = new CreateCustomerDto(
            email: 'test+tag@example.com',
            phone: '+48 123 456 789',
            firstName: 'José',
            lastName: 'García-López'
        );
        
        $this->assertEquals('test+tag@example.com', $dto->email);
        $this->assertEquals('+48 123 456 789', $dto->phone);
        $this->assertEquals('José', $dto->firstName);
        $this->assertEquals('García-López', $dto->lastName);
    }

    public function testCreateCustomerDtoWithLongNames(): void
    {
        $dto = new CreateCustomerDto(
            email: 'verylongemailaddress@verylongdomainname.com',
            phone: '+481234567890',
            firstName: 'VeryLongFirstNameThatExceedsNormalLength',
            lastName: 'VeryLongLastNameThatExceedsNormalLength'
        );
        
        $this->assertEquals('verylongemailaddress@verylongdomainname.com', $dto->email);
        $this->assertEquals('+481234567890', $dto->phone);
        $this->assertEquals('VeryLongFirstNameThatExceedsNormalLength', $dto->firstName);
        $this->assertEquals('VeryLongLastNameThatExceedsNormalLength', $dto->lastName);
    }

    public function testCreateCustomerDtoWithDifferentPhoneFormats(): void
    {
        $phoneFormats = [
            '+48123456789',
            '123456789',
            '+48 123 456 789',
            '123-456-789',
            '123.456.789'
        ];
        
        foreach ($phoneFormats as $phone) {
            $dto = new CreateCustomerDto(
                email: 'test@example.com',
                phone: $phone,
                firstName: 'Test',
                lastName: 'User'
            );
            
            $this->assertEquals($phone, $dto->phone);
        }
    }
}
