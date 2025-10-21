<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeInterface;

/**
 * Lead entity
 * Represents a lead in the LMS system
 */
class Lead
{
    private ?int $id = null;
    private string $leadUuid;
    private Customer $customer;
    private string $applicationName;
    private string $status = 'new';
    private DateTimeInterface $createdAt;
    private DateTimeInterface $updatedAt;
    private ?LeadProperty $property = null;
    
    // AI Scoring Cache
    private ?int $aiScore = null;
    private ?string $aiCategory = null;
    private ?string $aiReasoning = null;
    private ?array $aiSuggestions = null;
    private ?DateTimeInterface $aiScoredAt = null;

    public function __construct(
        string $leadUuid,
        Customer $customer,
        string $applicationName
    ) {
        $this->leadUuid = $leadUuid;
        $this->customer = $customer;
        $this->applicationName = $applicationName;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLeadUuid(): string
    {
        return $this->leadUuid;
    }

    public function setLeadUuid(string $leadUuid): self
    {
        $this->leadUuid = $leadUuid;
        return $this;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function setCustomer(Customer $customer): self
    {
        $this->customer = $customer;
        return $this;
    }

    public function getApplicationName(): string
    {
        return $this->applicationName;
    }

    public function setApplicationName(string $applicationName): self
    {
        $this->applicationName = $applicationName;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
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

    public function getProperty(): ?LeadProperty
    {
        return $this->property;
    }

    public function setProperty(?LeadProperty $property): self
    {
        $this->property = $property;
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
    
    // AI Scoring Cache Getters and Setters
    
    public function getAiScore(): ?int
    {
        return $this->aiScore;
    }
    
    public function setAiScore(?int $aiScore): self
    {
        $this->aiScore = $aiScore;
        return $this;
    }
    
    public function getAiCategory(): ?string
    {
        return $this->aiCategory;
    }
    
    public function setAiCategory(?string $aiCategory): self
    {
        $this->aiCategory = $aiCategory;
        return $this;
    }
    
    public function getAiReasoning(): ?string
    {
        return $this->aiReasoning;
    }
    
    public function setAiReasoning(?string $aiReasoning): self
    {
        $this->aiReasoning = $aiReasoning;
        return $this;
    }
    
    public function getAiSuggestions(): ?array
    {
        return $this->aiSuggestions;
    }
    
    public function setAiSuggestions(?array $aiSuggestions): self
    {
        $this->aiSuggestions = $aiSuggestions;
        return $this;
    }
    
    public function getAiScoredAt(): ?DateTimeInterface
    {
        return $this->aiScoredAt;
    }
    
    public function setAiScoredAt(?DateTimeInterface $aiScoredAt): self
    {
        $this->aiScoredAt = $aiScoredAt;
        return $this;
    }
    
    /**
     * Check if lead has been scored by AI
     */
    public function isAiScored(): bool
    {
        return $this->aiScore !== null && $this->aiScoredAt !== null;
    }
    
    /**
     * Check if AI score needs refresh (older than 24 hours)
     */
    public function needsAiRescore(): bool
    {
        if (!$this->isAiScored()) {
            return true;
        }
        
        $now = new \DateTime();
        $hoursSinceScore = $now->diff($this->aiScoredAt)->h + ($now->diff($this->aiScoredAt)->days * 24);
        
        return $hoursSinceScore >= 24;
    }
}

