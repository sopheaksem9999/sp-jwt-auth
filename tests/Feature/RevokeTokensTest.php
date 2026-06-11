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
<<<<<<< HEAD

    public function test_revoke_all_for_user_can_keep_current_session(): void
    {
        $service = app(JwtTokenService::class);
        $user = $this->createUser();

        $current = $service->issueTokenPair($user, TokenContext::make()->sessionId('11111111-1111-1111-1111-111111111111'));
        $other = $service->issueTokenPair($user, TokenContext::make()->sessionId('22222222-2222-2222-2222-222222222222'));

        $service->revokeAllForUser($user, exceptSessionId: $current->accessTokenRecord->session_id);

        self::assertNull($current->accessTokenRecord->fresh()->revoked_at);
        self::assertNotNull($other->accessTokenRecord->fresh()->revoked_at);
    }

    public function test_revoke_device_revokes_matching_device_tokens_for_user(): void
    {
        $service = app(JwtTokenService::class);
        $user = $this->createUser();

        $mobile = $service->issueTokenPair($user, new TokenContext(deviceId: 'ios-1'));
        $web = $service->issueTokenPair($user, new TokenContext(deviceId: 'web-1'));

        $service->revokeDevice($user, 'ios-1');

        self::assertNotNull($mobile->accessTokenRecord->fresh()->revoked_at);
        self::assertNull($web->accessTokenRecord->fresh()->revoked_at);
    }

    public function test_active_sessions_for_user_returns_active_access_tokens(): void
    {
        $service = app(JwtTokenService::class);
        $user = $this->createUser();

        $active = $service->issueTokenPair($user, TokenContext::make()->sessionId('11111111-1111-1111-1111-111111111111'));
        $revoked = $service->issueTokenPair($user, TokenContext::make()->sessionId('22222222-2222-2222-2222-222222222222'));
        $service->revokeSession($revoked->accessTokenRecord->session_id);

        $sessions = $service->activeSessionsForUser($user);

        self::assertTrue($sessions->contains('id', $active->accessTokenRecord->id));
        self::assertFalse($sessions->contains('id', $revoked->accessTokenRecord->id));
    }
=======
>>>>>>> 11e06a7 (feat: add complete Laravel JWT auth package with OAuth support)
}
