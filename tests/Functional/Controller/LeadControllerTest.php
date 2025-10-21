<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for LeadController
 * Tests the full API endpoint including database interactions
 */
class LeadControllerTest extends WebTestCase
{
    protected static function createClient(array $options = [], array $server = []): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $options = array_merge(['environment' => 'test', 'debug' => true], $options);
        return parent::createClient($options, $server);
    }

    // No setUp() needed - tests use unique UUIDs that don't conflict with production data
    // All test UUIDs follow pattern: a1b2c3d4-e5f6-41d4-a716-44665544XXXX
    // Production should never use UUIDs starting with a1b2c3d4-e5f6

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
     * Deletes only test rows in reverse dependency order to preserve user data
     */
    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        
        $kernel = self::bootKernel(['environment' => 'test', 'debug' => true]);
        $connection = $kernel->getContainer()->get('doctrine')->getConnection();
        
        // Delete in REVERSE DEPENDENCY ORDER (children first, parents last)
        
        // 1a. Delete Lead-related events
        $connection->executeStatement(
            "DELETE FROM events WHERE entity_type = 'Lead' AND entity_id IN (
                SELECT id FROM leads WHERE lead_uuid LIKE 'a1b2c3d4-e5f6%'
            )"
        );
        
        // 1b. Delete api_request events (created during test HTTP requests)
        // Since this is test database, we can safely delete all api_request events
        $connection->executeStatement(
            "DELETE FROM events WHERE event_type = 'api_request'"
        );
        
        // 2. Delete child records: failed_deliveries (has lead_id column)
        $connection->executeStatement(
            "DELETE FROM failed_deliveries WHERE lead_id IN (
                SELECT id FROM leads WHERE lead_uuid LIKE 'a1b2c3d4-e5f6%'
            )"
        );
        
        // 3. Delete parent records: leads
        $connection->executeStatement(
            "DELETE FROM leads WHERE lead_uuid LIKE 'a1b2c3d4-e5f6%'"
        );
        
        // 4. Delete related customers (only test customers with identifiable email patterns)
        $connection->executeStatement(
            "DELETE FROM customers WHERE 
                email LIKE '%@test.example.com' 
                OR email LIKE 'functional-test%' 
                OR email LIKE 'duplicate-test%'
                OR email LIKE 'dedupe-test%'"
        );
        
        // Close connection and shutdown kernel
        $connection->close();
        self::ensureKernelShutdown();
    }

    public function testCreateLeadReturns201OnSuccess(): void
    {
        $client = static::createClient();

        $requestData = [
            'lead_uuid' => 'a1b2c3d4-e5f6-41d4-a716-446655440001',
            'application_name' => 'morizon',
            'customer' => [
                'email' => 'functional-test@example.com',
                'phone' => '+48111222333',
                'first_name' => 'Functional',
                'last_name' => 'Test',
            ],
            'property' => [
                'property_id' => 'PROP-TEST-001',
                'price' => 500000.00,
                'city' => 'Warszawa',
            ],
        ];

        $client->request(
            'POST',
            '/api/leads',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($requestData)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $this->assertResponseHeaderSame('Content-Type', 'application/json');

        $responseData = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('id', $responseData);
        $this->assertArrayHasKey('leadUuid', $responseData);
        $this->assertArrayHasKey('status', $responseData);
        $this->assertArrayHasKey('customerId', $responseData);
        $this->assertArrayHasKey('applicationName', $responseData);
        $this->assertArrayHasKey('createdAt', $responseData);
        $this->assertArrayHasKey('cdpDeliveryStatus', $responseData);

        $this->assertEquals('a1b2c3d4-e5f6-41d4-a716-446655440001', $responseData['leadUuid']);
        $this->assertEquals('morizon', $responseData['applicationName']);
        $this->assertEquals('new', $responseData['status']);
        $this->assertEquals('pending', $responseData['cdpDeliveryStatus']);
    }

    public function testCreateLeadReturns409OnDuplicateUuid(): void
    {
        $client = static::createClient();

        $requestData = [
            'lead_uuid' => 'a1b2c3d4-e5f6-41d4-a716-446655440002',
            'application_name' => 'gratka',
            'customer' => [
                'email' => 'duplicate-test@example.com',
                'phone' => '+48222333444',
            ],
            'property' => [],
        ];

        // First request - should succeed
        $client->request(
            'POST',
            '/api/leads',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($requestData)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);

        // Second request with same UUID - should fail with 409
        $client->request(
            'POST',
            '/api/leads',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($requestData)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CONFLICT);

        $responseData = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $responseData);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals('Conflict', $responseData['error']);
        $this->assertStringContainsString('already exists', $responseData['message']);
    }

    public function testCreateLeadReturns400OnInvalidData(): void
    {
        $client = static::createClient();

        $requestData = [
            'lead_uuid' => 'invalid-uuid',
            'application_name' => 'unknown-app',
            'customer' => [
                'email' => 'invalid-email',
                'phone' => '123',
            ],
            'property' => [],
        ];

        $client->request(
            'POST',
            '/api/leads',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($requestData)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $responseData = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $responseData);
        $this->assertArrayHasKey('details', $responseData);
        $this->assertEquals('Validation Error', $responseData['error']);

        $details = $responseData['details'];
        $this->assertArrayHasKey('lead_uuid', $details);
        $this->assertArrayHasKey('application_name', $details);
        $this->assertArrayHasKey('customer.email', $details);
        $this->assertArrayHasKey('customer.phone', $details);
    }

    public function testCreateLeadReturns400OnMissingRequiredFields(): void
    {
        $client = static::createClient();

        $requestData = [
            'lead_uuid' => 'a1b2c3d4-e5f6-41d4-a716-446655440003',
            // missing application_name
            'customer' => [
                // missing email and phone
            ],
            'property' => [],
        ];

        $client->request(
            'POST',
            '/api/leads',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($requestData)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $responseData = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals('Validation Error', $responseData['error']);
    }

    public function testCreateLeadReturns400OnInvalidJson(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/leads',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            'invalid json {'
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $responseData = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $responseData);
        $this->assertEquals('Validation Error', $responseData['error']);
    }

    public function testCreateLeadReturns400OnInvalidContentType(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/leads',
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            'some data'
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $responseData = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals('Validation Error', $responseData['error']);
        $this->assertArrayHasKey('content_type', $responseData['details']);
    }

    public function testCreateLeadDeduplicatesCustomerByEmailAndPhone(): void
    {
        $client = static::createClient();

        $customer = [
            'email' => 'dedupe-test@example.com',
            'phone' => '+48333444555',
            'first_name' => 'Dedupe',
            'last_name' => 'Test',
        ];

        // First lead
        $firstRequest = [
            'lead_uuid' => 'a1b2c3d4-e5f6-41d4-a716-446655440004',
            'application_name' => 'morizon',
            'customer' => $customer,
            'property' => ['city' => 'Kraków'],
        ];

        $client->request(
            'POST',
            '/api/leads',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($firstRequest)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $firstResponse = json_decode($client->getResponse()->getContent(), true);
        $firstCustomerId = $firstResponse['customerId'];

        // Second lead with same customer (should reuse customer)
        $secondRequest = [
            'lead_uuid' => 'a1b2c3d4-e5f6-41d4-a716-446655440005',
            'application_name' => 'gratka',
            'customer' => $customer,
            'property' => ['city' => 'Gdańsk'],
        ];

        $client->request(
            'POST',
            '/api/leads',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($secondRequest)
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $secondResponse = json_decode($client->getResponse()->getContent(), true);
        $secondCustomerId = $secondResponse['customerId'];

        // Both leads should have the same customer_id (deduplication worked)
        $this->assertEquals(
            $firstCustomerId,
            $secondCustomerId,
            'Customer deduplication should reuse the same customer'
        );
    }
}



















