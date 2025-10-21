<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeInterface;

/**
 * LeadProperty entity
 * Represents property details associated with a lead
 */
class LeadProperty
{
    private ?int $id = null;
    private Lead $lead;
    private ?string $propertyId = null;
    private ?string $developmentId = null;
    private ?string $partnerId = null;
    private ?string $propertyType = null;
    private ?float $price = null;
    private ?string $location = null;
    private ?string $city = null;
    private DateTimeInterface $createdAt;

    public function __construct(Lead $lead)
    {
        $this->lead = $lead;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLead(): Lead
    {
        return $this->lead;
    }

    public function setLead(Lead $lead): self
    {
        $this->lead = $lead;
        return $this;
    }

    public function getPropertyId(): ?string
    {
        return $this->propertyId;
    }

    public function setPropertyId(?string $propertyId): self
    {
        $this->propertyId = $propertyId;
        return $this;
    }

    public function getDevelopmentId(): ?string
    {
        return $this->developmentId;
    }

    public function setDevelopmentId(?string $developmentId): self
    {
        $this->developmentId = $developmentId;
        return $this;
    }

    public function getPartnerId(): ?string
    {
        return $this->partnerId;
    }

    public function setPartnerId(?string $partnerId): self
    {
        $this->partnerId = $partnerId;
        return $this;
    }

    public function getPropertyType(): ?string
    {
        return $this->propertyType;
    }

    public function setPropertyType(?string $propertyType): self
    {
        $this->propertyType = $propertyType;
        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(?float $price): self
    {
        $this->price = $price;
        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): self
    {
        $this->location = $location;
        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): self
    {
        $this->city = $city;
        return $this;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * @ORM\PrePersist
     */
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
    }
}

