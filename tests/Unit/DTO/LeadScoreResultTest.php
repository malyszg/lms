<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\LeadScoreResult;
use PHPUnit\Framework\TestCase;

/**
 * Test for LeadScoreResult DTO
 */
class LeadScoreResultTest extends TestCase
{
    public function testConstructorWithValidData(): void
    {
        $result = new LeadScoreResult(
            score: 85,
            category: 'hot',
            reasoning: 'High quality lead with business email',
            suggestions: ['Call immediately', 'Prepare offers']
        );

        $this->assertEquals(85, $result->score);
        $this->assertEquals('hot', $result->category);
        $this->assertEquals('High quality lead with business email', $result->reasoning);
        $this->assertEquals(['Call immediately', 'Prepare offers'], $result->suggestions);
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        $result = new LeadScoreResult(
            score: 60,
            category: 'warm',
            reasoning: 'Moderate potential',
            suggestions: ['Contact within 24h']
        );

        $array = $result->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('score', $array);
        $this->assertArrayHasKey('category', $array);
        $this->assertArrayHasKey('reasoning', $array);
        $this->assertArrayHasKey('suggestions', $array);
        $this->assertEquals(60, $array['score']);
        $this->assertEquals('warm', $array['category']);
    }

    public function testThrowsExceptionWhenScoreTooLow(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Score must be between 0 and 100');

        new LeadScoreResult(
            score: -1,
            category: 'cold',
            reasoning: 'Test',
            suggestions: ['Test']
        );
    }

    public function testThrowsExceptionWhenScoreTooHigh(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Score must be between 0 and 100');

        new LeadScoreResult(
            score: 101,
            category: 'hot',
            reasoning: 'Test',
            suggestions: ['Test']
        );
    }

    public function testThrowsExceptionWhenInvalidCategory(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Category must be one of');

        new LeadScoreResult(
            score: 50,
            category: 'invalid',
            reasoning: 'Test',
            suggestions: ['Test']
        );
    }

    public function testThrowsExceptionWhenReasoningEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Reasoning cannot be empty');

        new LeadScoreResult(
            score: 50,
            category: 'warm',
            reasoning: '',
            suggestions: ['Test']
        );
    }

    public function testThrowsExceptionWhenSuggestionsEmpty(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Suggestions cannot be empty');

        new LeadScoreResult(
            score: 50,
            category: 'warm',
            reasoning: 'Test reasoning',
            suggestions: []
        );
    }

    public function testAcceptsAllValidCategories(): void
    {
        $categories = ['hot', 'warm', 'cold'];

        foreach ($categories as $category) {
            $result = new LeadScoreResult(
                score: 50,
                category: $category,
                reasoning: 'Test',
                suggestions: ['Test']
            );

            $this->assertEquals($category, $result->category);
        }
    }

    public function testAcceptsBoundaryScoreValues(): void
    {
        // Test minimum score (0)
        $result1 = new LeadScoreResult(
            score: 0,
            category: 'cold',
            reasoning: 'Test',
            suggestions: ['Test']
        );
        $this->assertEquals(0, $result1->score);

        // Test maximum score (100)
        $result2 = new LeadScoreResult(
            score: 100,
            category: 'hot',
            reasoning: 'Test',
            suggestions: ['Test']
        );
        $this->assertEquals(100, $result2->score);
    }
}

