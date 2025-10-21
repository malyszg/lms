<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\AI;

use App\Infrastructure\AI\GeminiClient;
use App\Exception\GeminiApiException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Test for GeminiClient
 */
class GeminiClientTest extends TestCase
{
    private ClientInterface $httpClient;
    private LoggerInterface $logger;
    private string $apiKey = 'test-api-key';
    
    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(ClientInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testConstructorThrowsExceptionOnEmptyApiKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Gemini API key cannot be empty');
        
        new GeminiClient(
            $this->httpClient,
            '', // empty API key
            'gemini-1.5-flash',
            'https://api.test.com',
            $this->logger
        );
    }

    public function testGenerateStructuredContentSuccess(): void
    {
        $prompt = 'Test prompt';
        $schema = [
            'type' => 'object',
            'properties' => [
                'score' => ['type' => 'integer']
            ]
        ];

        $mockApiResponse = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => '{"score": 85}']
                        ]
                    ]
                ]
            ]
        ];

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with(
                'POST',
                $this->stringContains('generateContent')
            )
            ->willReturn(new Response(200, [], json_encode($mockApiResponse)));

        $client = new GeminiClient(
            $this->httpClient,
            $this->apiKey,
            'gemini-1.5-flash',
            'https://api.test.com',
            $this->logger
        );

        $result = $client->generateStructuredContent($prompt, $schema);

        $this->assertEquals(['score' => 85], $result);
    }

    public function testGenerateStructuredContentWithSystemInstruction(): void
    {
        $prompt = 'Test prompt';
        $schema = [
            'type' => 'object',
            'properties' => [
                'result' => ['type' => 'string']
            ]
        ];
        $systemInstruction = 'You are an expert';

        $mockApiResponse = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => '{"result": "success"}']
                        ]
                    ]
                ]
            ]
        ];

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn(new Response(200, [], json_encode($mockApiResponse)));

        $client = new GeminiClient(
            $this->httpClient,
            $this->apiKey,
            'gemini-1.5-flash',
            'https://api.test.com',
            $this->logger
        );

        $result = $client->generateStructuredContent($prompt, $schema, $systemInstruction);

        $this->assertArrayHasKey('result', $result);
        $this->assertEquals('success', $result['result']);
    }

    public function testThrowsExceptionOnInvalidSchema(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Response schema must have "type" field');
        
        $client = new GeminiClient(
            $this->httpClient,
            $this->apiKey,
            'gemini-1.5-flash',
            'https://api.test.com',
            $this->logger
        );

        $client->generateStructuredContent('test', ['invalid' => 'schema']);
    }

    public function testThrowsExceptionWhenSchemaTypeNotObject(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Response schema type must be "object"');
        
        $client = new GeminiClient(
            $this->httpClient,
            $this->apiKey,
            'gemini-1.5-flash',
            'https://api.test.com',
            $this->logger
        );

        $schema = [
            'type' => 'string',
            'properties' => []
        ];

        $client->generateStructuredContent('test', $schema);
    }

    public function testThrowsExceptionWhenSchemaHasNoProperties(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Response schema must have "properties" field');
        
        $client = new GeminiClient(
            $this->httpClient,
            $this->apiKey,
            'gemini-1.5-flash',
            'https://api.test.com',
            $this->logger
        );

        $schema = [
            'type' => 'object'
        ];

        $client->generateStructuredContent('test', $schema);
    }

    public function testThrowsNetworkErrorOnConnectException(): void
    {
        $this->expectException(GeminiApiException::class);
        $this->expectExceptionMessage('Network error connecting to Gemini API');

        $request = $this->createMock(RequestInterface::class);
        
        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willThrowException(new ConnectException('Connection failed', $request));

        $client = new GeminiClient(
            $this->httpClient,
            $this->apiKey,
            'gemini-1.5-flash',
            'https://api.test.com',
            $this->logger
        );

        $schema = [
            'type' => 'object',
            'properties' => ['test' => ['type' => 'string']]
        ];

        $client->generateStructuredContent('test', $schema);
    }

    public function testThrowsInvalidApiKeyExceptionOn401(): void
    {
        $this->expectException(GeminiApiException::class);

        $request = $this->createMock(RequestInterface::class);
        $response = new Response(401, [], '{"error": "Invalid API key"}');
        
        $exception = new RequestException(
            'Unauthorized',
            $request,
            $response
        );

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willThrowException($exception);

        $client = new GeminiClient(
            $this->httpClient,
            $this->apiKey,
            'gemini-1.5-flash',
            'https://api.test.com',
            $this->logger
        );

        $schema = [
            'type' => 'object',
            'properties' => ['test' => ['type' => 'string']]
        ];

        try {
            $client->generateStructuredContent('test', $schema);
        } catch (GeminiApiException $e) {
            $this->assertEquals('GEMINI_INVALID_API_KEY', $e->getErrorCode());
            $this->assertEquals(401, $e->getCode());
            throw $e;
        }
    }

    public function testThrowsRateLimitExceptionOn429(): void
    {
        $this->expectException(GeminiApiException::class);

        $request = $this->createMock(RequestInterface::class);
        $response = new Response(429, [], '{"error": "Rate limit exceeded"}');
        
        $exception = new RequestException(
            'Too Many Requests',
            $request,
            $response
        );

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willThrowException($exception);

        $client = new GeminiClient(
            $this->httpClient,
            $this->apiKey,
            'gemini-1.5-flash',
            'https://api.test.com',
            $this->logger
        );

        $schema = [
            'type' => 'object',
            'properties' => ['test' => ['type' => 'string']]
        ];

        try {
            $client->generateStructuredContent('test', $schema);
        } catch (GeminiApiException $e) {
            $this->assertEquals('GEMINI_RATE_LIMIT', $e->getErrorCode());
            $this->assertEquals(429, $e->getCode());
            throw $e;
        }
    }

    public function testThrowsExceptionOnInvalidJsonResponse(): void
    {
        $this->expectException(GeminiApiException::class);
        $this->expectExceptionMessage('Failed to parse AI generated JSON');

        $mockApiResponse = [
            'candidates' => [
                [
                    'content' => [
                        'parts' => [
                            ['text' => 'invalid json {']
                        ]
                    ]
                ]
            ]
        ];

        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willReturn(new Response(200, [], json_encode($mockApiResponse)));

        $client = new GeminiClient(
            $this->httpClient,
            $this->apiKey,
            'gemini-1.5-flash',
            'https://api.test.com',
            $this->logger
        );

        $schema = [
            'type' => 'object',
            'properties' => ['test' => ['type' => 'string']]
        ];

        $client->generateStructuredContent('test', $schema);
    }

    public function testIsAvailableReturnsTrueOnSuccess(): void
    {
        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->with('GET')
            ->willReturn(new Response(200));

        $client = new GeminiClient(
            $this->httpClient,
            $this->apiKey,
            'gemini-1.5-flash',
            'https://api.test.com',
            $this->logger
        );

        $this->assertTrue($client->isAvailable());
    }

    public function testIsAvailableReturnsFalseOnException(): void
    {
        $request = $this->createMock(RequestInterface::class);
        
        $this->httpClient
            ->expects($this->once())
            ->method('request')
            ->willThrowException(new ConnectException('Connection failed', $request));

        $client = new GeminiClient(
            $this->httpClient,
            $this->apiKey,
            'gemini-1.5-flash',
            'https://api.test.com',
            $this->logger
        );

        $this->assertFalse($client->isAvailable());
    }
}

