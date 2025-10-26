<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model;

use App\Model\User;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for User model
 */
class UserModelTest extends TestCase
{
    public function testUserConstructor(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');
        $user->setPassword('password123');
        
        $this->assertEquals('test@example.com', $user->getEmail());
        $this->assertEquals('testuser', $user->getUsername());
        $this->assertEquals('password123', $user->getPassword());
        $this->assertTrue($user->isActive());
        $this->assertEquals(['ROLE_USER'], $user->getRoles());
    }

    public function testUserRolesManagement(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');
        $user->setPassword('password123');
        
        // Test default role
        $this->assertEquals(['ROLE_USER'], $user->getRoles());
        
        // Test adding roles
        $user->addRole('ROLE_CALL_CENTER');
        $this->assertContains('ROLE_CALL_CENTER', $user->getRoles());
        $this->assertContains('ROLE_USER', $user->getRoles());
        
        $user->addRole('ROLE_BOK');
        $this->assertContains('ROLE_BOK', $user->getRoles());
        $this->assertContains('ROLE_CALL_CENTER', $user->getRoles());
        $this->assertContains('ROLE_USER', $user->getRoles());
        
        // Test removing roles
        $user->removeRole('ROLE_CALL_CENTER');
        $this->assertNotContains('ROLE_CALL_CENTER', $user->getRoles());
        $this->assertContains('ROLE_BOK', $user->getRoles());
        $this->assertContains('ROLE_USER', $user->getRoles());
    }

    public function testUserSetRoles(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');
        $user->setPassword('password123');
        
        // Test setting roles array
        $user->setRoles(['ROLE_CALL_CENTER', 'ROLE_BOK']);
        $roles = $user->getRoles();
        
        $this->assertContains('ROLE_CALL_CENTER', $roles);
        $this->assertContains('ROLE_BOK', $roles);
        $this->assertContains('ROLE_USER', $roles); // ROLE_USER should always be present
    }

    public function testUserFullName(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');
        $user->setPassword('password123');
        
        // Test with first and last name
        $user->setFirstName('John');
        $user->setLastName('Doe');
        $this->assertEquals('John Doe', $user->getFullName());
        
        // Test with only first name
        $user->setFirstName('John');
        $user->setLastName(null);
        $this->assertEquals('testuser', $user->getFullName());
        
        // Test with only last name
        $user->setFirstName(null);
        $user->setLastName('Doe');
        $this->assertEquals('testuser', $user->getFullName());
        
        // Test with no names
        $user->setFirstName(null);
        $user->setLastName(null);
        $this->assertEquals('testuser', $user->getFullName());
    }

    public function testUserActiveStatus(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');
        $user->setPassword('password123');
        
        // Test default active status
        $this->assertTrue($user->isActive());
        
        // Test deactivating user
        $user->setIsActive(false);
        $this->assertFalse($user->isActive());
        
        // Test reactivating user
        $user->setIsActive(true);
        $this->assertTrue($user->isActive());
    }

    public function testUserTimestamps(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');
        $user->setPassword('password123');
        
        // Test that timestamps are set
        $this->assertInstanceOf(\DateTimeInterface::class, $user->getCreatedAt());
        $this->assertInstanceOf(\DateTimeInterface::class, $user->getUpdatedAt());
        
        // Test updating timestamp
        $originalUpdatedAt = $user->getUpdatedAt();
        sleep(1); // Ensure time difference
        $user->setUpdatedAt(new \DateTime());
        
        $this->assertNotEquals($originalUpdatedAt, $user->getUpdatedAt());
    }

    public function testUserLastLogin(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');
        $user->setPassword('password123');
        
        // Test initial last login is null
        $this->assertNull($user->getLastLoginAt());
        
        // Test setting last login
        $loginTime = new \DateTime();
        $user->setLastLoginAt($loginTime);
        $this->assertEquals($loginTime, $user->getLastLoginAt());
        
        // Test updating last login
        $newLoginTime = new \DateTime();
        $user->setLastLoginAt($newLoginTime);
        $this->assertEquals($newLoginTime, $user->getLastLoginAt());
    }

    public function testUserGetUserIdentifier(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setUsername('testuser');
        $user->setPassword('password123');
        
        // Test that getUserIdentifier returns email
        $this->assertEquals('test@example.com', $user->getUserIdentifier());
        
        // Test with different email
        $user->setEmail('different@example.com');
        $this->assertEquals('different@example.com', $user->getUserIdentifier());
    }
}
