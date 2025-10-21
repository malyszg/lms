<?php

declare(strict_types=1);

namespace App\ApiClient;

use App\Leads\EventServiceInterface;
use App\Model\Lead;
use Psr\Log\LoggerInterface;

/**
 * CDP Delivery Service (MVP - Mock Implementation)
 * 
 * TODO for production:
 * - Implement RabbitMQ message publishing
 * - Create separate consumer for async processing
 * - Implement actual HTTP clients for each CDP system
 * - Add retry mechanism with exponential backoff
 * - Store failed deliveries in database
 */
class CDPDeliveryService implements CDPDeliveryServiceInterface
{
    private array $cdpSystems = [
        'SalesManago',
        'Murapol',
        'DomDevelopment'
    ];

    public function __construct(
        private readonly EventServiceInterface $eventService,
        private readonly ?LoggerInterface $logger = null
    ) {}

    /**
     * Send lead to all configured CDP systems (MVP - mock implementation)
     * In production, this should publish message to RabbitMQ
     *
     * @param Lead $lead
     * @return void
     */
    public function sendLeadToCDP(Lead $lead): void
    {
        // MVP: Just log that we would send to CDP
        // TODO: Implement actual RabbitMQ publishing
        
        foreach ($this->cdpSystems as $cdpSystem) {
            try {
                // Simulate CDP delivery (mock)
                $this->mockCDPDelivery($lead, $cdpSystem);

                // Log success
                $this->eventService->logCdpDeliverySuccess($lead, $cdpSystem);

                $this->logger?->info('Lead sent to CDP system (mock)', [
                    'lead_id' => $lead->getId(),
                    'lead_uuid' => $lead->getLeadUuid(),
                    'cdp_system' => $cdpSystem,
                    'status' => 'success'
                ]);

            } catch (\Exception $e) {
                // Log failure
                $this->eventService->logCdpDeliveryFailed(
                    $lead,
                    $cdpSystem,
                    $e->getMessage()
                );

                $this->logger?->error('Failed to send lead to CDP system', [
                    'lead_id' => $lead->getId(),
                    'lead_uuid' => $lead->getLeadUuid(),
                    'cdp_system' => $cdpSystem,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Get list of configured CDP systems
     *
     * @return array<string>
     */
    public function getConfiguredSystems(): array
    {
        return $this->cdpSystems;
    }

    /**
     * Mock CDP delivery (placeholder for actual implementation)
     * 
     * In production, this would:
     * 1. Build HTTP request with lead data
     * 2. Send to CDP system API endpoint
     * 3. Handle response and errors
     *
     * @param Lead $lead
     * @param string $cdpSystem
     * @return void
     */
    private function mockCDPDelivery(Lead $lead, string $cdpSystem): void
    {
        // MVP: Mock implementation - just simulate success
        // In production: Replace with actual Guzzle HTTP client calls
        
        // Simulate random failures (10% chance) for testing
        if (rand(1, 10) === 1) {
            throw new \RuntimeException(
                sprintf('Mock CDP delivery failure for %s', $cdpSystem)
            );
        }

        // Success - no exception thrown
    }
}































