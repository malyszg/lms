<?php

declare(strict_types=1);

namespace App\Command;

use App\Leads\LeadScoringServiceInterface;
use App\Model\Lead;
use App\Service\LeadViewServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:score-leads',
    description: 'Score leads using AI (Google Gemini) and cache results in database'
)]
class ScoreLeadsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LeadScoringServiceInterface $leadScoringService,
        private readonly LeadViewServiceInterface $leadViewService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'unscored-only',
                'u',
                InputOption::VALUE_NONE,
                'Score only leads that have not been scored yet'
            )
            ->addOption(
                'rescore',
                'r',
                InputOption::VALUE_NONE,
                'Re-score all leads (including already scored)'
            )
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_REQUIRED,
                'Limit number of leads to score',
                100
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $unscoredOnly = $input->getOption('unscored-only');
        $rescore = $input->getOption('rescore');
        $limit = (int) $input->getOption('limit');
        
        $io->title('AI Lead Scoring');
        
        // Build query
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('l', 'c', 'lp')
            ->from(Lead::class, 'l')
            ->leftJoin('l.customer', 'c')
            ->leftJoin('l.property', 'lp')
            ->setMaxResults($limit);
        
        if ($unscoredOnly || !$rescore) {
            $qb->where('l.aiScore IS NULL');
            $io->info(sprintf('Scoring unscored leads (max %d)...', $limit));
        } else {
            $io->info(sprintf('Re-scoring ALL leads (max %d)...', $limit));
        }
        
        $qb->orderBy('l.createdAt', 'DESC');
        
        $leads = $qb->getQuery()->getResult();
        
        if (empty($leads)) {
            $io->success('No leads to score!');
            return Command::SUCCESS;
        }
        
        $io->info(sprintf('Found %d leads to score', count($leads)));
        $io->newLine();
        
        $scored = 0;
        $failed = 0;
        
        $progressBar = $io->createProgressBar(count($leads));
        $progressBar->start();
        
        foreach ($leads as $lead) {
            /** @var Lead $lead */
            try {
                // Convert Lead entity to LeadItemDto for scoring
                $leadDto = $this->convertLeadToDto($lead);
                
                // Score the lead
                $result = $this->leadScoringService->score($leadDto);
                
                // Save to database
                $lead->setAiScore($result->score);
                $lead->setAiCategory($result->category);
                $lead->setAiReasoning($result->reasoning);
                $lead->setAiSuggestions($result->suggestions);
                $lead->setAiScoredAt(new \DateTime());
                
                $this->entityManager->persist($lead);
                $this->entityManager->flush();
                
                $scored++;
                
                // Rate limiting: 100ms delay between requests (max 10 req/sec)
                usleep(100000);
                
            } catch (\Exception $e) {
                $failed++;
                $io->error(sprintf(
                    'Failed to score lead %d: %s',
                    $lead->getId(),
                    $e->getMessage()
                ));
            }
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $io->newLine(2);
        
        // Summary
        $io->success(sprintf(
            'Scoring complete! Scored: %d, Failed: %d',
            $scored,
            $failed
        ));
        
        return Command::SUCCESS;
    }
    
    /**
     * Convert Lead entity to LeadItemDto for scoring
     * (Simplified version - just enough for AI scoring)
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
}

