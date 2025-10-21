<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeInterface;

/**
 * FailedDelivery entity
 * Represents failed CDP delivery attempts
 */
class FailedDelivery
{
    private ?int $id = null;
    private Lead $lead;
    private string $cdpSystemName;
    private ?string $errorCode = null;
    private ?string $errorMessage = null;
    private int $retryCount = 0;
    private int $maxRetries = 3;
    private ?DateTimeInterface $nextRetryAt = null;
    private string $status = 'pending';
    private DateTimeInterface $createdAt;
    private ?DateTimeInterface $resolvedAt = null;

    public function __construct(Lead $lead, string $cdpSystemName)
    {
        $this->lead = $lead;
        $this->cdpSystemName = $cdpSystemName;
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

    public function getCdpSystemName(): string
    {
        return $this->cdpSystemName;
    }

    public function setCdpSystemName(string $cdpSystemName): self
    {
        $this->cdpSystemName = $cdpSystemName;
        return $this;
    }

    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    public function setErrorCode(?string $errorCode): self
    {
        $this->errorCode = $errorCode;
        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    public function setRetryCount(int $retryCount): self
    {
        $this->retryCount = $retryCount;
        return $this;
    }

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    public function setMaxRetries(int $maxRetries): self
    {
        $this->maxRetries = $maxRetries;
        return $this;
    }

    public function getNextRetryAt(): ?DateTimeInterface
    {
        return $this->nextRetryAt;
    }

    public function setNextRetryAt(?DateTimeInterface $nextRetryAt): self
    {
        $this->nextRetryAt = $nextRetryAt;
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

    public function getResolvedAt(): ?DateTimeInterface
    {
        return $this->resolvedAt;
    }

    public function setResolvedAt(?DateTimeInterface $resolvedAt): self
    {
        $this->resolvedAt = $resolvedAt;
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

