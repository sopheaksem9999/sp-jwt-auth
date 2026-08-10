<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sopheak\JwtAuth\Support\ResponseEnvelope;

final class ResponseEnvelopeTest extends TestCase
{
    public function test_raw_mode_returns_payload_unchanged(): void
    {
        $payload = ['otp_id' => 'abc', 'destination_masked' => 'a***@b.com'];

        $this->assertSame($payload, ResponseEnvelope::wrap($payload, 'raw'));
    }

    public function test_laravel_mode_wraps_in_data(): void
    {
        $payload = ['otp_id' => 'abc'];

        $this->assertSame(['data' => $payload], ResponseEnvelope::wrap($payload, 'laravel'));
    }

    public function test_empty_mode_is_treated_as_raw(): void
    {
        $payload = ['otp_id' => 'abc'];

        $this->assertSame($payload, ResponseEnvelope::wrap($payload, ''));
    }
}
