<?php

declare(strict_types=1);

namespace App\Leads;

use App\DTO\PropertyDto;
use App\Model\Lead;
use App\Model\LeadProperty;

/**
 * Lead property service interface
 * Handles property data associated with leads
 */
interface LeadPropertyServiceInterface
{
    /**
     * Create property record for a lead
     *
     * @param Lead $lead
     * @param PropertyDto $propertyDto
     * @return LeadProperty
     */
    public function createProperty(Lead $lead, PropertyDto $propertyDto): LeadProperty;

    /**
     * Check if property data should be created (if any field is non-null)
     *
     * @param PropertyDto $propertyDto
     * @return bool
     */
    public function shouldCreateProperty(PropertyDto $propertyDto): bool;
}


