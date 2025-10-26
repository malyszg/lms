<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Customer entity
 * Represents a customer in the LMS system
 */
class Customer
{
    private ?int $id = null;
    private string $email;
    private string $phone;
    private ?string $firstName = null;
    private ?string $lastName = null;
    private DateTimeInterface $createdAt;
    private DateTimeInterface $updatedAt;
    private Collection $leads;

    public function __construct(
        string $email,
        string $phone,
        ?string $firstName = null,
        ?string $lastName = null
    ) {
        $this->email = $email;
        $this->phone = $phone;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
        $this->leads = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): self
    {
        $this->phone = $phone;
        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): self
    {
        $this->lastName = $lastName;
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

    public function getUpdatedAt(): DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return Collection<int, Lead>
     */
    public function getLeads(): Collection
    {
        return $this->leads;
    }

    public function addLead(Lead $lead): self
    {
        if (!$this->leads->contains($lead)) {
            $this->leads->add($lead);
            $lead->setCustomer($this);
        }

        return $this;
    }

    public function removeLead(Lead $lead): self
    {
        if ($this->leads->removeElement($lead)) {
            // Note: In many-to-one relationship, we don't set customer to null
            // The lead will be removed from the collection but keep its customer reference
        }

        return $this;
    }

    /**
     * @ORM\PrePersist
     */
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    /**
     * @ORM\PreUpdate
     */
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
    }
}

