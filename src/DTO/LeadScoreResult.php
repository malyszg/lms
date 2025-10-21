<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Lead Score Result DTO
 * 
 * Represents the result of AI-based lead scoring analysis.
 * Contains score, category, reasoning and suggested actions.
 */
class LeadScoreResult
{
    /**
     * @param int $score Score from 0 to 100
     * @param string $category Category: hot (71-100), warm (41-70), cold (0-40)
     * @param string $reasoning AI explanation for the score
     * @param array<string> $suggestions List of suggested actions for call center
     */
    public function __construct(
        public readonly int $score,
        public readonly string $category,
        public readonly string $reasoning,
        public readonly array $suggestions
    ) {
        $this->validate();
    }

    /**
     * Validate the DTO data
     * 
     * @throws \InvalidArgumentException When validation fails
     * @return void
     */
    private function validate(): void
    {
        if ($this->score < 0 || $this->score > 100) {
            throw new \InvalidArgumentException(
                sprintf('Score must be between 0 and 100, got: %d', $this->score)
            );
        }

        $allowedCategories = ['hot', 'warm', 'cold'];
        if (!in_array($this->category, $allowedCategories, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Category must be one of: %s, got: %s',
                    implode(', ', $allowedCategories),
                    $this->category
                )
            );
        }

        if (empty($this->reasoning)) {
            throw new \InvalidArgumentException('Reasoning cannot be empty');
        }

        if (empty($this->suggestions)) {
            throw new \InvalidArgumentException('Suggestions cannot be empty');
        }
    }

    /**
     * Convert to array representation
     * 
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'score' => $this->score,
            'category' => $this->category,
            'reasoning' => $this->reasoning,
            'suggestions' => $this->suggestions
        ];
    }
}

