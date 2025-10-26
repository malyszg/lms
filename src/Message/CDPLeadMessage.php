<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Message to send a lead to CDP systems
 */
class CDPLeadMessage
{
    public function __construct(
        private readonly int $leadId,
        private readonly string $leadUuid
    ) {
    }

    public function getLeadId(): int
    {
        return $this->leadId;
    }

    public function getLeadUuid(): string
    {
        return $this->leadUuid;
    }
}

