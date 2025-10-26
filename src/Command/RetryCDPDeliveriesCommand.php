<?php

declare(strict_types=1);

namespace App\Command;

use App\ApiClient\CDPDeliveryServiceInterface;
use App\Leads\FailedDeliveryServiceInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Retry CDP Deliveries Command
 * Processes pending failed deliveries with retry mechanism
 */
#[AsCommand(
    name: 'app:retry-cdp-deliveries',
    description: 'Retry failed CDP deliveries that are ready for retry',
)]
class RetryCDPDeliveriesCommand extends Command
{
    public function __construct(
        private readonly FailedDeliveryServiceInterface $failedDeliveryService,
        private readonly CDPDeliveryServiceInterface $cdpDeliveryService,
        private readonly ?LoggerInterface $logger = null
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_OPTIONAL,
                'Maximum number of pending deliveries to process',
                100
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = (int)($input->getOption('limit') ?? 100);

        $io->title('Retry CDP Deliveries');

        try {
            // Get pending deliveries
            $pendingDeliveries = $this->failedDeliveryService->getPendingDeliveries($limit);

            if (empty($pendingDeliveries)) {
                $io->success('No pending deliveries to retry');
                return Command::SUCCESS;
            }

            $io->info(sprintf('Found %d pending delivery(ies) to retry', count($pendingDeliveries)));

            $successCount = 0;
            $failedCount = 0;

            foreach ($pendingDeliveries as $failedDelivery) {
                if (!$failedDelivery->shouldRetryNow()) {
                    continue;
                }

                try {
                    $this->cdpDeliveryService->retryFailedDelivery($failedDelivery);
                    $successCount++;

                    $this->logger?->info('Retry processed for failed delivery', [
                        'failed_delivery_id' => $failedDelivery->getId(),
                        'lead_id' => $failedDelivery->getLead()->getId(),
                        'cdp_system' => $failedDelivery->getCdpSystemName(),
                    ]);

                } catch (\Exception $e) {
                    $failedCount++;

                    $this->logger?->error('Failed to retry delivery', [
                        'failed_delivery_id' => $failedDelivery->getId(),
                        'lead_id' => $failedDelivery->getLead()->getId(),
                        'cdp_system' => $failedDelivery->getCdpSystemName(),
                        'error' => $e->getMessage(),
                    ]);

                    $io->warning(sprintf(
                        'Failed to retry delivery #%d: %s',
                        $failedDelivery->getId(),
                        $e->getMessage()
                    ));
                }
            }

            // Summary
            $io->newLine();
            $io->table(
                ['Status', 'Count'],
                [
                    ['Success', $successCount],
                    ['Failed', $failedCount],
                    ['Total', count($pendingDeliveries)],
                ]
            );

            $io->success('Retry process completed');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error(sprintf('Error processing retry: %s', $e->getMessage()));
            $this->logger?->error('Error in retry command', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }
}

