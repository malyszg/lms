<?php

declare(strict_types=1);

namespace App\Tests\Unit\DTO;

use App\DTO\DeleteLeadResponse;
use DateTime;
use PHPUnit\Framework\TestCase;

/**
 * Test for DeleteLeadResponse DTO
 */
class DeleteLeadResponseTest extends TestCase
{
    public function testConstructor(): void
    {
        $leadUuid = '550e8400-e29b-41d4-a716-446655440000';
        $deletedAt = new DateTime('2025-01-15 14:30:00');
        $message = 'Lead został pomyślnie usunięty';

        $response = new DeleteLeadResponse($leadUuid, $deletedAt, $message);

        $this->assertSame($leadUuid, $response->leadUuid);
        $this->assertSame($deletedAt, $response->deletedAt);
        $this->assertSame($message, $response->message);
    }

    public function testReadonlyProperties(): void
    {
        $response = new DeleteLeadResponse(
            '550e8400-e29b-41d4-a716-446655440000',
            new DateTime(),
            'Test message'
        );

        // Properties should be readonly - this should not cause any issues
        // but we can't modify them
        $this->assertIsString($response->leadUuid);
        $this->assertInstanceOf(DateTime::class, $response->deletedAt);
        $this->assertIsString($response->message);
    }

    public function testWithDifferentData(): void
    {
        $leadUuid = '123e4567-e89b-12d3-a456-426614174000';
        $deletedAt = new DateTime('2025-12-31 23:59:59');
        $message = 'Lead usunięty przez administratora';

        $response = new DeleteLeadResponse($leadUuid, $deletedAt, $message);

        $this->assertSame($leadUuid, $response->leadUuid);
        $this->assertSame($deletedAt, $response->deletedAt);
        $this->assertSame($message, $response->message);
    }
}
