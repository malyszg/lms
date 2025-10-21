<?php

declare(strict_types=1);

namespace App\Tests\Unit\Leads;

use App\Leads\LeadRequestTransformer;
use PHPUnit\Framework\TestCase;

class LeadRequestTransformerTest extends TestCase
{
    private LeadRequestTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new LeadRequestTransformer();
    }

    public function testTransformMorizonDataDoesNotChangeStructure(): void
    {
        $data = [
            'lead_uuid' => 'a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d',
            'application_name' => 'morizon',
            'customer' => [
                'email' => 'test@example.com',
                'phone' => '+48601234567'
            ],
            'property' => [
                'property_id' => 'MRZ-001',
                'development_id' => 'DEV-001',
                'partner_id' => 'PARTNER-001',
                'price' => 850000.00,
                'city' => 'Warszawa'
            ]
        ];

        $result = $this->transformer->transformRequestData($data, 'morizon');

        $this->assertEquals($data, $result);
        $this->assertArrayHasKey('property_id', $result['property']);
        $this->assertArrayHasKey('development_id', $result['property']);
        $this->assertArrayHasKey('partner_id', $result['property']);
    }

    public function testTransformGratkaDataDoesNotChangeStructure(): void
    {
        $data = [
            'lead_uuid' => 'b2c3d4e5-f6a7-4b8c-9d0e-1f2a3b4c5d6e',
            'application_name' => 'gratka',
            'customer' => [
                'email' => 'test@gratka.pl',
                'phone' => '+48602345678'
            ],
            'property' => [
                'property_id' => 'GRT-002',
                'development_id' => 'DEV-002',
                'partner_id' => 'PARTNER-002'
            ]
        ];

        $result = $this->transformer->transformRequestData($data, 'gratka');

        $this->assertEquals($data, $result);
    }

    public function testTransformHomstersDataMapsFieldsCorrectly(): void
    {
        $data = [
            'lead_uuid' => 'c3d4e5f6-a7b8-4c9d-0e1f-2a3b4c5d6e7f',
            'application_name' => 'homsters',
            'customer' => [
                'email' => 'test@homsters.pl',
                'phone' => '+48703456789'
            ],
            'property' => [
                'hms_property_id' => 'HMS-PROP-001',
                'hms_project_id' => 'HMS-PROJ-001',
                'hms_partner_id' => 'HMS-PARTNER-001',
                'property_type' => 'apartment',
                'price' => 920000.00,
                'city' => 'Warszawa'
            ]
        ];

        $result = $this->transformer->transformRequestData($data, 'homsters');

        // Check that hms_* fields are mapped to standard fields
        $this->assertArrayHasKey('property_id', $result['property']);
        $this->assertArrayHasKey('development_id', $result['property']);
        $this->assertArrayHasKey('partner_id', $result['property']);

        // Check values are correctly mapped
        $this->assertEquals('HMS-PROP-001', $result['property']['property_id']);
        $this->assertEquals('HMS-PROJ-001', $result['property']['development_id']);
        $this->assertEquals('HMS-PARTNER-001', $result['property']['partner_id']);

        // Check that hms_* fields are removed
        $this->assertArrayNotHasKey('hms_property_id', $result['property']);
        $this->assertArrayNotHasKey('hms_project_id', $result['property']);
        $this->assertArrayNotHasKey('hms_partner_id', $result['property']);

        // Check other fields remain unchanged
        $this->assertEquals('apartment', $result['property']['property_type']);
        $this->assertEquals(920000.00, $result['property']['price']);
        $this->assertEquals('Warszawa', $result['property']['city']);
    }

    public function testTransformHomstersDataWithPartialFields(): void
    {
        $data = [
            'lead_uuid' => 'd4e5f6a7-b8c9-4d0e-1f2a-3b4c5d6e7f8a',
            'application_name' => 'homsters',
            'customer' => [
                'email' => 'partial@homsters.pl',
                'phone' => '+48704567890'
            ],
            'property' => [
                'hms_property_id' => 'HMS-PROP-002',
                'hms_project_id' => 'HMS-PROJ-002',
                // hms_partner_id is missing
                'price' => 750000.00
            ]
        ];

        $result = $this->transformer->transformRequestData($data, 'homsters');

        // Check mapped fields
        $this->assertArrayHasKey('property_id', $result['property']);
        $this->assertArrayHasKey('development_id', $result['property']);
        $this->assertEquals('HMS-PROP-002', $result['property']['property_id']);
        $this->assertEquals('HMS-PROJ-002', $result['property']['development_id']);

        // Check that partner_id is not present (was not in original data)
        $this->assertArrayNotHasKey('partner_id', $result['property']);

        // Check hms_* fields are removed
        $this->assertArrayNotHasKey('hms_property_id', $result['property']);
        $this->assertArrayNotHasKey('hms_project_id', $result['property']);
    }

    public function testTransformHomstersDataWithoutPropertySection(): void
    {
        $data = [
            'lead_uuid' => 'e5f6a7b8-c9d0-4e1f-2a3b-4c5d6e7f8a9b',
            'application_name' => 'homsters',
            'customer' => [
                'email' => 'noproperty@homsters.pl',
                'phone' => '+48705678901'
            ]
            // No property section
        ];

        $result = $this->transformer->transformRequestData($data, 'homsters');

        // Should not throw exception, just return data as-is
        $this->assertEquals($data, $result);
        $this->assertArrayNotHasKey('property', $result);
    }

    public function testTransformHomstersDataWithEmptyPropertySection(): void
    {
        $data = [
            'lead_uuid' => 'f6a7b8c9-d0e1-4f2a-3b4c-5d6e7f8a9b0c',
            'application_name' => 'homsters',
            'customer' => [
                'email' => 'emptyprop@homsters.pl',
                'phone' => '+48706789012'
            ],
            'property' => []
        ];

        $result = $this->transformer->transformRequestData($data, 'homsters');

        // Empty property should remain empty
        $this->assertArrayHasKey('property', $result);
        $this->assertEmpty($result['property']);
    }

    public function testTransformHomstersDataPreservesOtherPropertyFields(): void
    {
        $data = [
            'lead_uuid' => 'a7b8c9d0-e1f2-4a3b-4c5d-6e7f8a9b0c1d',
            'application_name' => 'homsters',
            'customer' => [
                'email' => 'preserve@homsters.pl',
                'phone' => '+48707890123'
            ],
            'property' => [
                'hms_property_id' => 'HMS-PROP-003',
                'hms_project_id' => 'HMS-PROJ-003',
                'hms_partner_id' => 'HMS-PARTNER-003',
                'property_type' => 'house',
                'price' => 1450000.00,
                'location' => 'Piaseczno, ul. Leśna 12',
                'city' => 'Piaseczno'
            ]
        ];

        $result = $this->transformer->transformRequestData($data, 'homsters');

        // Check all non-hms fields are preserved
        $this->assertEquals('house', $result['property']['property_type']);
        $this->assertEquals(1450000.00, $result['property']['price']);
        $this->assertEquals('Piaseczno, ul. Leśna 12', $result['property']['location']);
        $this->assertEquals('Piaseczno', $result['property']['city']);

        // Check mapped fields
        $this->assertEquals('HMS-PROP-003', $result['property']['property_id']);
        $this->assertEquals('HMS-PROJ-003', $result['property']['development_id']);
        $this->assertEquals('HMS-PARTNER-003', $result['property']['partner_id']);
    }
}































