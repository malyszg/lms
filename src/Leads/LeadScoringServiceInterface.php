<?php

declare(strict_types=1);

namespace App\Leads;

use App\DTO\LeadItemDto;
use App\DTO\LeadScoreResult;

/**
 * Lead Scoring Service Interface
 * 
 * Defines contract for AI-based lead scoring functionality.
 * Provides methods to score individual leads or batches.
 */
interface LeadScoringServiceInterface
{
    /**
     * Score a single lead using AI analysis
     * 
     * Analyzes lead data and returns a score (0-100), category (hot/warm/cold),
     * reasoning and suggested actions for call center.
     * 
     * If AI is unavailable, returns fallback score based on simple heuristics.
     * 
     * @param LeadItemDto $lead Lead to score
     * @return LeadScoreResult Score result with category, reasoning and suggestions
     */
    public function score(LeadItemDto $lead): LeadScoreResult;
    
    /**
     * Score multiple leads in batch
     * 
     * Processes multiple leads with rate limiting to respect API quotas.
     * Uses delay between requests to avoid hitting rate limits.
     * 
     * @param array<LeadItemDto> $leads Array of leads to score
     * @return array<int, LeadScoreResult> Map of lead ID to score result
     * @throws \InvalidArgumentException When array contains non-LeadItemDto items
     */
    public function scoreBatch(array $leads): array;
}

