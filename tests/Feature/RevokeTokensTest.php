<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Illuminate\Auth\AuthenticationException;
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Models\JwtAccessToken;
use Sopheak\JwtAuth\Services\JwtTokenService;
use Sopheak\JwtAuth\Tests\TestCase;

final class RevokeTokensTest extends TestCase
{
    public function test_revoke_access_token_rejects_validation(): void
    {
        $service = app(JwtTokenService::class);
        $pair = $service->issueTokenPair($this->createUser(), TokenContext::make());

        $service->revokeAccessToken($pair->accessTokenRecord->id);

        $this->expectException(AuthenticationException::class);
        $service->validateAccessToken($pair->accessToken);
    }

    public function test_revoke_session_revokes_all_tokens_for_session(): void
    {
        $service = app(JwtTokenService::class);
        $pair = $service->issueTokenPair($this->createUser(), TokenContext::make());

        $service->revokeSession($pair->accessTokenRecord->session_id);

        self::assertSame(0, JwtAccessToken::query()->whereNull('revoked_at')->count());
    }
}
