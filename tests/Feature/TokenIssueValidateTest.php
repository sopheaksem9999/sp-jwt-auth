<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Illuminate\Auth\AuthenticationException;
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Models\JwtAccessToken;
use Sopheak\JwtAuth\Models\JwtRefreshToken;
use Sopheak\JwtAuth\Services\JwtTokenService;
use Sopheak\JwtAuth\Tests\TestCase;

final class TokenIssueValidateTest extends TestCase
{
    public function test_issue_token_pair_persists_rows_and_validates_access_token(): void
    {
        $user = $this->createUser();
        $service = app(JwtTokenService::class);

        $pair = $service->issueTokenPair($user, TokenContext::make()
            ->subject('tenant', '42')
            ->scopes(['client', 'tenant:42'])
            ->claims(['tenant_id' => 42]));

        self::assertNotSame('', $pair->accessToken);
        self::assertMatchesRegularExpression('/^[^.]+\\.[^.]+$/', $pair->refreshToken);
        self::assertSame(1, JwtAccessToken::query()->count());
        self::assertSame(1, JwtRefreshToken::query()->count());
        self::assertDatabaseMissing('sp_jwt_refresh_tokens', ['secret_hash' => explode('.', $pair->refreshToken, 2)[1]]);

        $accessToken = $service->validateAccessToken($pair->accessToken);

        self::assertSame($pair->accessTokenRecord->id, $accessToken->id);
        self::assertSame(['client', 'tenant:42'], $accessToken->scopes);
        self::assertSame(42, $accessToken->claims['tenant_id']);
    }

    public function test_validate_rejects_revoked_token(): void
    {
        $service = app(JwtTokenService::class);
        $pair = $service->issueTokenPair($this->createUser(), TokenContext::make());
        $service->revokeAccessToken($pair->accessTokenRecord->id);

        $this->expectException(AuthenticationException::class);

        $service->validateAccessToken($pair->accessToken);
    }
}
