<?php

declare(strict_types=1);

namespace App\Tests\Unit\Leads;

use App\Leads\GeminiLeadScoringService;
use App\Infrastructure\AI\GeminiClientInterface;
use App\DTO\LeadItemDto;
use App\DTO\CustomerDto;
use App\DTO\PropertyDto;
use App\DTO\LeadScoreResult;
use App\Exception\GeminiApiException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Test for GeminiLeadScoringService
 */
class GeminiLeadScoringServiceTest extends TestCase
{
    private GeminiClientInterface $geminiClient;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->geminiClient = $this->createMock(GeminiClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testScoreLeadSuccess(): void
    {
        $this->geminiClient
            ->expects($this->once())
            ->method('generateStructuredContent')
            ->willReturn([
                'score' => 85,
                'category' => 'hot',
                'reasoning' => 'High quality lead with business email',
                'suggestions' => ['Call immediately', 'Prepare offers']
            ]);

        $service = new GeminiLeadScoringService($this->geminiClient, $this->logger);

        $lead = $this->createTestLead();
        $result = $service->score($lead);

        $this->assertInstanceOf(LeadScoreResult::class, $result);
        $this->assertEquals(85, $result->score);
        $this->assertEquals('hot', $result->category);
        $this->assertEquals('High quality lead with business email', $result->reasoning);
        $this->assertCount(2, $result->suggestions);
    }

    public function testScoreUsesPromptWithLeadData(): void
    {
        $this->geminiClient
            ->expects($this->once())
            ->method('generateStructuredContent')
            ->with(
                $this->stringContains('Oceń potencjał leada'),
                $this->isType('array'),
                $this->stringContains('Jesteś ekspertem')
            )
            ->willReturn([
                'score' => 75,
                'category' => 'hot',
                'reasoning' => 'Test',
                'suggestions' => ['Action 1', 'Action 2']
            ]);

        $service = new GeminiLeadScoringService($this->geminiClient, $this->logger);

        $lead = $this->createTestLead();
        $service->score($lead);
    }

    public function testScoreReturnsFallbackOnGeminiException(): void
    {
        $this->geminiClient
            ->expects($this->once())
            ->method('generateStructuredContent')
            ->willThrowException(new GeminiApiException(
                'API Error',
                'GEMINI_API_ERROR',
                500
            ));

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Lead scoring failed, using fallback',
                $this->isType('array')
            );

        $service = new GeminiLeadScoringService($this->geminiClient, $this->logger);

        $lead = $this->createTestLead();
        $result = $service->score($lead);

        $this->assertInstanceOf(LeadScoreResult::class, $result);
        $this->assertStringContainsString('AI niedostępne', $result->reasoning);
        $this->assertIsInt($result->score);
        $this->assertGreaterThanOrEqual(0, $result->score);
        $this->assertLessThanOrEqual(100, $result->score);
    }

    public function testFallbackScoreIncreasesForBusinessEmail(): void
    {
        $this->geminiClient
            ->method('generateStructuredContent')
            ->willThrowException(new GeminiApiException('Error', 'ERROR', 500));

        $service = new GeminiLeadScoringService($this->geminiClient);

        $dummyProperty = new PropertyDto(null, null, null, null, null, null, null);

        // Lead with business email
        $businessLead = new LeadItemDto(
            id: 1,
            leadUuid: 'test-uuid',
            status: 'new',
            statusLabel: 'Nowy',
            createdAt: new \DateTime(),
            customer: new CustomerDto(1, 'test@company.com', '+48123', null, null, new \DateTime()),
            applicationName: 'morizon',
            property: $dummyProperty,
            cdpDeliveryStatus: 'pending'
        );

        // Lead with consumer email
        $consumerLead = new LeadItemDto(
            id: 2,
            leadUuid: 'test-uuid-2',
            status: 'new',
            statusLabel: 'Nowy',
            createdAt: new \DateTime(),
            customer: new CustomerDto(2, 'test@gmail.com', '+48123', null, null, new \DateTime()),
            applicationName: 'morizon',
            property: $dummyProperty,
            cdpDeliveryStatus: 'pending'
        );

        $businessResult = $service->score($businessLead);
        $consumerResult = $service->score($consumerLead);

        $this->assertGreaterThan($consumerResult->score, $businessResult->score);
    }

