<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\DTO\TokenPair;
use Sopheak\JwtAuth\Models\JwtAccessToken;
use Sopheak\JwtAuth\Models\JwtRefreshToken;
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

    public function test_token_response_can_be_extended_globally(): void
    {
        TokenResponse::extend(function (array $response, TokenPair $pair): array {
            $response['company_id'] = $pair->accessTokenRecord->companyId();
            $response['impersonated'] = $pair->accessTokenRecord->isImpersonated();

            return $response;
        });

        try {
            $pair = app(JwtTokenService::class)->issueTokenPair(
                $this->createUser(),
                TokenContext::make()->companyId(42)->impersonated(),
            );

            $response = TokenResponse::passportCompatible($pair);

            self::assertSame(42, $response['company_id']);
            self::assertTrue($response['impersonated']);
        } finally {
            TokenResponse::flushExtensions();
        }
    }

    public function test_prune_command_deletes_old_expired_tokens(): void
    {
        $pair = app(JwtTokenService::class)->issueTokenPair($this->createUser(), TokenContext::make());
        $pair->accessTokenRecord->forceFill(['expires_at' => now()->subDays(40)])->save();
        $pair->refreshTokenRecord->forceFill(['expires_at' => now()->subDays(40)])->save();

        $this->artisan('sp-jwt-auth:prune', ['--expired-days' => 30])
            ->assertExitCode(0);

        self::assertSame(0, JwtAccessToken::query()->count());
        self::assertSame(0, JwtRefreshToken::query()->count());
    }

    public function test_prune_keeps_refresh_token_that_is_still_valid(): void
    {
        $pair = app(JwtTokenService::class)->issueTokenPair($this->createUser(), TokenContext::make());
        $pair->accessTokenRecord->forceFill(['expires_at' => now()->subDays(40)])->save();

        $this->artisan('sp-jwt-auth:prune', ['--expired-days' => 30])->assertExitCode(0);

        self::assertSame(1, JwtAccessToken::query()->count());
        self::assertSame(1, JwtRefreshToken::query()->count());
        self::assertNotNull(app(JwtTokenService::class)->rotateRefreshToken($pair->refreshToken));
    }

    public function test_prune_counts_expired_and_revoked_rows_once_and_dry_run_deletes_nothing(): void
    {
        $user = $this->createUser();
        $service = app(JwtTokenService::class);

        for ($i = 0; $i < 5; $i++) {
            $pair = $service->issueTokenPair($user, TokenContext::make());
            $pair->refreshTokenRecord->forceFill([
                'expires_at' => now()->subDays(40),
                'revoked_at' => now()->subDays(40),
            ])->save();
            $pair->accessTokenRecord->forceFill([
                'expires_at' => now()->subDays(40),
                'revoked_at' => now()->subDays(40),
            ])->save();
        }

        $this->artisan('sp-jwt-auth:prune', ['--dry-run' => true])
            ->expectsOutput('Would prune 10 token records.')
            ->assertExitCode(0);
        self::assertSame(5, JwtAccessToken::query()->count());

        $this->artisan('sp-jwt-auth:prune', ['--chunk' => 2])
            ->expectsOutput('Pruned 10 token records.')
            ->assertExitCode(0);
        self::assertSame(0, JwtAccessToken::query()->count());
        self::assertSame(0, JwtRefreshToken::query()->count());
    }
}
