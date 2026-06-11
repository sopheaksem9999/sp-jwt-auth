<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Models\JwtAccessToken;
use Sopheak\JwtAuth\Services\JwtTokenService;
use Sopheak\JwtAuth\Support\TokenResponse;
use Sopheak\JwtAuth\Tests\TestCase;

final class CommandTest extends TestCase
{
    public function test_passport_compatible_token_response(): void
    {
        $pair = app(JwtTokenService::class)->issueTokenPair($this->createUser(), TokenContext::make());

        $response = TokenResponse::passportCompatible($pair, ['company_id' => 1]);

        self::assertSame('Bearer', $response['token_type']);
        self::assertArrayHasKey('access_token', $response);
        self::assertArrayHasKey('refresh_token', $response);
        self::assertSame(1, $response['company_id']);
    }

    public function test_prune_command_deletes_old_expired_tokens(): void
    {
        $pair = app(JwtTokenService::class)->issueTokenPair($this->createUser(), TokenContext::make());
        $pair->accessTokenRecord->forceFill(['expires_at' => now()->subDays(40)])->save();

        $this->artisan('sp-jwt-auth:prune', ['--expired-days' => 30])
            ->assertExitCode(0);

        self::assertSame(0, JwtAccessToken::query()->count());
    }
}
