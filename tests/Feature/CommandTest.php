<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\DTO\TokenPair;
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

        $this->artisan('sp-jwt-auth:prune', ['--expired-days' => 30])
            ->assertExitCode(0);

        self::assertSame(0, JwtAccessToken::query()->count());
    }
}
