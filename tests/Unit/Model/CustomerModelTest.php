<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model;

use App\Model\Customer;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Customer model
 */
class CustomerModelTest extends TestCase
{
    public function testCustomerConstructor(): void
    {
        $customer = new Customer(
            email: 'test@example.com',
            phone: '+48123456789',
            firstName: 'Test',
            lastName: 'User'
        );
        
        $this->assertEquals('test@example.com', $customer->getEmail());
        $this->assertEquals('+48123456789', $customer->getPhone());
        $this->assertEquals('Test', $customer->getFirstName());
        $this->assertEquals('User', $customer->getLastName());
    }

    public function testCustomerEmailUpdate(): void
    {
        $customer = new Customer(
            email: 'original@example.com',
            phone: '+48123456789',
            firstName: 'Test',
            lastName: 'User'
        );
        
        $this->assertEquals('original@example.com', $customer->getEmail());
        
        $customer->setEmail('updated@example.com');
        $this->assertEquals('updated@example.com', $customer->getEmail());
    }

    public function testCustomerPhoneUpdate(): void
    {
        $customer = new Customer(
            email: 'test@example.com',
            phone: '+48123456789',
            firstName: 'Test',
            lastName: 'User'
        );
        
        $this->assertEquals('+48123456789', $customer->getPhone());
        
        $customer->setPhone('+48987654321');
        $this->assertEquals('+48987654321', $customer->getPhone());
    }

    public function testCustomerNameUpdate(): void
    {
        $customer = new Customer(
            email: 'test@example.com',
            phone: '+48123456789',
            firstName: 'Original',
            lastName: 'Name'
        );
        
        $this->assertEquals('Original', $customer->getFirstName());
        $this->assertEquals('Name', $customer->getLastName());
        
        $customer->setFirstName('Updated');
        $customer->setLastName('Surname');
        
        $this->assertEquals('Updated', $customer->getFirstName());
        $this->assertEquals('Surname', $customer->getLastName());
    }

    public function testCustomerWithEmptyOptionalFields(): void
    {
        $customer = new Customer(
            email: 'minimal@example.com',
            phone: '+48111111111',
            firstName: '',
            lastName: ''
        );
        
        $this->assertEquals('minimal@example.com', $customer->getEmail());
        $this->assertEquals('+48111111111', $customer->getPhone());
        $this->assertEquals('', $customer->getFirstName());
        $this->assertEquals('', $customer->getLastName());
    }
}
