<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use RuntimeException;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Sopheak\JwtAuth\DTO\ApiKeyContext;
use Sopheak\JwtAuth\DTO\ExternalIdentity;
use Sopheak\JwtAuth\DTO\OAuthAuthorizationRequest;
use Sopheak\JwtAuth\DTO\OAuthClientData;
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Events\OAuthAuthorizationDenied;
use Sopheak\JwtAuth\Models\ExternalIdentity as ExternalIdentityModel;
use Sopheak\JwtAuth\Models\JwtAccessToken;
use Sopheak\JwtAuth\Services\ApiKeyService;
use Sopheak\JwtAuth\Services\ExternalIdentityStore;
use Sopheak\JwtAuth\Services\JwtTokenService;
use Sopheak\JwtAuth\Services\OAuthClientRepository;
use Sopheak\JwtAuth\Services\OAuthServerService;
use Sopheak\JwtAuth\Signing\ConfigSigningKeyRepository;
use Sopheak\JwtAuth\Signing\SigningKeyRepository;
use Sopheak\JwtAuth\Tests\TestCase;

/** Regression tests for docs/bug-reports/2026-10-26-*. */
final class BugReports20261026Test extends TestCase
{
    private function tokens(): JwtTokenService
    {
        return app(JwtTokenService::class);
    }

    public function test_idle_session_with_live_refresh_token_is_listed_and_revoked_one_is_not(): void
    {
        $user = $this->createUser();
        $idle = $this->tokens()->issueTokenPair($user, TokenContext::make());
        $idle->accessTokenRecord->forceFill(['expires_at' => now()->subHour()])->save();
        $revoked = $this->tokens()->issueTokenPair($user, TokenContext::make());
        $this->tokens()->revokeSession($revoked->accessTokenRecord->session_id);

        $sessions = $this->tokens()->activeSessionsForUser($user);

        self::assertCount(1, $sessions);
        self::assertSame($idle->accessTokenRecord->id, $sessions->first()->id);
    }

    public function test_session_listing_has_one_entry_per_session_after_refresh(): void
    {
        $user = $this->createUser();
        $pair = $this->tokens()->issueTokenPair($user, TokenContext::make());
        $next = $this->tokens()->rotateRefreshToken($pair->refreshToken);

        $sessions = $this->tokens()->activeSessionsForUser($user);

        self::assertCount(1, $sessions);
        self::assertSame($next->accessTokenRecord->id, $sessions->first()->id);
    }

    public function test_api_key_allowed_ips_support_cidr_ipv6_and_fail_closed_without_ip(): void
    {
        $service = app(ApiKeyService::class);
        $v4 = $service->createApiKey(new ApiKeyContext(ownerType: 'tenant', ownerId: '1', name: 'v4', allowedIps: ['203.0.113.0/24']));
        $v6 = $service->createApiKey(new ApiKeyContext(ownerType: 'tenant', ownerId: '1', name: 'v6', allowedIps: ['2001:db8::/32']));

        self::assertSame('1', $service->validateApiKey($v4->plaintextKey, '203.0.113.10')->ownerId);
        self::assertSame('1', $service->validateApiKey($v6->plaintextKey, '2001:db8::5')->ownerId);

        foreach ([[$v4, '198.51.100.1'], [$v4, null], [$v6, '2001:db9::1']] as [$key, $ip]) {
            try {
                $service->validateApiKey($key->plaintextKey, $ip);
                self::fail('Expected the key to be rejected.');
            } catch (AuthenticationException) {
                self::assertTrue(true);
            }
        }
    }

    public function test_api_key_lifetime_follows_max_ttl_and_allow_never_expires(): void
    {
        $service = app(ApiKeyService::class);

        $defaulted = $service->createApiKey(new ApiKeyContext(ownerType: 'tenant', ownerId: '1', name: 'a'));
        self::assertNotNull($defaulted->apiKey->expires_at);

        config()->set('sp-jwt-auth.api_keys.allow_never_expires', true);
        self::assertNull($service->createApiKey(new ApiKeyContext(ownerType: 'tenant', ownerId: '1', name: 'b'))->apiKey->expires_at);

        $this->expectException(InvalidArgumentException::class);
        $service->createApiKey(new ApiKeyContext(ownerType: 'tenant', ownerId: '1', name: 'c', expiresAt: now()->addYears(5)));
    }

    public function test_compromised_key_does_not_break_jwks_and_is_rejected_for_verification(): void
    {
        config()->set('sp-jwt-auth.keys.items.old', [
            'state' => 'compromised',
            'private_key' => self::privateKey(),
            'public_key' => self::publicKey(),
        ]);

        $this->getJson('/.well-known/sp-jwt-auth/jwks.json')
            ->assertOk()
            ->assertJsonCount(1, 'keys');

        $this->expectException(RuntimeException::class);
        app(ConfigSigningKeyRepository::class)->forVerification('old');
    }

    public function test_compromised_kids_list_rejects_tokens_and_previous_kids_still_verify(): void
    {
        $pair = $this->tokens()->issueTokenPair($this->createUser(), TokenContext::make());

        config()->set('sp-jwt-auth.keys.previous_kids', ['test-active']);
        app(SigningKeyRepository::class)->flush();
        self::assertSame($pair->accessTokenRecord->id, $this->tokens()->validateAccessToken($pair->accessToken)->id);

        config()->set('sp-jwt-auth.keys.compromised_kids', ['test-active']);
        app(SigningKeyRepository::class)->flush();
        $this->expectException(AuthenticationException::class);
        $this->tokens()->validateAccessToken($pair->accessToken);
    }

