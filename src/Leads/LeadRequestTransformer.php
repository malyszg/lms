<?php

declare(strict_types=1);

namespace App\Leads;

class LeadRequestTransformer implements LeadRequestTransformerInterface
{
    /**
     * @inheritDoc
     */
    public function transformRequestData(array $data, string $applicationName): array
    {
        // If Homsters, transform hms_* fields to standard format
        if ($applicationName === 'homsters' && isset($data['property'])) {
            $data['property'] = $this->transformHomstersProperty($data['property']);
        }

        return $data;
    }

    /**
     * Transform Homsters property fields to standard format
     *
     * Mappings:
     * - hms_property_id → property_id
     * - hms_project_id → development_id
     * - hms_partner_id → partner_id
     *
     * @param array $property Homsters property data
     * @return array Transformed property data
     */
    private function transformHomstersProperty(array $property): array
    {
        $transformed = $property;

        // Map hms_property_id to property_id
        if (isset($property['hms_property_id'])) {
            $transformed['property_id'] = $property['hms_property_id'];
            unset($transformed['hms_property_id']);
        }

        // Map hms_project_id to development_id
        if (isset($property['hms_project_id'])) {
            $transformed['development_id'] = $property['hms_project_id'];
            unset($transformed['hms_project_id']);
        }

        // Map hms_partner_id to partner_id
        if (isset($property['hms_partner_id'])) {
            $transformed['partner_id'] = $property['hms_partner_id'];
            unset($transformed['hms_partner_id']);
        }

        return $transformed;
    }
}































