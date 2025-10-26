<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure;

use App\Infrastructure\ExponentialBackoffCalculator;
use PHPUnit\Framework\TestCase;

/**
 * ExponentialBackoffCalculator Test
 * Tests exponential backoff delay calculations
 */
class ExponentialBackoffCalculatorTest extends TestCase
{
    private ExponentialBackoffCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new ExponentialBackoffCalculator();
    }

    public function testCalculateRetry1(): void
    {
        $initialDelay = 60; // 1 minute
        $multiplier = 2.0;
        
        $now = new \DateTime();
        $nextRetry = $this->calculator->calculate(1, $initialDelay, $multiplier);
        
        // Retry 1: 60 * 2^1 = 120 seconds (2 minutes)
        $difference = $nextRetry->getTimestamp() - $now->getTimestamp();
        
        // Should be approximately 120 seconds (allow 2 second variance)
        $this->assertGreaterThanOrEqual(118, $difference);
        $this->assertLessThanOrEqual(122, $difference);
    }

    public function testCalculateRetry2(): void
    {
        $initialDelay = 60; // 1 minute
        $multiplier = 2.0;
        
        $now = new \DateTime();
        $nextRetry = $this->calculator->calculate(2, $initialDelay, $multiplier);
        
        // Retry 2: 60 * 2^2 = 240 seconds (4 minutes)
        $difference = $nextRetry->getTimestamp() - $now->getTimestamp();
        
        // Should be approximately 240 seconds (allow 2 second variance)
        $this->assertGreaterThanOrEqual(238, $difference);
        $this->assertLessThanOrEqual(242, $difference);
    }

    public function testCalculateRetry3(): void
    {
        $initialDelay = 60; // 1 minute
        $multiplier = 2.0;
        
        $now = new \DateTime();
        $nextRetry = $this->calculator->calculate(3, $initialDelay, $multiplier);
        
        // Retry 3: 60 * 2^3 = 480 seconds (8 minutes)
        $difference = $nextRetry->getTimestamp() - $now->getTimestamp();
        
        // Should be approximately 480 seconds (allow 2 second variance)
        $this->assertGreaterThanOrEqual(478, $difference);
        $this->assertLessThanOrEqual(482, $difference);
    }

    public function testCalculateWithConfig(): void
    {
        $retryConfig = [
            'initial_delay_seconds' => 30,
            'backoff_multiplier' => 1.5,
        ];
        
        $nextRetry = $this->calculator->calculateWithConfig(2, $retryConfig);
        
        // Verify it returns a DateTimeInterface
        $this->assertInstanceOf(\DateTimeInterface::class, $nextRetry);
        
        // Verify it's in the future
        $now = new \DateTime();
        $this->assertGreaterThan($now, $nextRetry);
    }

    public function testCalculateWithInvalidRetryCount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Retry count must be >= 0');
        
        $this->calculator->calculate(-1, 60, 2.0);
    }

    public function testCalculateWithInvalidInitialDelay(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Initial delay must be > 0');
        
        $this->calculator->calculate(1, 0, 2.0);
    }

    public function testCalculateWithInvalidMultiplier(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Multiplier must be >= 1.0');
        
        $this->calculator->calculate(1, 60, 0.5);
    }
}

