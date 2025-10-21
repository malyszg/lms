<?php

declare(strict_types=1);

namespace App\Leads;

use App\DTO\LeadItemDto;
use App\DTO\LeadScoreResult;
use App\Exception\GeminiApiException;
use App\Infrastructure\AI\GeminiClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Gemini-based Lead Scoring Service Implementation
 * 
 * Uses Google Gemini AI to analyze and score leads based on multiple factors:
 * - Contact quality (email type, completeness)
 * - Budget and preferences
 * - Source application quality
 * - Lead freshness
 */
class GeminiLeadScoringService implements LeadScoringServiceInterface
{
    /**
     * @param GeminiClientInterface $geminiClient Gemini API client
     * @param LoggerInterface|null $logger Optional logger
     */
    public function __construct(
        private readonly GeminiClientInterface $geminiClient,
        private readonly ?LoggerInterface $logger = null
    ) {}

    /**
     * {@inheritdoc}
     */
    public function score(LeadItemDto $lead): LeadScoreResult
    {
        try {
            $prompt = $this->buildPrompt($lead);
            $schema = $this->buildResponseSchema();
            $systemInstruction = $this->getSystemInstruction();

            $response = $this->geminiClient->generateStructuredContent(
                prompt: $prompt,
                responseSchema: $schema,
                systemInstruction: $systemInstruction
            );

            $this->logger?->info('Lead scored successfully by AI', [
                'lead_id' => $lead->id,
                'score' => $response['score'] ?? null,
                'category' => $response['category'] ?? null
            ]);

            return $this->mapToResultDto($response);

        } catch (GeminiApiException $e) {
            $this->logger?->error('Lead scoring failed, using fallback', [
                'lead_id' => $lead->id,
                'error' => $e->getMessage(),
                'error_code' => $e->getErrorCode()
            ]);

            return $this->getFallbackScore($lead);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function scoreBatch(array $leads): array
    {
        $results = [];

        foreach ($leads as $lead) {
            if (!$lead instanceof LeadItemDto) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'All items must be LeadItemDto instances, got: %s',
                        get_debug_type($lead)
                    )
                );
            }

            $results[$lead->id] = $this->score($lead);
            
            // Respect rate limits - sleep between requests
            // 100ms delay = max 10 req/sec (well below 15/min limit)
            usleep(100000);
        }

        $this->logger?->info('Batch scoring completed', [
            'total_leads' => count($leads),
            'successful' => count($results)
        ]);

