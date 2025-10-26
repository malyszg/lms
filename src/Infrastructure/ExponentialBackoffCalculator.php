<?php

declare(strict_types=1);

namespace App\Infrastructure;

use DateTimeInterface;

/**
 * Exponential Backoff Calculator
 * Calculates next retry time using exponential backoff algorithm
 */
class ExponentialBackoffCalculator
{
    /**
     * Calculate next retry time using exponential backoff
     *
     * Formula: delay = initialDelaySeconds * (multiplier ^ retryCount)
     *
     * @param int $retryCount Current retry count (0-based)
     * @param int $initialDelaySeconds Initial delay in seconds
     * @param float $multiplier Exponential multiplier
     * @return DateTimeInterface Next retry time
     */
    public function calculate(
        int $retryCount,
        int $initialDelaySeconds,
        float $multiplier
    ): DateTimeInterface {
        if ($retryCount < 0) {
            throw new \InvalidArgumentException('Retry count must be >= 0');
        }

        if ($initialDelaySeconds <= 0) {
            throw new \InvalidArgumentException('Initial delay must be > 0');
        }

        if ($multiplier < 1.0) {
            throw new \InvalidArgumentException('Multiplier must be >= 1.0');
        }

        // Calculate delay: initial * (multiplier ^ retryCount)
        $delaySeconds = $initialDelaySeconds * pow($multiplier, $retryCount);

        // Create DateTime for next retry (now + calculated delay)
        $nextRetry = new \DateTime();
        $nextRetry->modify("+{$delaySeconds} seconds");

        return $nextRetry;
    }

    /**
     * Calculate next retry time for given retry configuration
     *
     * @param int $retryCount Current retry count
     * @param array<string, mixed> $retryConfig Retry configuration with keys:
     *                                           - initial_delay_seconds (int)
     *                                           - backoff_multiplier (float)
     * @return DateTimeInterface Next retry time
     */
    public function calculateWithConfig(int $retryCount, array $retryConfig): DateTimeInterface
    {
        $initialDelaySeconds = $retryConfig['initial_delay_seconds'] ?? 60;
        $multiplier = $retryConfig['backoff_multiplier'] ?? 2.0;

        return $this->calculate($retryCount, $initialDelaySeconds, $multiplier);
    }
}