    public function testFallbackScoreIncreasesForCompleteData(): void
    {
        $this->geminiClient
            ->method('generateStructuredContent')
            ->willThrowException(new GeminiApiException('Error', 'ERROR', 500));

        $service = new GeminiLeadScoringService($this->geminiClient);

        $dummyProperty = new PropertyDto(null, null, null, null, null, null, null);

        // Lead with complete data
        $completeLead = new LeadItemDto(
            id: 1,
            leadUuid: 'test-uuid',
            status: 'new',
            statusLabel: 'Nowy',
            createdAt: new \DateTime(),
            customer: new CustomerDto(1, 'test@example.com', '+48123', 'Jan', 'Kowalski', new \DateTime()),
            applicationName: 'morizon',
            property: $dummyProperty,
            cdpDeliveryStatus: 'pending'
        );

        // Lead without names
        $incompleteLead = new LeadItemDto(
            id: 2,
            leadUuid: 'test-uuid-2',
            status: 'new',
            statusLabel: 'Nowy',
            createdAt: new \DateTime(),
            customer: new CustomerDto(2, 'test@example.com', '+48123', null, null, new \DateTime()),
            applicationName: 'morizon',
            property: $dummyProperty,
            cdpDeliveryStatus: 'pending'
        );

        $completeResult = $service->score($completeLead);
        $incompleteResult = $service->score($incompleteLead);

        $this->assertGreaterThan($incompleteResult->score, $completeResult->score);
    }

    public function testScoreBatchProcessesMultipleLeads(): void
    {
        $this->geminiClient
            ->expects($this->exactly(2))
            ->method('generateStructuredContent')
            ->willReturn([
                'score' => 75,
                'category' => 'hot',
                'reasoning' => 'Test',
                'suggestions' => ['Action 1']
            ]);

        $service = new GeminiLeadScoringService($this->geminiClient, $this->logger);

        $dummyProperty = new PropertyDto(null, null, null, null, null, null, null);

        $leads = [
            new LeadItemDto(
                1, 'uuid1', 'new', 'Nowy', new \DateTime(), 
                new CustomerDto(1, 'test1@example.com', '+48111', 'Jan', 'K', new \DateTime()),
                'morizon', $dummyProperty, 'pending'
            ),
            new LeadItemDto(
                2, 'uuid2', 'new', 'Nowy', new \DateTime(),
                new CustomerDto(2, 'test2@example.com', '+48222', 'Anna', 'N', new \DateTime()),
                'gratka', $dummyProperty, 'pending'
            )
        ];

        $results = $service->scoreBatch($leads);

        $this->assertCount(2, $results);
        $this->assertArrayHasKey(1, $results);
        $this->assertArrayHasKey(2, $results);
        $this->assertInstanceOf(LeadScoreResult::class, $results[1]);
        $this->assertInstanceOf(LeadScoreResult::class, $results[2]);
    }

    public function testScoreBatchThrowsExceptionOnInvalidInput(): void
    {
        $this->expectException(\TypeError::class);

        $service = new GeminiLeadScoringService($this->geminiClient);

        $dummyProperty = new PropertyDto(null, null, null, null, null, null, null);
        
        $invalidLeads = [
            $this->createTestLead(),
            new \stdClass(), // Invalid item - causes TypeError when trying to access properties
        ];

        $service->scoreBatch($invalidLeads);
    }

    public function testScoreLogsSuccessfulScoring(): void
    {
        $this->geminiClient
            ->method('generateStructuredContent')
            ->willReturn([
                'score' => 85,
                'category' => 'hot',
                'reasoning' => 'Test',
                'suggestions' => ['Action']
            ]);

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with(
                'Lead scored successfully by AI',
                $this->callback(function($context) {
                    return isset($context['lead_id']) 
                        && isset($context['score']) 
                        && isset($context['category']);
                })
            );

        $service = new GeminiLeadScoringService($this->geminiClient, $this->logger);

        $lead = $this->createTestLead();
        $service->score($lead);
    }

    private function createTestLead(): LeadItemDto
    {
        return new LeadItemDto(
            id: 1,
            leadUuid: 'test-uuid-123',
            status: 'new',
            statusLabel: 'Nowy',
            createdAt: new \DateTime('2025-10-14 10:30:00'),
            customer: new CustomerDto(
                id: 1,
                email: 'jan.kowalski@firma.pl',
                phone: '+48123456789',
                firstName: 'Jan',
                lastName: 'Kowalski',
                createdAt: new \DateTime('2025-10-01 10:00:00')
            ),
            applicationName: 'morizon',
            property: new PropertyDto(
                propertyId: 'prop123',
                developmentId: 'dev456',
                partnerId: 'partner789',
                propertyType: 'mieszkanie',
                price: 450000.0,
                location: 'Warszawa, Mokotów',
                city: 'Warszawa'
            ),
            cdpDeliveryStatus: 'pending'
        );
    }
}