        return $results;
    }

    /**
     * Build prompt from lead data
     * 
     * Creates a structured prompt with customer data, preferences and context
     * for AI analysis.
     * 
     * @param LeadItemDto $lead Lead to analyze
     * @return string Complete prompt for AI
     */
    private function buildPrompt(LeadItemDto $lead): string
    {
        $parts = [
            "Oceń potencjał leada na podstawie poniższych danych:\n",
            "\nDANE KLIENTA:",
        ];

        // Email analysis
        $emailDomain = $this->getEmailDomain($lead->customer->email);
        $emailType = $this->isBusinessEmail($emailDomain) ? 'firmowy' : 'prywatny';
        $parts[] = sprintf("- Email: %s (domena %s)", $emailType, $emailDomain);

        // Phone
        $parts[] = sprintf("- Telefon: %s", $this->formatPhoneForDisplay($lead->customer->phone));

        // Name
        if ($lead->customer->firstName || $lead->customer->lastName) {
            $parts[] = sprintf(
                "- Imię i nazwisko: %s %s",
                $lead->customer->firstName ?? '',
                $lead->customer->lastName ?? ''
            );
        }

        // Property info
        if ($lead->property) {
            $parts[] = "\nPREFERENCJE:";
            
            if ($lead->property->price) {
                $parts[] = sprintf("- Budżet: %.0f PLN", $lead->property->price);
            }
            
            if ($lead->property->location) {
                $parts[] = sprintf("- Lokalizacja: %s", $lead->property->location);
            }
        }

        // Context
        $parts[] = "\nKONTEKST:";
        $parts[] = sprintf("- Źródło: %s", $lead->applicationName);
        $parts[] = sprintf("- Data zgłoszenia: %s", $lead->createdAt->format('Y-m-d H:i'));

        $parts[] = "\nPrzypisz score (0-100), kategorię (hot/warm/cold), uzasadnienie i 2-4 konkretne sugestie działań dla call center.";

        return implode("\n", $parts);
    }

    /**
     * Build JSON Schema for AI response
     * 
     * Defines the expected structure and validation rules for AI response.
     * 
     * @return array<string, mixed> JSON Schema
     */
    private function buildResponseSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'score' => [
                    'type' => 'integer',
                    'minimum' => 0,
                    'maximum' => 100,
                    'description' => 'Ocena potencjału leada od 0 do 100'
                ],
                'category' => [
                    'type' => 'string',
                    'enum' => ['hot', 'warm', 'cold'],
                    'description' => 'Kategoria: hot (71-100), warm (41-70), cold (0-40)'
                ],
                'reasoning' => [
                    'type' => 'string',
                    'description' => 'Zwięzłe uzasadnienie (2-3 zdania)'
                ],
                'suggestions' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'minItems' => 2,
                    'maxItems' => 4,
                    'description' => 'Lista konkretnych sugestii działań'
                ]
            ],
            'required' => ['score', 'category', 'reasoning', 'suggestions']
        ];
    }

    /**
     * Get system instruction for AI
     * 
     * Provides AI with expert knowledge and scoring criteria for
     * real estate leads in Poland.
     * 
     * @return string System instruction
     */
    private function getSystemInstruction(): string
    {
        return <<<INSTRUCTION
Jesteś ekspertem od oceny leadów nieruchomościowych w Polsce.

KRYTERIA OCENY:
1. Jakość kontaktu (40%):
   - Email firmowy = wyższy score (bardziej poważny klient)
   - Kompletność danych kontaktowych
   
2. Budżet i lokalizacja (30%):
   - Realny budżet dla danej lokalizacji
   - Atrakcyjność lokalizacji
   
3. Źródło (20%):
   - Morizon/Gratka = wyższy score (popularne portale)
   - Homsters = umiarkowany score
   
4. Świeżość (10%):
   - Lead z ostatnich 24h = wyższy score

KATEGORIE:
- HOT (71-100): Pilny kontakt w ciągu 1-2h, wysoki potencjał zakupu
- WARM (41-70): Kontakt w ciągu 24h, umiarkowany potencjał
- COLD (0-40): Niski priorytet, podstawowa weryfikacja

SUGESTIE:
- Konkretne: "Zadzwoń przed 15:00" zamiast "Skontaktuj się szybko"
- Praktyczne: "Zaproponuj 3 oferty w okolicy Mokotowa"
- 2-4 sugestie maksymalnie

Odpowiadaj ZAWSZE po polsku. Bądź zwięzły i konkretny.
INSTRUCTION;
    }

    /**
     * Map AI response to LeadScoreResult DTO
     * 
     * @param array<string, mixed> $response AI response
     * @return LeadScoreResult Mapped result
     */
    private function mapToResultDto(array $response): LeadScoreResult
    {
        return new LeadScoreResult(
            score: $response['score'],
            category: $response['category'],
            reasoning: $response['reasoning'],
            suggestions: $response['suggestions']
        );
    }

    /**
     * Get fallback score when AI is unavailable
     * 
     * Uses simple heuristics based on:
     * - Business email (+10)
     * - Complete customer data (+10)
     * - Fresh lead from last 24h (+10)
     * 
     * @param LeadItemDto $lead Lead to score
     * @return LeadScoreResult Fallback score result
     */
    private function getFallbackScore(LeadItemDto $lead): LeadScoreResult
    {
        // Prosta heurystyka gdy AI nie działa
        $score = 50; // bazowy
        
        // +10 za email firmowy
        if ($this->isBusinessEmail($this->getEmailDomain($lead->customer->email))) {
            $score += 10;
        }
        
        // +10 za kompletne dane
        if ($lead->customer->firstName && $lead->customer->lastName) {
            $score += 10;
        }
        
        // +10 za świeży lead (ostatnie 24h)
        $now = new \DateTime();
        $diff = $now->diff($lead->createdAt);
        if ($diff->days === 0) {
            $score += 10;
        }

        $category = match(true) {
            $score >= 71 => 'hot',
            $score >= 41 => 'warm',
            default => 'cold'
        };

        return new LeadScoreResult(
            score: $score,
            category: $category,
            reasoning: 'Automatyczna ocena (AI niedostępne) - wymagana ręczna weryfikacja',
            suggestions: [
                'Skontaktuj się z klientem telefonicznie',
                'Zweryfikuj preferencje ręcznie'
            ]
        );
    }

    /**
     * Extract email domain from email address
     * 
     * @param string $email Email address
     * @return string Domain part
     */
    private function getEmailDomain(string $email): string
    {
        $parts = explode('@', $email);
        return $parts[1] ?? '';
    }

    /**
     * Check if email is business email (not consumer)
     * 
     * @param string $domain Email domain
     * @return bool True if business email
     */
    private function isBusinessEmail(string $domain): bool
    {
        $consumerDomains = ['gmail.com', 'wp.pl', 'onet.pl', 'interia.pl', 'o2.pl', 'yahoo.com', 'outlook.com'];
        return !in_array(strtolower($domain), $consumerDomains, true);
    }

    /**
     * Format phone number for display
     * 
     * Removes all non-numeric characters except plus sign.
     * 
     * @param string $phone Phone number
     * @return string Formatted phone
     */
    private function formatPhoneForDisplay(string $phone): string
    {
        return preg_replace('/[^0-9+]/', '', $phone) ?? $phone;
    }
}

