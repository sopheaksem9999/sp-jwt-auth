<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Illuminate\Auth\AuthenticationException;
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Models\JwtRefreshToken;
use Sopheak\JwtAuth\Services\JwtTokenService;
use Sopheak\JwtAuth\Tests\TestCase;

final class RefreshRotationTest extends TestCase
{
    public function test_refresh_token_rotates_and_preserves_context(): void
    {
        $user = $this->createUser();
        $service = app(JwtTokenService::class);
        $first = $service->issueTokenPair($user, TokenContext::make()
            ->subject('tenant', '42')
            ->scopes(['client'])
            ->claims(['tenant_id' => 42]));

        $second = $service->rotateRefreshToken($first->refreshToken);

        self::assertNotSame($first->accessToken, $second->accessToken);
        self::assertNotSame($first->refreshToken, $second->refreshToken);
        self::assertNotNull(JwtRefreshToken::query()->find($first->refreshTokenRecord->id)->revoked_at);
        self::assertSame($second->refreshTokenRecord->id, JwtRefreshToken::query()->find($first->refreshTokenRecord->id)->replaced_by_id);
        self::assertSame(['client'], $second->accessTokenRecord->scopes);
        self::assertSame('tenant', $second->accessTokenRecord->subject_type);
    }

    public function test_reused_refresh_token_revokes_session_by_default(): void
    {
        $user = $this->createUser();
        $service = app(JwtTokenService::class);
        $first = $service->issueTokenPair($user, TokenContext::make()->scopes(['client']));

        $service->rotateRefreshToken($first->refreshToken);

        $this->expectException(AuthenticationException::class);

        $service->rotateRefreshToken($first->refreshToken);
    }
}
