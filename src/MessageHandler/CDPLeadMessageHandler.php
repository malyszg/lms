<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Exception\LeadNotFoundException;
use App\Leads\LeadServiceInterface;
use App\Message\CDPLeadMessage;
use App\Model\Lead;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class CDPLeadMessageHandler
{
    public function __construct(
        private readonly LeadServiceInterface $leadService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(CDPLeadMessage $message): void
    {
        $this->logger->info('Processing CDP delivery message', [
            'lead_id' => $message->getLeadId(),
            'lead_uuid' => $message->getLeadUuid(),
        ]);

        try {
            // Get lead from database
            $lead = $this->leadService->findByUuid($message->getLeadUuid());

            if (!$lead instanceof Lead) {
                throw new LeadNotFoundException(sprintf('Lead with UUID %s not found', $message->getLeadUuid()));
            }

            // Send to CDP - this will handle all configured CDP systems
            $this->leadService->sendLeadToCDP($lead);

            $this->logger->info('Successfully processed CDP delivery message', [
                'lead_id' => $message->getLeadId(),
                'lead_uuid' => $message->getLeadUuid(),
            ]);
        } catch (LeadNotFoundException $e) {
            $this->logger->error('Lead not found for CDP delivery', [
                'lead_id' => $message->getLeadId(),
                'lead_uuid' => $message->getLeadUuid(),
                'error' => $e->getMessage(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error processing CDP delivery message', [
                'lead_id' => $message->getLeadId(),
                'lead_uuid' => $message->getLeadUuid(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}

