<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\Model\Lead;
use App\Model\Customer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * End-to-end tests for lead deletion UI functionality
 * Tests the complete user interaction flow
 */
class LeadDeletionE2ETest extends WebTestCase
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
            ->setParameter('testUuid', 'e2e-test-%')
            ->getQuery()
            ->execute();
            
        $entityManager->createQueryBuilder()
            ->delete(Customer::class, 'c')
            ->where('c.email LIKE :testEmail')
            ->setParameter('testEmail', 'e2e-test-%')
            ->getQuery()
            ->execute();
    }

    public function testLeadDeletionUIFlow(): void
    {
        $client = static::createClient();
        
        // Step 1: Create test lead
        $testLead = $this->createTestLead();
        $leadId = $testLead->getId();
        $leadUuid = $testLead->getLeadUuid();
        
        // Step 2: Navigate to leads page
        $crawler = $client->request('GET', '/leads');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        
        // Step 3: Verify lead is displayed in table
        $this->assertStringContainsString($leadUuid, $client->getResponse()->getContent());
        $this->assertStringContainsString('test@example.com', $client->getResponse()->getContent());
        
        // Step 4: Test delete button presence (should be visible for ROLE_CALL_CENTER)
        $this->assertStringContainsString('data-bs-target="#deleteModal"', $client->getResponse()->getContent());
        $this->assertStringContainsString('onclick="loadDeleteModal(', $client->getResponse()->getContent());
        
        // Step 5: Test modal loading endpoint
        $client->request('GET', "/leads/{$leadId}/delete-modal", [], [], [
            'HTTP_HX-Request' => 'true'
        ]);
        
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $modalContent = $client->getResponse()->getContent();
        
        // Verify modal content
        $this->assertStringContainsString('Potwierdź usunięcie leada', $modalContent);
        $this->assertStringContainsString($leadUuid, $modalContent);
        $this->assertStringContainsString('test@example.com', $modalContent);
        $this->assertStringContainsString('Uwaga: Ta operacja jest nieodwracalna!', $modalContent);
        
        // Verify HTMX attributes in modal
        $this->assertStringContainsString('hx-delete="/api/leads/' . $leadUuid . '"', $modalContent);
        $this->assertStringContainsString('hx-target="#lead-row-' . $leadId . '"', $modalContent);
        $this->assertStringContainsString('hx-swap="outerHTML"', $modalContent);
        $this->assertStringContainsString('hx-confirm="Czy na pewno chcesz usunąć ten lead?"', $modalContent);
        
        // Step 6: Test DELETE API endpoint
        $client->request('DELETE', "/api/leads/{$leadUuid}");
        
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('lead_uuid', $response);
        $this->assertArrayHasKey('deleted_at', $response);
        $this->assertArrayHasKey('message', $response);
        $this->assertSame($leadUuid, $response['lead_uuid']);
        $this->assertSame('Lead został pomyślnie usunięty', $response['message']);
        
        // Step 7: Verify lead is deleted from database
        $deletedLead = $this->entityManager->find(Lead::class, $leadId);
        $this->assertNull($deletedLead);
        
        // Step 8: Verify event was logged
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
    }

    public function testLeadDeletionUIWithDifferentRoles(): void
    {
        $client = static::createClient();
        
        // Create test lead
        $testLead = $this->createTestLead();
        
        // Test leads page
        $crawler = $client->request('GET', '/leads');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        
        // In MVP, all users can see delete buttons since auth is not implemented
        // In production, this would test different role behaviors
        $this->assertStringContainsString('data-bs-target="#deleteModal"', $client->getResponse()->getContent());
        
        // Test modal loading
        $client->request('GET', "/leads/{$testLead->getId()}/delete-modal", [], [], [
            'HTTP_HX-Request' => 'true'
        ]);
        
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
    }

    public function testLeadDeletionUIErrorHandling(): void
    {
        $client = static::createClient();
        
        // Test modal loading with non-existent lead
        $nonExistentId = 99999;
        
        $client->request('GET', "/leads/{$nonExistentId}/delete-modal", [], [], [
            'HTTP_HX-Request' => 'true'
        ]);
        
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $this->assertStringContainsString('Lead nie został znaleziony', $client->getResponse()->getContent());
        
        // Test DELETE with non-existent UUID
        $nonExistentUuid = '550e8400-e29b-41d4-a716-446655440000';
        
        $client->request('DELETE', "/api/leads/{$nonExistentUuid}");
        
        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        
        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Not Found', $response['error']);
        $this->assertSame('Lead nie został znaleziony', $response['message']);
    }

    public function testLeadDeletionUIWithPropertyData(): void
    {
        $client = static::createClient();
        
        // Create test lead with property
        $testLead = $this->createTestLeadWithProperty();
        $leadId = $testLead->getId();
        $leadUuid = $testLead->getLeadUuid();
        
        // Test modal loading
        $client->request('GET', "/leads/{$leadId}/delete-modal", [], [], [
            'HTTP_HX-Request' => 'true'
        ]);
        
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        $modalContent = $client->getResponse()->getContent();
        
        // Verify modal shows lead details
        $this->assertStringContainsString($leadUuid, $modalContent);
        $this->assertStringContainsString('test-property@example.com', $modalContent);
        
        // Test deletion
        $client->request('DELETE', "/api/leads/{$leadUuid}");
        
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        
        // Verify cascade delete
        $this->assertNull($this->entityManager->find(Lead::class, $leadId));
        $this->assertNull($this->entityManager->find(\App\Model\LeadProperty::class, $testLead->getProperty()->getId()));
    }

    public function testLeadDeletionUIJavaScriptIntegration(): void
    {
        $client = static::createClient();
        
        // Create test lead
        $testLead = $this->createTestLead();
        
        // Test leads page contains JavaScript functions
        $crawler = $client->request('GET', '/leads');
        $this->assertResponseStatusCodeSame(Response::HTTP_OK);
        
        $pageContent = $client->getResponse()->getContent();
        
        // Verify JavaScript functions are present
        $this->assertStringContainsString('loadDeleteModal', $pageContent);
        $this->assertStringContainsString('showToast', $pageContent);
        
        // Verify HTMX error handling
        $this->assertStringContainsString('htmx:responseError', $pageContent);
        $this->assertStringContainsString('htmx:afterRequest', $pageContent);
        
        // Verify modal container exists
        $this->assertStringContainsString('id="delete-modal-container"', $pageContent);
    }

    private function createTestLead(): Lead
    {
        // Create test customer
        $customer = new Customer(
            email: 'e2e-test-' . uniqid() . '@example.com',
            phone: '+48123456789',
            firstName: 'E2E',
            lastName: 'Test'
        );
        
        $this->entityManager->persist($customer);
        $this->entityManager->flush();
        
        // Create test lead
        $lead = new Lead(
            leadUuid: 'e2e-test-' . uniqid(),
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
            email: 'e2e-test-property-' . uniqid() . '@example.com',
            phone: '+48123456789',
            firstName: 'E2E',
            lastName: 'Test'
        );
        
        $this->entityManager->persist($customer);
        $this->entityManager->flush();
        
        // Create test lead
        $lead = new Lead(
            leadUuid: 'e2e-test-property-' . uniqid(),
            customer: $customer,
            applicationName: 'gratka'
        );
        
        $this->entityManager->persist($lead);
        $this->entityManager->flush();
        
        // Create test property
        $property = new \App\Model\LeadProperty($lead);
        $property->setPropertyId('E2E-PROP-' . uniqid())
                 ->setPropertyType('apartment')
                 ->setPrice(450000.00)
                 ->setLocation('ul. E2E Testowa 1')
                 ->setCity('Kraków');
        
        $this->entityManager->persist($property);
        $lead->setProperty($property);
        $this->entityManager->flush();
        
        return $lead;
    }
}
