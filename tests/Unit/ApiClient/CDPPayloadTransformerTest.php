<?php

declare(strict_types=1);

namespace Tests\Unit\ApiClient;

use App\ApiClient\CDPPayloadTransformer;
use App\Model\Customer;
use App\Model\Lead;
use App\Model\LeadProperty;
use PHPUnit\Framework\TestCase;

/**
 * CDPPayloadTransformer Test
 * Tests payload transformation for different CDP systems
 */
class CDPPayloadTransformerTest extends TestCase
{
    private CDPPayloadTransformer $transformer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->transformer = new CDPPayloadTransformer();
    }

    public function testTransformForSalesManago(): void
    {
        $customer = new Customer('test@example.com', '+48123456789', 'Jan', 'Kowalski');
        $lead = new Lead('test-uuid-123', $customer, 'morizon');
        
        $property = new LeadProperty($lead);
        $property->setPropertyId('prop-123');
        $property->setDevelopmentId('dev-456');
        $property->setPartnerId('partner-789');
        $property->setPropertyType('apartment');
        $property->setPrice(500000);
        $property->setLocation('Śródmieście');
        $property->setCity('Warszawa');
        
        $lead->setProperty($property);

        $payload = $this->transformer->transformForSalesManago($lead);

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('contact', $payload);
        $this->assertEquals('test@example.com', $payload['contact']['email']);
        $this->assertEquals('+48123456789', $payload['contact']['phone']);
        $this->assertEquals('Jan Kowalski', $payload['contact']['name']);
        $this->assertArrayHasKey('tags', $payload);
        $this->assertContains('lead', $payload['tags']);
        $this->assertContains('lms', $payload['tags']);
        $this->assertArrayHasKey('customFields', $payload);
        $this->assertEquals('test-uuid-123', $payload['customFields']['lead_uuid']);
        $this->assertEquals('morizon', $payload['customFields']['application_name']);
        $this->assertArrayHasKey('property', $payload['customFields']);
    }

    public function testTransformForSalesManagoWithoutProperty(): void
    {
        $customer = new Customer('test@example.com', '+48123456789');
        $lead = new Lead('test-uuid-123', $customer, 'gratka');

        $payload = $this->transformer->transformForSalesManago($lead);

        $this->assertArrayNotHasKey('property', $payload['customFields']);
    }

    public function testTransformForMurapol(): void
    {
        $customer = new Customer('test@example.com', '+48123456789', 'Jan', 'Kowalski');
        $lead = new Lead('test-uuid-123', $customer, 'homsters');
        
        $property = new LeadProperty($lead);
        $property->setDevelopmentId('dev-456');
        $property->setPropertyId('prop-123');
        
        $lead->setProperty($property);

        $payload = $this->transformer->transformForMurapol($lead);

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('client', $payload);
        $this->assertEquals('test@example.com', $payload['client']['email']);
        $this->assertEquals('+48123456789', $payload['client']['phone']);
        $this->assertEquals('Jan', $payload['client']['first_name']);
        $this->assertEquals('Kowalski', $payload['client']['last_name']);
        $this->assertEquals('dev-456', $payload['project_id']);
        $this->assertEquals('prop-123', $payload['property_id']);
        $this->assertArrayHasKey('metadata', $payload);
        $this->assertEquals('test-uuid-123', $payload['metadata']['lead_uuid']);
    }

    public function testTransformForDomDevelopment(): void
    {
        $customer = new Customer('test@example.com', '+48123456789', 'Jan', 'Kowalski');
        $lead = new Lead('test-uuid-123', $customer, 'gratka');
        
        $property = new LeadProperty($lead);
        $property->setDevelopmentId('dev-789');
        
        $lead->setProperty($property);

        $payload = $this->transformer->transformForDomDevelopment($lead);

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('client', $payload);
        $this->assertEquals('test@example.com', $payload['client']['email']);
        $this->assertEquals('+48123456789', $payload['client']['phone']);
        $this->assertEquals('Jan Kowalski', $payload['client']['name']);
        $this->assertEquals('dev-789', $payload['development_id']);
        $this->assertEquals('test-uuid-123', $payload['lead_uuid']);
        $this->assertEquals('gratka', $payload['application_name']);
        $this->assertArrayHasKey('property_details', $payload);
    }
}

