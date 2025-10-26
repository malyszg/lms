<?php

declare(strict_types=1);

namespace App\Infrastructure\Config;

/**
 * CDP System Configuration
 * Manages configuration for CDP systems (SalesManago, Murapol, DomDevelopment)
 */
class CDPSystemConfig
{
    private const CDP_SYSTEMS = ['SalesManago', 'Murapol', 'DomDevelopment'];

    public function __construct(
        private readonly string $salesmanagoEnabled,
        private readonly string $salesmanagoUrl,
        private readonly string $salesmanagoApiKey,
        private readonly string $murapolEnabled,
        private readonly string $murapolUrl,
        private readonly string $murapolApiKey,
        private readonly string $domdevelopmentEnabled,
        private readonly string $domdevelopmentUrl,
        private readonly string $domdevelopmentApiKey,
    ) {}

    /**
     * Get configuration for a specific CDP system
     *
     * @param string $systemName CDP system name
     * @return array<string, mixed> Configuration array
     */
    public function getConfig(string $systemName): array
    {
        if (!in_array($systemName, self::CDP_SYSTEMS, true)) {
            throw new \InvalidArgumentException("Unknown CDP system: {$systemName}");
        }

        return [
            'enabled' => $this->isEnabled($systemName),
            'api_url' => $this->getApiUrl($systemName),
            'api_key' => $this->getApiKey($systemName),
            'retry_config' => $this->getRetryConfig($systemName),
        ];
    }

    /**
     * Get API URL for CDP system
     *
     * @param string $systemName CDP system name
     * @return string API URL
     */
    public function getApiUrl(string $systemName): string
    {
        return match ($systemName) {
            'SalesManago' => $this->salesmanagoUrl,
            'Murapol' => $this->murapolUrl,
            'DomDevelopment' => $this->domdevelopmentUrl,
            default => throw new \InvalidArgumentException("Unknown CDP system: {$systemName}"),
        };
    }

    /**
     * Get API key for CDP system
     *
     * @param string $systemName CDP system name
     * @return string API key
     */
    public function getApiKey(string $systemName): string
    {
        return match ($systemName) {
            'SalesManago' => $this->salesmanagoApiKey,
            'Murapol' => $this->murapolApiKey,
            'DomDevelopment' => $this->domdevelopmentApiKey,
            default => throw new \InvalidArgumentException("Unknown CDP system: {$systemName}"),
        };
    }

    /**
     * Check if CDP system is enabled
     *
     * @param string $systemName CDP system name
     * @return bool True if enabled
     */
    public function isEnabled(string $systemName): bool
    {
        $enabled = match ($systemName) {
            'SalesManago' => $this->salesmanagoEnabled,
            'Murapol' => $this->murapolEnabled,
            'DomDevelopment' => $this->domdevelopmentEnabled,
            default => throw new \InvalidArgumentException("Unknown CDP system: {$systemName}"),
        };
        
        return $enabled === 'true' || $enabled === true;
    }

    /**
     * Get retry configuration for CDP system
     *
     * @param string $systemName CDP system name
     * @return array<string, mixed> Retry config
     */
    public function getRetryConfig(string $systemName): array
    {
        return [
            'max_retries' => 3,
            'initial_delay_seconds' => 60,
            'backoff_multiplier' => 2.0,
        ];
    }

    /**
     * Get list of all configured CDP systems
     *
     * @return array<string> CDP system names
     */
    public function getConfiguredSystems(): array
    {
        return self::CDP_SYSTEMS;
    }

}

