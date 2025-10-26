<?php

declare(strict_types=1);

namespace App\ApiClient;

use App\Infrastructure\Config\CDPSystemConfig;
use App\Infrastructure\ExponentialBackoffCalculator;
use App\Leads\EventServiceInterface;
use App\Leads\FailedDeliveryServiceInterface;
use App\Model\FailedDelivery;
use App\Model\Lead;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;

/**
 * CDP Delivery Service
 * Handles sending leads to CDP systems with retry mechanism
 */
class CDPDeliveryService implements CDPDeliveryServiceInterface
{
    public function __construct(
        private readonly EventServiceInterface $eventService,
        private readonly FailedDeliveryServiceInterface $failedDeliveryService,
        private readonly CDPPayloadTransformerInterface $payloadTransformer,
        private readonly CDPSystemConfig $systemConfig,
        private readonly ExponentialBackoffCalculator $backoffCalculator,
        private readonly Client $httpClient,
        private readonly ?LoggerInterface $logger = null
    ) {}

    /**
     * Send lead to all configured CDP systems
     *
     * @param Lead $lead
     * @return void
     */
    public function sendLeadToCDP(Lead $lead): void
    {
        foreach ($this->systemConfig->getConfiguredSystems() as $cdpSystem) {
            // Skip disabled systems
            if (!$this->systemConfig->isEnabled($cdpSystem)) {
                $this->eventService->logCdpDeliverySkipped($lead, $cdpSystem, 'System disabled');
                
                $this->logger?->info('CDP system disabled, skipping', [
                    'lead_id' => $lead->getId(),
                    'cdp_system' => $cdpSystem,
                ]);
                continue;
            }

            try {
                $this->sendToSingleCDP($lead, $cdpSystem);

                // Log success
                $this->eventService->logCdpDeliverySuccess($lead, $cdpSystem);

                $this->logger?->info('Lead sent to CDP system', [
                    'lead_id' => $lead->getId(),
                    'lead_uuid' => $lead->getLeadUuid(),
                    'cdp_system' => $cdpSystem,
                    'status' => 'success'
                ]);

            } catch (\Exception $e) {
                $errorCode = $e->getCode() > 0 ? (string)$e->getCode() : null;

                // Create failed delivery record
                $this->failedDeliveryService->createFailedDelivery(
                    $lead,
                    $cdpSystem,
                    $e->getMessage(),
                    $errorCode
                );

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
                    'error' => $e->getMessage(),
                    'error_code' => $errorCode
                ]);
            }
        }
    }

    /**
     * Send lead to single CDP system
     *
     * @param Lead $lead
     * @param string $cdpSystem CDP system name
     * @return void
     * @throws \Exception On HTTP error
     */
    public function sendToSingleCDP(Lead $lead, string $cdpSystem): void
    {
        // Check if system is enabled
        if (!$this->systemConfig->isEnabled($cdpSystem)) {
            throw new \RuntimeException("CDP system {$cdpSystem} is disabled");
        }

        // Build payload
        $payload = $this->buildPayload($lead, $cdpSystem);

        // Get API URL and key
        $apiUrl = $this->systemConfig->getApiUrl($cdpSystem);
        $apiKey = $this->systemConfig->getApiKey($cdpSystem);

        // Send HTTP request
        $this->sendHttpRequest($apiUrl, $payload, $apiKey);
    }

    /**
     * Retry failed delivery
     *
     * @param FailedDelivery $failedDelivery Delivery to retry
     * @return void
     */
    public function retryFailedDelivery(FailedDelivery $failedDelivery): void
    {
        if (!$failedDelivery->canRetry()) {
            throw new \RuntimeException("Cannot retry delivery: retry limit exceeded or status invalid");
        }

        $lead = $failedDelivery->getLead();
        $cdpSystem = $failedDelivery->getCdpSystemName();

        try {
            // Mark as retrying
            $failedDelivery->setStatus('retrying');
            $this->failedDeliveryService->markAsResolved($failedDelivery); // Just flush status change

            // Try to send
            $this->sendToSingleCDP($lead, $cdpSystem);

            // Success - mark as resolved
            $failedDelivery->setStatus('resolved');
            $failedDelivery->setResolvedAt(new \DateTime());
            $this->failedDeliveryService->markAsResolved($failedDelivery);

            $this->eventService->logCdpRetrySuccess($failedDelivery);

            $this->logger?->info('Failed delivery retry successful', [
                'failed_delivery_id' => $failedDelivery->getId(),
                'lead_id' => $lead->getId(),
                'cdp_system' => $cdpSystem,
            ]);

        } catch (\Exception $e) {
            // Increment retry count
            $retryConfig = $this->systemConfig->getRetryConfig($cdpSystem);
            $newRetryCount = $failedDelivery->getRetryCount() + 1;

            if ($newRetryCount >= ($retryConfig['max_retries'] ?? 3)) {
                // Max retries exceeded - mark as final failure
                $failedDelivery->setStatus('failed');
                $this->failedDeliveryService->markAsFailed($failedDelivery);
                
                $this->eventService->logCdpRetryFailed($failedDelivery, $e->getMessage());
                
                $this->logger?->error('Max retries exceeded for failed delivery', [
                    'failed_delivery_id' => $failedDelivery->getId(),
                    'lead_id' => $lead->getId(),
                    'cdp_system' => $cdpSystem,
                ]);
            } else {
                // Schedule next retry
                $nextRetryAt = $this->backoffCalculator->calculateWithConfig(
                    $newRetryCount,
                    $retryConfig
                );

                $failedDelivery->setStatus('pending');
                $this->failedDeliveryService->updateRetryInfo(
                    $failedDelivery,
                    $newRetryCount,
                    $nextRetryAt
                );

                $this->eventService->logCdpRetryFailed($failedDelivery, $e->getMessage());
                
                $this->logger?->info('Scheduled next retry for failed delivery', [
                    'failed_delivery_id' => $failedDelivery->getId(),
                    'lead_id' => $lead->getId(),
                    'cdp_system' => $cdpSystem,
                    'retry_count' => $newRetryCount,
                    'next_retry_at' => $nextRetryAt->format('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    /**
     * Build payload for CDP system
     *
     * @param Lead $lead
     * @param string $cdpSystem
     * @return array<string, mixed>
     */
    private function buildPayload(Lead $lead, string $cdpSystem): array
    {
        return match ($cdpSystem) {
            'SalesManago' => $this->payloadTransformer->transformForSalesManago($lead),
            'Murapol' => $this->payloadTransformer->transformForMurapol($lead),
            'DomDevelopment' => $this->payloadTransformer->transformForDomDevelopment($lead),
            default => throw new \InvalidArgumentException("Unknown CDP system: {$cdpSystem}"),
        };
    }

    /**
     * Send HTTP request to CDP API
     *
     * @param string $url API URL
     * @param array<string, mixed> $payload Request payload
     * @param string $apiKey API key
     * @return void
     * @throws \Exception On HTTP error
     */
    private function sendHttpRequest(string $url, array $payload, string $apiKey): void
    {
        try {
            $response = $this->httpClient->post($url, [
                'json' => $payload,
                'headers' => [
                    'Authorization' => "Bearer {$apiKey}",
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 30, // 30 seconds timeout
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode < 200 || $statusCode >= 300) {
                throw new \RuntimeException(
                    "CDP API returned status code: {$statusCode}"
                );
            }

        } catch (GuzzleException $e) {
            throw new \RuntimeException(
                "Failed to send to CDP API: {$e->getMessage()}",
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Get list of configured CDP systems
     *
     * @return array<string>
     */
    public function getConfiguredSystems(): array
    {
        return $this->systemConfig->getConfiguredSystems();
    }
}































