<?php

declare(strict_types=1);

namespace App\Infrastructure\AI;

use App\Exception\GeminiApiException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Google Gemini API Client Implementation
 * 
 * Handles low-level HTTP communication with Google Gemini API.
 * Supports structured content generation with JSON Schema validation.
 */
class GeminiClient implements GeminiClientInterface
{
    private const API_VERSION = 'v1beta';
    
    /**
     * @param ClientInterface $httpClient HTTP client for API requests
     * @param string $apiKey Google Gemini API key
     * @param string $model Model name (default: gemini-2.0-flash)
     * @param string $apiBaseUrl Base URL for Gemini API
     * @param LoggerInterface|null $logger Optional logger for debugging
     */
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly string $apiKey,
        private readonly string $model = 'gemini-2.0-flash',
        private readonly string $apiBaseUrl = 'https://generativelanguage.googleapis.com',
        private readonly ?LoggerInterface $logger = null
    ) {
        if (empty($this->apiKey)) {
            throw new \InvalidArgumentException('Gemini API key cannot be empty');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function generateStructuredContent(
        string $prompt,
        array $responseSchema,
        ?string $systemInstruction = null
    ): array {
        $this->validateSchema($responseSchema);
        
        $payload = $this->buildRequestPayload($prompt, $responseSchema, $systemInstruction);
        $endpoint = $this->buildEndpoint();
        
        try {
            $response = $this->makeRequest($endpoint, $payload);
            return $this->parseResponse($response);
            
        } catch (ConnectException $e) {
            $this->logger?->error('Network error connecting to Gemini API', [
                'error' => $e->getMessage()
            ]);
            
            throw new GeminiApiException(
                'Network error connecting to Gemini API',
                'GEMINI_NETWORK_ERROR',
                0,
                ['error' => $e->getMessage()]
            );
        } catch (GuzzleException $e) {
            $this->handleGuzzleException($e);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isAvailable(): bool
    {
        try {
            $endpoint = $this->buildEndpoint();
            $response = $this->httpClient->request('GET', $endpoint, [
                'query' => ['key' => $this->apiKey],
                'timeout' => 5,
                'http_errors' => false
            ]);
            
            $statusCode = $response->getStatusCode();
            $isAvailable = $statusCode >= 200 && $statusCode < 500;
            
            if (!$isAvailable) {
                $this->logger?->warning('Gemini API health check failed', [
                    'status_code' => $statusCode
                ]);
            }
            
            return $isAvailable;
            
        } catch (\Exception $e) {
            $this->logger?->warning('Gemini API health check failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Build API endpoint URL
     * 
     * @return string Complete endpoint URL
     */
    private function buildEndpoint(): string
    {
        return sprintf(
            '%s/%s/models/%s:generateContent',
            rtrim($this->apiBaseUrl, '/'),
            self::API_VERSION,
            $this->model
        );
    }

    /**
     * Build request payload for Gemini API
     * 
     * @param string $prompt User prompt
     * @param array<string, mixed> $responseSchema JSON Schema for response
     * @param string|null $systemInstruction Optional system instruction
     * @return array<string, mixed> Complete request payload
     */
    private function buildRequestPayload(
        string $prompt,
        array $responseSchema,
        ?string $systemInstruction
    ): array {
        $payload = [
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => [['text' => $prompt]]
                ]
            ],
            'generationConfig' => [
                'response_mime_type' => 'application/json',
                'response_schema' => $responseSchema
            ]
        ];

        if ($systemInstruction !== null) {
            $payload['systemInstruction'] = [
                'parts' => [['text' => $systemInstruction]]
            ];
        }

        return $payload;
    }

    /**
     * Execute HTTP request to Gemini API
     * 
     * @param string $endpoint API endpoint URL
     * @param array<string, mixed> $payload Request payload
     * @return array<string, mixed> Parsed response data
     * @throws GeminiApiException When request fails
     */
    private function makeRequest(string $endpoint, array $payload): array
    {
        $this->logger?->debug('Gemini API request', [
            'endpoint' => $endpoint,
            'model' => $this->model
        ]);

        $response = $this->httpClient->request('POST', $endpoint, [
            'query' => ['key' => $this->apiKey],
            'json' => $payload,
            'timeout' => 30,
            'connect_timeout' => 10,
            'headers' => [
                'Content-Type' => 'application/json',
            ]
        ]);

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger?->error('Failed to parse Gemini API response', [
                'body' => $body,
                'json_error' => json_last_error_msg()
            ]);
            
            throw new GeminiApiException(
                'Failed to parse Gemini API response: ' . json_last_error_msg(),
                'GEMINI_PARSE_ERROR',
                500,
                ['body' => $body]
            );
        }

        return $data;
    }

    /**
     * Parse and validate response from Gemini API
     * 
     * @param array<string, mixed> $response Raw API response
     * @return array<string, mixed> Parsed structured content
     * @throws GeminiApiException When response structure is invalid
     */
    private function parseResponse(array $response): array
    {
        // Check for safety blocks or other finish reasons
        if (isset($response['candidates'][0]['finishReason'])) {
            $finishReason = $response['candidates'][0]['finishReason'];
            if ($finishReason === 'SAFETY') {
                $this->logger?->warning('Gemini API response blocked by safety filter');
                throw new GeminiApiException(
                    'Content blocked by safety filter',
                    'GEMINI_SAFETY_BLOCK',
                    400,
                    ['finish_reason' => $finishReason]
                );
            }
        }

        // Extract generated text
        if (!isset($response['candidates'][0]['content']['parts'][0]['text'])) {
            $this->logger?->error('Unexpected Gemini API response structure', [
                'response' => $response
            ]);
            
            throw new GeminiApiException(
                'Unexpected Gemini API response structure',
                'GEMINI_INVALID_RESPONSE',
                500,
                ['response' => $response]
            );
        }

        $text = $response['candidates'][0]['content']['parts'][0]['text'];
        $parsed = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logger?->error('Failed to parse AI generated JSON', [
                'text' => $text,
                'json_error' => json_last_error_msg()
            ]);
            
            throw new GeminiApiException(
                'Failed to parse AI generated JSON: ' . json_last_error_msg(),
                'GEMINI_INVALID_JSON',
                500,
                ['text' => $text]
            );
        }

        return $parsed;
    }

    /**
     * Validate JSON Schema before sending to API
     * 
     * @param array<string, mixed> $schema JSON Schema to validate
     * @return void
     * @throws \InvalidArgumentException When schema is invalid
     */
    private function validateSchema(array $schema): void
    {
        if (!isset($schema['type'])) {
            throw new \InvalidArgumentException('Response schema must have "type" field');
        }

        if ($schema['type'] !== 'object') {
            throw new \InvalidArgumentException(
                sprintf('Response schema type must be "object", got: %s', $schema['type'])
            );
        }

        if (!isset($schema['properties']) || !is_array($schema['properties'])) {
            throw new \InvalidArgumentException('Response schema must have "properties" field as array');
        }

        if (empty($schema['properties'])) {
            throw new \InvalidArgumentException('Response schema properties cannot be empty');
        }
    }

    /**
     * Handle Guzzle exceptions and convert to GeminiApiException
     * 
     * @param GuzzleException $e Guzzle exception
     * @return never
     * @throws GeminiApiException Always throws
     */
    private function handleGuzzleException(GuzzleException $e): never
    {
        $response = null;
        $statusCode = 0;
        $body = '';
        
        // RequestException has getResponse() method
        if ($e instanceof RequestException && $e->hasResponse()) {
            $response = $e->getResponse();
            $statusCode = $response->getStatusCode();
            $body = (string) $response->getBody();
        }

        $this->logger?->error('Gemini API error', [
            'status' => $statusCode,
            'body' => $body,
            'error' => $e->getMessage()
        ]);

        $errorCode = match($statusCode) {
            401, 403 => 'GEMINI_INVALID_API_KEY',
            429 => 'GEMINI_RATE_LIMIT',
            400 => 'GEMINI_BAD_REQUEST',
            default => 'GEMINI_API_ERROR'
        };

        $message = match($statusCode) {
            401, 403 => 'Invalid or missing Gemini API key',
            429 => 'Gemini API rate limit exceeded',
            400 => 'Bad request to Gemini API',
            default => 'Gemini API error: ' . $e->getMessage()
        };

        throw new GeminiApiException(
            $message,
            $errorCode,
            $statusCode,
            ['body' => $body, 'original_message' => $e->getMessage()]
        );
    }
}

