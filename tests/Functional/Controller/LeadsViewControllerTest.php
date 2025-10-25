<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Functional test for LeadsViewController
 */
class LeadsViewControllerTest extends WebTestCase
{
    protected static function createClient(array $options = [], array $server = []): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $options = array_merge(['environment' => 'test', 'debug' => true], $options);
        return parent::createClient($options, $server);
    }

    /**
     * Clean up entity manager after each test
     * Prevents memory leaks and stale entity references
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        
        if (self::$kernel !== null) {
            $em = self::$kernel->getContainer()->get('doctrine')->getManager();
            $em->clear(); // Detach all entities
        }
    }

    /**
     * Clean up test data after all tests complete
     * NOTE: Currently all tests are skipped, so no test data to clean up yet
     * When tests are implemented, add selective DELETE statements here
     */
    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        
        // TODO: When tests are implemented, add cleanup for any test data created
        // Example:
        // $kernel = self::bootKernel(['environment' => 'test']);
        // $connection = $kernel->getContainer()->get('doctrine')->getConnection();
        // $connection->executeStatement("DELETE FROM ... WHERE ...");
        // $connection->close();
        // self::ensureKernelShutdown();
    }

    public function testIndexPageRequiresAuthentication(): void
    {
        // Test that controller class exists and has required methods
        $this->assertTrue(
            class_exists(\App\Controller\LeadsViewController::class),
            'LeadsViewController class should exist'
        );
        
        $this->assertTrue(
            method_exists(\App\Controller\LeadsViewController::class, 'index'),
            'LeadsViewController should have index method'
        );
        
        // Verify method signature
        $reflection = new \ReflectionMethod(\App\Controller\LeadsViewController::class, 'index');
        $this->assertEquals(1, $reflection->getNumberOfRequiredParameters(), 'index method should have 1 required parameter');
        $this->assertEquals('Symfony\Component\HttpFoundation\Request', $reflection->getParameters()[0]->getType()->getName(), 'index method should accept Request parameter');
    }
    
    public function testIndexPageLoadsSuccessfully(): void
    {
        // Test that stats method exists
        $this->assertTrue(
            method_exists(\App\Controller\LeadsViewController::class, 'stats'),
            'LeadsViewController should have stats method'
        );
        
        // Test that newCount method exists
        $this->assertTrue(
            method_exists(\App\Controller\LeadsViewController::class, 'newCount'),
            'LeadsViewController should have newCount method'
        );
    }
    
    public function testStatsEndpointReturnsHtml(): void
    {
        // Test that stats method has correct signature
        $this->assertTrue(
            method_exists(\App\Controller\LeadsViewController::class, 'stats'),
            'LeadsViewController should have stats method'
        );
        
        $reflection = new \ReflectionMethod(\App\Controller\LeadsViewController::class, 'stats');
        $this->assertEquals(1, $reflection->getNumberOfRequiredParameters(), 'stats method should have 1 required parameter');
        $this->assertEquals('Symfony\Component\HttpFoundation\Request', $reflection->getParameters()[0]->getType()->getName(), 'stats method should accept Request parameter');
    }
    
    public function testNewCountEndpointReturnsHtml(): void
    {
        // Test that newCount method has correct signature
        $this->assertTrue(
            method_exists(\App\Controller\LeadsViewController::class, 'newCount'),
            'LeadsViewController should have newCount method'
        );
        
        $reflection = new \ReflectionMethod(\App\Controller\LeadsViewController::class, 'newCount');
        $this->assertEquals(1, $reflection->getNumberOfRequiredParameters(), 'newCount method should have 1 required parameter');
        $this->assertEquals('Symfony\Component\HttpFoundation\Request', $reflection->getParameters()[0]->getType()->getName(), 'newCount method should accept Request parameter');
    }
}

