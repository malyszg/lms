<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Model\Lead;
use App\Model\Customer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Integration tests for lead deletion functionality
 * Tests the complete flow from UI to database
 */
class LeadDeletionIntegrationTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        
        $client = static::createClient();
        $container = $client->getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
    }

    public static function tearDownAfterClass(): void
    {
        // Clean up test data
        $client = static::createClient();
        $container = $client->getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);
        
        $entityManager->createQueryBuilder()
            ->delete(Lead::class, 'l')
            ->where('l.leadUuid LIKE :testUuid')
            ->setParameter('testUuid', 'integration-test-%')
            ->getQuery()
            ->execute();
            
        $entityManager->createQueryBuilder()
            ->delete(Customer::class, 'c')
            ->where('c.email LIKE :testEmail')
            ->setParameter('testEmail', 'integration-test-%')
            ->getQuery()
            ->execute();
    }

    public function testCompleteLeadDeletionFlow(): void
    {
        $client = static::createClient();
        
        // Step 1: Create test lead
        $testLead = $this->createTestLead();
        $leadId = $testLead->getId();
        $leadUuid = $testLead->getLeadUuid();
        
        // Step 2: Verify lead exists in database
        $this->assertNotNull($this->entityManager->find(Lead::class, $leadId));
        
        // Step 3: Test modal loading endpoint
        $client->request('GET', "/leads/{$leadId}/delete-modal", [], [], [
            'HTTP_HX-Request' => 'true'
        ]);
        
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertStringContainsString($leadUuid, $client->getResponse()->getContent());
        $this->assertStringContainsString('Potwierdź usunięcie leada', $client->getResponse()->getContent());
        
        // Step 4: Test DELETE API endpoint
        $client->request('DELETE', "/api/leads/{$leadUuid}");
        
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('lead_uuid', $response);
        $this->assertArrayHasKey('deleted_at', $response);
        $this->assertArrayHasKey('message', $response);
        $this->assertSame($leadUuid, $response['lead_uuid']);
        $this->assertSame('Lead został pomyślnie usunięty', $response['message']);
        
        // Step 5: Verify lead is deleted from database
        $deletedLead = $this->entityManager->find(Lead::class, $leadId);
        $this->assertNull($deletedLead);
        
        // Step 6: Verify event was logged
        $event = $this->entityManager->createQueryBuilder()
            ->select('e')
            ->from(\App\Model\Event::class, 'e')
            ->where('e.eventType = :eventType')
            ->andWhere('e.entityId = :entityId')
            ->setParameter('eventType', 'lead_deleted')
            ->setParameter('entityId', $leadId)
            ->getQuery()
            ->getOneOrNullResult();
        
        $this->assertNotNull($event);
        $this->assertSame('lead_deleted', $event->getEventType());
        $this->assertSame('lead', $event->getEntityType());
        
        $details = $event->getDetails();
        $this->assertSame($leadUuid, $details['lead_uuid']);
        $this->assertSame($testLead->getCustomer()->getId(), $details['customer_id']);
        $this->assertSame($testLead->getApplicationName(), $details['application_name']);
        $this->assertSame($testLead->getStatus(), $details['status']);
    }

    public function testLeadDeletionWithProperty(): void
    {
        $client = static::createClient();
        
        // Create test lead with property
        $testLead = $this->createTestLeadWithProperty();
        $leadId = $testLead->getId();
        $leadUuid = $testLead->getLeadUuid();
        $propertyId = $testLead->getProperty()->getId();
        
        // Verify both lead and property exist
        $this->assertNotNull($this->entityManager->find(Lead::class, $leadId));
        $this->assertNotNull($this->entityManager->find(\App\Model\LeadProperty::class, $propertyId));
        
        // Delete lead
        $client->request('DELETE', "/api/leads/{$leadUuid}");
        
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        
        // Verify cascade delete - both lead and property should be deleted
        $this->assertNull($this->entityManager->find(Lead::class, $leadId));
        $this->assertNull($this->entityManager->find(\App\Model\LeadProperty::class, $propertyId));
    }

    public function testLeadDeletionErrorHandling(): void
    {
        $client = static::createClient();
        
        // Test with non-existent UUID
        $nonExistentUuid = '550e8400-e29b-41d4-a716-446655440000';
        
        $client->request('DELETE', "/api/leads/{$nonExistentUuid}");
        
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Not Found', $response['error']);
        $this->assertSame('Lead nie został znaleziony', $response['message']);
        
        // Test with invalid UUID format
        $invalidUuid = 'invalid-uuid';
        
        $client->request('DELETE', "/api/leads/{$invalidUuid}");
        
        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Bad Request', $response['error']);
        $this->assertSame('Invalid UUID format', $response['message']);
    }

    public function testLeadDeletionModalErrorHandling(): void
    {
        $client = static::createClient();
        
        // Test modal loading with non-existent lead ID
        $nonExistentId = 99999;
        
        $client->request('GET', "/leads/{$nonExistentId}/delete-modal", [], [], [
            'HTTP_HX-Request' => 'true'
        ]);
        
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertStringContainsString('Lead nie został znaleziony', $client->getResponse()->getContent());
        
        // Test modal loading without HTMX header (should redirect)
        $testLead = $this->createTestLead();
        $leadId = $testLead->getId();
        
        $client->request('GET', "/leads/{$leadId}/delete-modal");
        
        $this->assertResponseRedirects('/leads');
    }

    public function testLeadDeletionWithMultipleLeads(): void
    {
        $client = static::createClient();
        
        // Create multiple test leads
        $leads = [];
        for ($i = 0; $i < 3; $i++) {
            $leads[] = $this->createTestLead();
        }
        
        // Verify all leads exist
        foreach ($leads as $lead) {
            $this->assertNotNull($this->entityManager->find(Lead::class, $lead->getId()));
        }
        
        // Delete each lead
        foreach ($leads as $lead) {
            $client->request('DELETE', "/api/leads/{$lead->getLeadUuid()}");
            $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        }
        
        // Verify all leads are deleted
        foreach ($leads as $lead) {
            $this->assertNull($this->entityManager->find(Lead::class, $lead->getId()));
        }
        
        // Verify events were logged for each deletion
        foreach ($leads as $lead) {
            $event = $this->entityManager->createQueryBuilder()
                ->select('e')
                ->from(\App\Model\Event::class, 'e')
                ->where('e.eventType = :eventType')
                ->andWhere('e.entityId = :entityId')
                ->setParameter('eventType', 'lead_deleted')
                ->setParameter('entityId', $lead->getId())
                ->getQuery()
                ->getOneOrNullResult();
            
            $this->assertNotNull($event);
        }
    }

    private function createTestLead(): Lead
    {
        // Create test customer
        $customer = new Customer(
            email: 'integration-test-' . uniqid() . '@example.com',
            phone: '+48123456789',
            firstName: 'Integration',
            lastName: 'Test'
        );
        
        $this->entityManager->persist($customer);
        $this->entityManager->flush();
        
        // Create test lead
        $lead = new Lead(
            leadUuid: 'integration-test-' . uniqid(),
            customer: $customer,
            applicationName: 'morizon'
        );
        
        $this->entityManager->persist($lead);
        $this->entityManager->flush();
        
        return $lead;
    }

    private function createTestLeadWithProperty(): Lead
    {
        // Create test customer
        $customer = new Customer(
            email: 'integration-test-property-' . uniqid() . '@example.com',
            phone: '+48123456789',
            firstName: 'Integration',
            lastName: 'Test'
        );
        
        $this->entityManager->persist($customer);
        $this->entityManager->flush();
        
        // Create test lead
        $lead = new Lead(
            leadUuid: 'integration-test-property-' . uniqid(),
            customer: $customer,
            applicationName: 'gratka'
        );
        
        $this->entityManager->persist($lead);
        $this->entityManager->flush();
        
        // Create test property
        $property = new \App\Model\LeadProperty($lead);
        $property->setPropertyId('TEST-PROP-' . uniqid())
                 ->setPropertyType('apartment')
                 ->setPrice(350000.00)
                 ->setLocation('ul. Testowa 1')
                 ->setCity('Warszawa');
        
        $this->entityManager->persist($property);
        $lead->setProperty($property);
        $this->entityManager->flush();
        
        return $lead;
    }
}
