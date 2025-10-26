<?php

declare(strict_types=1);

namespace App\Leads;

use App\ApiClient\CDPDeliveryServiceInterface;
use App\DTO\CreateLeadRequest;
use App\DTO\CreateLeadResponse;
use App\DTO\UpdateLeadRequest;
use App\Exception\LeadAlreadyExistsException;
use App\Exception\LeadNotFoundException;
use App\Exception\ValidationException;
use App\Message\CDPLeadMessage;
use App\Model\Lead;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Lead service implementation
 * Main orchestrator for lead creation process
 * Coordinates customer deduplication, lead creation, and property creation
 */
class LeadService implements LeadServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CustomerServiceInterface $customerService,
        private readonly LeadPropertyServiceInterface $propertyService,
        private readonly EventServiceInterface $eventService,
        private readonly CDPDeliveryServiceInterface $cdpDeliveryService,
        private readonly LeadScoringServiceInterface $leadScoringService,
        private readonly MessageBusInterface $messageBus,
        private readonly ?LoggerInterface $logger = null
    ) {}

    /**
     * Create new lead with full transaction
     *
     * @param CreateLeadRequest $request
     * @param string|null $ipAddress Client IP address for logging
     * @param string|null $userAgent Client user agent for logging
     * @return CreateLeadResponse
     * @throws LeadAlreadyExistsException If lead with given UUID already exists
     * @throws \Exception On database errors
     */
    public function createLead(
        CreateLeadRequest $request,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): CreateLeadResponse {
        // Start transaction
        $this->entityManager->beginTransaction();

        try {
            // Check for duplicate UUID
            $existingLead = $this->findByUuid($request->leadUuid);
            if ($existingLead !== null) {
                throw new LeadAlreadyExistsException(
                    $request->leadUuid,
                    $existingLead->getId()
                );
            }

            // Step 1: Find or create customer (with deduplication)
            $customer = $this->customerService->findOrCreateCustomer($request->customer);

            // Step 2: Create lead
            $lead = new Lead(
                $request->leadUuid,
                $customer,
                $request->applicationName
            );

            $this->entityManager->persist($lead);
            $this->entityManager->flush();

            // Step 3: Create property if any property data is provided
            if ($this->propertyService->shouldCreateProperty($request->property)) {
                $property = $this->propertyService->createProperty($lead, $request->property);
                $lead->setProperty($property);
            }

            // Step 4: Log lead creation event
            $this->eventService->logLeadCreated($lead, $ipAddress, $userAgent);

            // Commit transaction
            $this->entityManager->commit();

            // Step 5: Send to CDP systems (async via RabbitMQ)
            // Publish message to RabbitMQ queue - processing happens in background
            try {
                $message = new CDPLeadMessage($lead->getId(), $lead->getLeadUuid());
                $this->messageBus->dispatch($message);
                
                $this->logger?->info('CDP delivery message queued', [
                    'lead_id' => $lead->getId(),
                    'lead_uuid' => $lead->getLeadUuid(),
                ]);
            } catch (\Exception $e) {
                // RabbitMQ publishing failure shouldn't fail the lead creation
                $this->logger?->error('Failed to queue CDP delivery message', [
                    'lead_id' => $lead->getId(),
                    'lead_uuid' => $lead->getLeadUuid(),
                    'error' => $e->getMessage(),
                ]);
            }

            // Step 6: Score lead with AI (after successful commit)
            // This runs in background and doesn't block API response
            try {
                $this->scoreLeadAsync($lead);
            } catch (\Exception $e) {
                // AI scoring failure shouldn't fail the lead creation
                $this->logger?->warning('Failed to score lead automatically', [
                    'lead_id' => $lead->getId(),
                    'lead_uuid' => $lead->getLeadUuid(),
                    'error' => $e->getMessage()
                ]);
            }

            // Return response DTO
            return new CreateLeadResponse(
                id: $lead->getId(),
                leadUuid: $lead->getLeadUuid(),
                status: $lead->getStatus(),
                customerId: $customer->getId(),
                applicationName: $lead->getApplicationName(),
                createdAt: $lead->getCreatedAt(),
                cdpDeliveryStatus: 'pending'
            );

        } catch (\Exception $e) {
            // Rollback on any error
            $this->entityManager->rollback();
            throw $e;
        }
    }

    /**
     * Check if lead with given UUID exists
     *
     * @param string $leadUuid
     * @return bool
     */
    public function leadExists(string $leadUuid): bool
    {
        return $this->findByUuid($leadUuid) !== null;
    }

    /**
     * Find lead by UUID
     *
     * @param string $leadUuid
     * @return Lead|null
     */
    public function findByUuid(string $leadUuid): ?Lead
    {
        $repository = $this->entityManager->getRepository(Lead::class);

        return $repository->findOneBy(['leadUuid' => $leadUuid]);
    }

    /**
     * Delete lead by UUID
     * Implements full transaction: logging event, cascade delete
     *
     * @param string $leadUuid UUID of lead to delete
     * @param string|null $ipAddress Client IP address for logging
     * @param string|null $userAgent Client user agent for logging
     * @return void
     * @throws \App\Exception\LeadNotFoundException If lead with given UUID doesn't exist
     * @throws \Exception On database errors
     */
    public function deleteLead(
        string $leadUuid,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        // Start transaction
        $this->entityManager->beginTransaction();

        try {
            // Find lead by UUID
            $lead = $this->findByUuid($leadUuid);
            if ($lead === null) {
                throw new \App\Exception\LeadNotFoundException($leadUuid);
            }

            // Load property to ensure it's in memory
            $property = $lead->getProperty();

            // Log deletion event before deleting the lead
            $this->eventService->logLeadDeleted($lead, $ipAddress, $userAgent);

            // Remove LeadProperty first if exists
            if ($property !== null) {
                $this->entityManager->remove($property);
            }

            // Remove lead from database
            $this->entityManager->remove($lead);
            $this->entityManager->flush();

            // Commit transaction
            $this->entityManager->commit();

            $this->logger?->info('Lead deleted successfully', [
                'lead_uuid' => $leadUuid,
                'lead_id' => $lead->getId(),
                'ip_address' => $ipAddress
            ]);

        } catch (\Exception $e) {
            // Rollback on any error
            $this->entityManager->rollback();
            
            $this->logger?->error('Failed to delete lead', [
                'lead_uuid' => $leadUuid,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'ip_address' => $ipAddress
            ]);
            
            throw $e;
        }
    }

    /**
     * Score lead with AI and save to database cache
     * This is called automatically after lead creation
     *
     * @param Lead $lead
     * @return void
     */
    private function scoreLeadAsync(Lead $lead): void
    {
        // Convert Lead entity to LeadItemDto for scoring
        $leadDto = $this->convertLeadToDto($lead);

        // Score the lead with AI
        $result = $this->leadScoringService->score($leadDto);

        // Save score to database cache
        $lead->setAiScore($result->score);
        $lead->setAiCategory($result->category);
        $lead->setAiReasoning($result->reasoning);
        $lead->setAiSuggestions($result->suggestions);
        $lead->setAiScoredAt(new \DateTime());

        $this->entityManager->persist($lead);
        $this->entityManager->flush();

        $this->logger?->info('Lead scored successfully', [
            'lead_id' => $lead->getId(),
            'score' => $result->score,
            'category' => $result->category
        ]);
    }

    /**
     * Convert Lead entity to LeadItemDto for scoring
     *
     * @param Lead $lead
     * @return \App\DTO\LeadItemDto
     */
    private function convertLeadToDto(Lead $lead): \App\DTO\LeadItemDto
    {
        $customer = $lead->getCustomer();
        $property = $lead->getProperty();

        $customerDto = new \App\DTO\CustomerDto(
            id: $customer->getId(),
            email: $customer->getEmail(),
            phone: $customer->getPhone(),
            firstName: $customer->getFirstName(),
            lastName: $customer->getLastName(),
            createdAt: $customer->getCreatedAt()
        );

        $propertyDto = new \App\DTO\PropertyDto(
            propertyId: $property?->getPropertyId(),
            developmentId: $property?->getDevelopmentId(),
            partnerId: $property?->getPartnerId(),
            propertyType: $property?->getPropertyType(),
            price: $property?->getPrice(),
            location: $property?->getLocation(),
            city: $property?->getCity()
        );

        return new \App\DTO\LeadItemDto(
            id: $lead->getId(),
            leadUuid: $lead->getLeadUuid(),
            status: $lead->getStatus(),
            statusLabel: \App\DTO\LeadItemDto::getStatusLabel($lead->getStatus()),
            createdAt: $lead->getCreatedAt(),
            customer: $customerDto,
            applicationName: $lead->getApplicationName(),
            property: $propertyDto,
            cdpDeliveryStatus: 'pending'
        );
    }

    /**
     * Update lead status by UUID
     * Implements full transaction: validation, status update, logging event
     *
     * @param string $leadUuid UUID of lead to update
     * @param UpdateLeadRequest $request Request with new status
     * @param string|null $ipAddress Client IP address for logging
     * @param string|null $userAgent Client user agent for logging
     * @return Lead Updated lead entity
     * @throws LeadNotFoundException If lead with given UUID doesn't exist
     * @throws ValidationException If status is invalid
     * @throws \Exception On database errors
     */
    public function updateLeadStatus(
        string $leadUuid,
        UpdateLeadRequest $request,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): Lead {
        // Validate status
        $validStatuses = ['new', 'contacted', 'qualified', 'converted', 'rejected'];
        if (!in_array($request->status, $validStatuses, true)) {
            throw new ValidationException([
                'status' => sprintf('Invalid status "%s". Allowed values: %s', $request->status, implode(', ', $validStatuses))
            ]);
        }

        // Start transaction
        $this->entityManager->beginTransaction();

        try {
            // Find lead by UUID
            $lead = $this->findByUuid($leadUuid);
            if ($lead === null) {
                throw new LeadNotFoundException($leadUuid);
            }

            // Store old status for logging
            $oldStatus = $lead->getStatus();

            // Update status
            $lead->setStatus($request->status);
            $lead->setUpdatedAt(new \DateTime());

            // Persist changes
            $this->entityManager->persist($lead);
            $this->entityManager->flush();

            // Log status change event
            $this->eventService->logLeadStatusChanged(
                $lead,
                $oldStatus,
                $request->status,
                null, // userId will be set by EventService from security context
                $ipAddress,
                $userAgent
            );

            // Commit transaction
            $this->entityManager->commit();

            // Log success
            if ($this->logger !== null) {
                $this->logger->info('Lead status updated successfully', [
                    'lead_uuid' => $leadUuid,
                    'old_status' => $oldStatus,
                    'new_status' => $request->status,
                    'ip_address' => $ipAddress,
                ]);
            }

            return $lead;

        } catch (\Exception $e) {
            // Rollback transaction on any error
            $this->entityManager->rollback();

            // Log error
            if ($this->logger !== null) {
                $this->logger->error('Failed to update lead status', [
                    'lead_uuid' => $leadUuid,
                    'new_status' => $request->status,
                    'error' => $e->getMessage(),
                    'ip_address' => $ipAddress,
                ]);
            }

            throw $e;
        }
    }

    /**
     * Send lead to CDP systems (called by CDPLeadMessageHandler)
     * This method is invoked asynchronously after message is received from RabbitMQ queue
     *
     * @param Lead $lead
     * @return void
     */
    public function sendLeadToCDP(Lead $lead): void
    {
        try {
            $this->logger?->info('Sending lead to CDP systems', [
                'lead_id' => $lead->getId(),
                'lead_uuid' => $lead->getLeadUuid(),
            ]);

            $this->cdpDeliveryService->sendLeadToCDP($lead);

            $this->logger?->info('Lead sent to CDP systems successfully', [
                'lead_id' => $lead->getId(),
                'lead_uuid' => $lead->getLeadUuid(),
            ]);
        } catch (\Exception $e) {
            // Errors are already logged in CDPDeliveryService
            $this->logger?->error('Failed to send lead to CDP systems', [
                'lead_id' => $lead->getId(),
                'lead_uuid' => $lead->getLeadUuid(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}

