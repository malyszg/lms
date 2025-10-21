<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

/**
 * Google Gemini API Client Interface
 * 
 * Defines contract for communication with Google Gemini API.
 * Supports structured content generation with JSON Schema validation.
 */
interface GeminiClientInterface
{
    /**
     * Generate structured content using Gemini API
     * 
     * Sends a prompt to Gemini API and expects a response matching the provided JSON Schema.
     * The response will be automatically validated against the schema.
     * 
     * @param string $prompt User prompt with data to analyze
     * @param array<string, mixed> $responseSchema JSON Schema defining expected response structure
     * @param string|null $systemInstruction Optional system instruction for AI behavior
     * @return array<string, mixed> Parsed and validated response from AI
     * @throws \App\Exception\GeminiApiException When API request fails or response is invalid
     */
    public function generateStructuredContent(
        string $prompt,
        array $responseSchema,
        ?string $systemInstruction = null
    ): array;

    /**
     * Check if Gemini API is available
     * 
     * Performs a health check to verify API connectivity and credentials.
     * 
     * @return bool True if API is available, false otherwise
     */
    public function isAvailable(): bool;
}

