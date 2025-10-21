<?php

declare(strict_types=1);

namespace App\Leads;

use App\DTO\PropertyDto;
use App\Model\Lead;
use App\Model\LeadProperty;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Lead property service implementation
 * Manages property data associated with leads
 */
class LeadPropertyService implements LeadPropertyServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {}

    /**
     * Create property record for a lead
     *
     * @param Lead $lead
     * @param PropertyDto $propertyDto
     * @return LeadProperty
     */
    public function createProperty(Lead $lead, PropertyDto $propertyDto): LeadProperty
    {
        $property = new LeadProperty($lead);

        if ($propertyDto->propertyId !== null) {
            $property->setPropertyId($propertyDto->propertyId);
        }

        if ($propertyDto->developmentId !== null) {
            $property->setDevelopmentId($propertyDto->developmentId);
        }

        if ($propertyDto->partnerId !== null) {
            $property->setPartnerId($propertyDto->partnerId);
        }

        if ($propertyDto->propertyType !== null) {
            $property->setPropertyType($propertyDto->propertyType);
        }

        if ($propertyDto->price !== null) {
            $property->setPrice($propertyDto->price);
        }

        if ($propertyDto->location !== null) {
            $property->setLocation($propertyDto->location);
        }

        if ($propertyDto->city !== null) {
            $property->setCity($propertyDto->city);
        }

        $this->entityManager->persist($property);
        $this->entityManager->flush();

        return $property;
    }

    /**
     * Check if property data should be created
     * Returns true if at least one property field is non-null
     *
     * @param PropertyDto $propertyDto
     * @return bool
     */
    public function shouldCreateProperty(PropertyDto $propertyDto): bool
    {
        return $propertyDto->propertyId !== null
            || $propertyDto->developmentId !== null
            || $propertyDto->partnerId !== null
            || $propertyDto->propertyType !== null
            || $propertyDto->price !== null
            || $propertyDto->location !== null
            || $propertyDto->city !== null;
    }
}































