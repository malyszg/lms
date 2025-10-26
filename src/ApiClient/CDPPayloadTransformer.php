<?php

declare(strict_types=1);

namespace App\ApiClient;

use App\Model\Lead;

/**
 * CDP Payload Transformer
 * Transforms lead data to CDP system-specific format
 */
class CDPPayloadTransformer implements CDPPayloadTransformerInterface
{
    /**
     * Transform lead data for SalesManago
     *
     * @param Lead $lead Lead to transform
     * @return array<string, mixed> SalesManago payload
     */
    public function transformForSalesManago(Lead $lead): array
    {
        $customer = $lead->getCustomer();
        $property = $lead->getProperty();
        
        $payload = [
            'contact' => [
                'email' => $customer->getEmail(),
                'phone' => $customer->getPhone(),
            ],
            'tags' => ['lead', 'lms'],
            'customFields' => [
                'lead_uuid' => $lead->getLeadUuid(),
                'application_name' => $lead->getApplicationName(),
            ],
        ];

        // Add name if available
        if ($customer->getFirstName() || $customer->getLastName()) {
            $name = trim(($customer->getFirstName() ?? '') . ' ' . ($customer->getLastName() ?? ''));
            if ($name) {
                $payload['contact']['name'] = $name;
            }
        }

        // Note: Customer preferences not yet implemented as separate entity
        // This will be added in future when customer preferences are implemented

        // Add property data if available
        if ($property) {
            $payload['customFields']['property'] = [
                'property_id' => $property->getPropertyId(),
                'development_id' => $property->getDevelopmentId(),
                'partner_id' => $property->getPartnerId(),
                'property_type' => $property->getPropertyType(),
                'price' => $property->getPrice(),
                'location' => $property->getLocation(),
                'city' => $property->getCity(),
            ];
        }

        return $payload;
    }

    /**
     * Transform lead data for Murapol
     *
     * @param Lead $lead Lead to transform
     * @return array<string, mixed> Murapol payload
     */
    public function transformForMurapol(Lead $lead): array
    {
        $customer = $lead->getCustomer();
        $property = $lead->getProperty();
        
        $payload = [
            'client' => [
                'phone' => $customer->getPhone(),
                'email' => $customer->getEmail(),
            ],
        ];

        // Add name if available
        if ($customer->getFirstName() || $customer->getLastName()) {
            $payload['client']['first_name'] = $customer->getFirstName();
            $payload['client']['last_name'] = $customer->getLastName();
        }

        // Add property/development IDs
        if ($property) {
            $payload['project_id'] = $property->getDevelopmentId();
            $payload['property_id'] = $property->getPropertyId();
            $payload['partner_id'] = $property->getPartnerId();
            
            if ($property->getPrice()) {
                $payload['property_price'] = $property->getPrice();
            }
            if ($property->getLocation()) {
                $payload['property_location'] = $property->getLocation();
            }
            if ($property->getCity()) {
                $payload['property_city'] = $property->getCity();
            }
        }

        // Add metadata
        $payload['metadata'] = [
            'lead_uuid' => $lead->getLeadUuid(),
            'application_name' => $lead->getApplicationName(),
            'lead_status' => $lead->getStatus(),
        ];

        // Note: Customer preferences not yet implemented as separate entity
        // This will be added in future when customer preferences are implemented

        return $payload;
    }

    /**
     * Transform lead data for DomDevelopment
     *
     * @param Lead $lead Lead to transform
     * @return array<string, mixed> DomDevelopment payload
     */
    public function transformForDomDevelopment(Lead $lead): array
    {
        $customer = $lead->getCustomer();
        $property = $lead->getProperty();
        
        $payload = [
            'client' => [
                'email' => $customer->getEmail(),
                'phone' => $customer->getPhone(),
            ],
            'lead_uuid' => $lead->getLeadUuid(),
            'application_name' => $lead->getApplicationName(),
        ];

        // Add name if available
        if ($customer->getFirstName() || $customer->getLastName()) {
            $name = trim(($customer->getFirstName() ?? '') . ' ' . ($customer->getLastName() ?? ''));
            if ($name) {
                $payload['client']['name'] = $name;
            }
        }

        // Add development ID
        if ($property && $property->getDevelopmentId()) {
            $payload['development_id'] = $property->getDevelopmentId();
        }

        // Add property details
        if ($property) {
            $payload['property_details'] = [
                'property_id' => $property->getPropertyId(),
                'development_id' => $property->getDevelopmentId(),
                'partner_id' => $property->getPartnerId(),
            ];
            
            if ($property->getPropertyType()) {
                $payload['property_details']['type'] = $property->getPropertyType();
            }
            if ($property->getPrice()) {
                $payload['property_details']['price'] = $property->getPrice();
            }
            if ($property->getLocation()) {
                $payload['property_details']['location'] = $property->getLocation();
            }
            if ($property->getCity()) {
                $payload['property_details']['city'] = $property->getCity();
            }
        }

        // Note: Customer preferences not yet implemented as separate entity
        // This will be added in future when customer preferences are implemented

        // Add lead status
        $payload['status'] = $lead->getStatus();

        return $payload;
    }
}