    public function test_clock_skew_seconds_is_used_as_jwt_leeway(): void
    {
        $pair = $this->tokens()->issueTokenPair($this->createUser(), TokenContext::make());
        $afterExpiry = time() + (15 * 60) + 5;

        try {
            JWT::$timestamp = $afterExpiry;

            config()->set('sp-jwt-auth.clock_skew_seconds', 60);
            self::assertNotNull($this->tokens()->validateAccessToken($pair->accessToken));

            config()->set('sp-jwt-auth.clock_skew_seconds', 0);
            $this->expectException(AuthenticationException::class);
            $this->tokens()->validateAccessToken($pair->accessToken);
        } finally {
            JWT::$timestamp = null;
            JWT::$leeway = 0;
        }
    }

    public function test_store_without_user_keeps_existing_link_and_unlink_is_explicit(): void
    {
        $user = $this->createUser();
        $store = app(ExternalIdentityStore::class);
        $identity = new ExternalIdentity(provider: 'google', providerUserId: 'g-1', email: 'a@example.com');

        $store->store($identity, $user);
        $row = $store->store($identity);

        self::assertSame($user::class, $row->user_type);
        self::assertSame((string) $user->getKey(), (string) $row->user_id);

        $store->unlink('google', 'g-1');
        self::assertNull($store->find('google', 'g-1')->user_id);
    }

    public function test_provider_tokens_are_encrypted_at_rest_and_legacy_plaintext_rows_still_read(): void
    {
        config()->set('sp-jwt-auth.external_identities.store_provider_tokens', true);
        $row = app(ExternalIdentityStore::class)->store(new ExternalIdentity(
            provider: 'github',
            providerUserId: 'gh-1',
            providerTokens: ['access_token' => 'provider-secret'],
        ), $this->createUser());

        $raw = (string) DB::table('sp_jwt_external_identities')->where('id', $row->id)->value('provider_tokens');
        self::assertStringNotContainsString('provider-secret', $raw);
        self::assertSame(['access_token' => 'provider-secret'], ExternalIdentityModel::query()->find($row->id)->provider_tokens);

        DB::table('sp_jwt_external_identities')->where('id', $row->id)->update(['provider_tokens' => json_encode(['access_token' => 'legacy'])]);
        self::assertSame(['access_token' => 'legacy'], ExternalIdentityModel::query()->find($row->id)->provider_tokens);

        config()->set('sp-jwt-auth.external_identities.encrypt_provider_tokens', false);
        $plain = app(ExternalIdentityStore::class)->store(new ExternalIdentity(
            provider: 'github',
            providerUserId: 'gh-2',
            providerTokens: ['access_token' => 'visible'],
        ));
        self::assertStringContainsString('visible', (string) DB::table('sp_jwt_external_identities')->where('id', $plain->id)->value('provider_tokens'));
    }

    public function test_refresh_rotation_keeps_device_and_revoke_device_still_matches(): void
    {
        $user = $this->createUser();
        $pair = $this->tokens()->issueTokenPair($user, new TokenContext(deviceId: 'abc', deviceName: 'iPhone'));

        $next = $this->tokens()->rotateRefreshToken($pair->refreshToken);
        $last = $this->tokens()->rotateRefreshToken($next->refreshToken);

        self::assertSame('abc', $last->accessTokenRecord->device_id);
        self::assertSame('iPhone', $last->accessTokenRecord->device_name);

        $this->tokens()->revokeDevice($user, 'abc');
        self::assertNotNull(JwtAccessToken::query()->find($last->accessTokenRecord->id)->revoked_at);
    }

    public function test_ip_address_and_user_agent_are_recorded_on_issue_and_rotation(): void
    {
        $pair = $this->tokens()->issueTokenPair($this->createUser(), TokenContext::make()->metadata([
            'ip_address' => '203.0.113.9',
            'user_agent' => 'Issue/1.0',
        ]));

        self::assertSame('203.0.113.9', $pair->accessTokenRecord->fresh()->ip_address);
        self::assertSame('Issue/1.0', $pair->accessTokenRecord->fresh()->user_agent);

        $this->app->instance('request', Request::create('/', 'POST', server: ['REMOTE_ADDR' => '198.51.100.7', 'HTTP_USER_AGENT' => 'Rotate/2.0']));
        $next = $this->tokens()->rotateRefreshToken($pair->refreshToken);

        self::assertSame('198.51.100.7', $next->accessTokenRecord->fresh()->ip_address);
        self::assertSame('Rotate/2.0', $next->accessTokenRecord->fresh()->user_agent);
    }

    public function test_deny_authorization_request_dispatches_event_and_builds_error_redirect(): void
    {
        Event::fake([OAuthAuthorizationDenied::class]);
        $client = app(OAuthClientRepository::class)->createClient(new OAuthClientData(
            name: 'Web',
            redirectUris: ['https://client.test/callback'],
        ))->client;

        $url = app(OAuthServerService::class)->denyAuthorizationRequest(
            new OAuthAuthorizationRequest(client: $client, redirectUri: 'https://client.test/callback', state: 'xyz'),
        );

        Event::assertDispatched(OAuthAuthorizationDenied::class);
        self::assertSame('https://client.test/callback?error=access_denied&error_description=The+resource+owner+denied+the+request.&state=xyz', $url);
    }
}
