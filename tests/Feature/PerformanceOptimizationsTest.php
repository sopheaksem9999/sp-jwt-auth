<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Sopheak\JwtAuth\Services\OAuthClientRepository;
use Sopheak\JwtAuth\DTO\OAuthClientData;
use Sopheak\JwtAuth\Services\OAuthServerService;
use Sopheak\JwtAuth\DTO\OAuthAuthorizationRequest;
use Sopheak\JwtAuth\DTO\OAuthConsentContext;
use Sopheak\JwtAuth\Exceptions\OAuthInvalidGrantException;
use Sopheak\JwtAuth\Services\EmailVerificationBroker;
use Sopheak\JwtAuth\Services\ExternalIdentityStore;
use Sopheak\JwtAuth\DTO\ExternalIdentity;
use Sopheak\JwtAuth\Contracts\FirstFactorUserResolver;
use Sopheak\JwtAuth\Services\FirstFactorOtpBroker;
use Illuminate\Auth\AuthenticationException;
use Sopheak\JwtAuth\Models\FirstFactorOtpCode;
use Throwable;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Sopheak\JwtAuth\DTO\ApiKeyContext;
use Sopheak\JwtAuth\DTO\OtpDestination;
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Models\ApiKey;
use Sopheak\JwtAuth\Models\JwtAccessToken;
use Sopheak\JwtAuth\Models\JwtRefreshToken;
use Sopheak\JwtAuth\Security\HashKeyRepository;
use Sopheak\JwtAuth\Services\ApiKeyService;
use Sopheak\JwtAuth\Services\FirstFactorOtpRatePolicy;
use Sopheak\JwtAuth\Services\JwtTokenService;
use Sopheak\JwtAuth\Signing\ConfigSigningKeyRepository;
use Sopheak\JwtAuth\Support\LastUsedThrottle;
use Sopheak\JwtAuth\Support\SchemaColumnCache;
use Sopheak\JwtAuth\Tests\TestCase;

/** Behaviour of docs/superpowers/specs/2026-10-26-performance-optimization-suite-design.md */
final class PerformanceOptimizationsTest extends TestCase
{
    /** @return list<string> */
    private function captureSql(callable $callback): array
    {
        $queries = [];
        Event::listen(QueryExecuted::class, static function (QueryExecuted $event) use (&$queries): void {
            $queries[] = strtolower($event->sql);
        });
        $callback();

        return $queries;
    }

    public function test_signing_keys_are_read_from_disk_once_and_flush_reloads(): void
    {
        $dir = sys_get_temp_dir() . '/sp-jwt-key-' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir . '/priv.pem', self::privateKey());
        file_put_contents($dir . '/pub.pem', self::publicKey());
        config()->set('sp-jwt-auth.keys.items.file-key', [
            'state' => 'active',
            'private_key_path' => $dir . '/priv.pem',
            'public_key_path' => $dir . '/pub.pem',
        ]);
        $repo = new ConfigSigningKeyRepository();

        try {
            $first = $repo->forVerification('file-key');
            unlink($dir . '/priv.pem');
            unlink($dir . '/pub.pem');

            self::assertSame($first, $repo->forVerification('file-key'));
            self::assertCount(2, $repo->publicKeys());

            $repo->flush();
            $this->expectException(Throwable::class);
            $repo->forVerification('file-key');
        } finally {
            @rmdir($dir);
        }
    }

    public function test_hash_keys_are_memoized_and_active_id_stays_dynamic(): void
    {
        $repo = new HashKeyRepository();
        self::assertSame($repo->find('test-hash'), $repo->find('test-hash'));

        config()->set('sp-jwt-auth.hash_keys.items.other', ['state' => 'active', 'key' => 'other-key-other-key-other-key-00']);
        config()->set('sp-jwt-auth.hash_keys.active_id', 'other');
        self::assertSame('other', $repo->active()->id);

        $before = $repo->find('test-hash');
        $repo->flush();
        self::assertNotSame($before, $repo->find('test-hash'));
    }

    public function test_last_used_throttle_follows_interval(): void
    {
        self::assertTrue(LastUsedThrottle::due(now()), 'interval 0 writes every time');

        config()->set('sp-jwt-auth.last_used_interval_minutes', 5);
        self::assertTrue(LastUsedThrottle::due(null));
        self::assertFalse(LastUsedThrottle::due(now()->subMinutes(2)));
        self::assertTrue(LastUsedThrottle::due(now()->subMinutes(6)));
    }

    public function test_last_used_is_throttled_for_api_keys_and_event_still_fires(): void
    {
        config()->set('sp-jwt-auth.last_used_interval_minutes', 5);
        $service = app(ApiKeyService::class);
        $key = $service->createApiKey(new ApiKeyContext(ownerType: 'tenant', ownerId: '1', name: 'k'));

        $service->validateApiKey($key->plaintextKey, '127.0.0.1');
        $first = ApiKey::query()->find($key->apiKey->id)->last_used_at;
        $this->travel(2)->minutes();
        $service->validateApiKey($key->plaintextKey, '127.0.0.1');

        self::assertEquals($first, ApiKey::query()->find($key->apiKey->id)->last_used_at);

        $this->travel(5)->minutes();
        $service->validateApiKey($key->plaintextKey, '127.0.0.1');
        self::assertTrue(ApiKey::query()->find($key->apiKey->id)->last_used_at->gt($first));
    }

    public function test_rate_policy_locks_only_the_limit_keys_and_releases_on_failure(): void
    {
        config()->set('sp-jwt-auth.first_factor_otp.limits.request_per_destination', 1);
        $policy = app(FirstFactorOtpRatePolicy::class);

        $policy->reserveSend(OtpDestination::email('a@example.com'), '10.0.0.1');
        $policy->reserveSend(OtpDestination::email('b@example.com'), '10.0.0.2');

        try {
            $policy->reserveSend(OtpDestination::email('a@example.com'), '10.0.0.3');
            self::fail('Expected throttling.');
        } catch (TooManyRequestsHttpException) {
            // lock must have been released: a further call must not time out
        }

        $policy->hitVerify('10.0.0.9');
        self::assertSame(1, (new RateLimiter(Cache::store((string) config('cache.default'))))->attempts('sp-jwt-ffotp:verify:ip:10.0.0.9'));
    }

    public function test_refresh_rotation_issues_one_update_for_the_old_refresh_row(): void
    {
        $pair = app(JwtTokenService::class)->issueTokenPair($this->createUser(), TokenContext::make());

        $sql = $this->captureSql(fn () => app(JwtTokenService::class)->rotateRefreshToken($pair->refreshToken));
        $updates = array_filter($sql, static fn (string $q): bool => str_starts_with($q, 'update') && str_contains($q, 'sp_jwt_refresh_tokens'));

        self::assertCount(1, $updates);
        $old = JwtRefreshToken::query()->find($pair->refreshTokenRecord->id);
        self::assertNotNull($old->revoked_at);
        self::assertNotNull($old->replaced_by_id);
        self::assertNotNull(JwtAccessToken::query()->find($pair->accessTokenRecord->id)->revoked_at);
    }

    public function test_schema_column_cache_queries_once_across_instances(): void
    {
        $cache = app(SchemaColumnCache::class);
        $user = $this->createUser();

        $first = $cache->has($user, 'email_verified_at');
        $sql = $this->captureSql(fn () => $cache->has($user, 'email_verified_at'));

        self::assertTrue($first);
        self::assertSame([], $sql);
        self::assertSame($cache, app(SchemaColumnCache::class));
    }

    public function test_auth_code_index_migration_exists_and_is_idempotent(): void
    {
        self::assertTrue(Schema::hasIndex('sp_oauth_auth_codes', 'sp_oauth_auth_codes_active_index'));

        $migration = require __DIR__ . '/../../database/migrations/2026_10_26_000001_add_sp_oauth_auth_codes_active_index.php';
        $migration->up();

        self::assertTrue(Schema::hasIndex('sp_oauth_auth_codes', 'sp_oauth_auth_codes_active_index'));
    }

    /** @param list<string> $sql */
    private function countQueries(array $sql, string $verb, string $table): int
    {
        return count(array_filter($sql, static fn (string $q): bool => str_starts_with($q, $verb) && str_contains($q, $table)));
    }

    public function test_expired_authorization_codes_are_not_locked_or_scanned_and_still_fail_identically(): void
    {
        $client = app(OAuthClientRepository::class)->createClient(new OAuthClientData(
            name: 'SPA',
            redirectUris: ['https://client.test/cb'],
            allowedGrants: ['authorization_code'],
            allowedScopes: ['a'],
            confidential: false,
        ))->client;
        $oauth = app(OAuthServerService::class);
        $code = $oauth->approveAuthorizationRequest(
            new OAuthAuthorizationRequest(client: $client, redirectUri: 'https://client.test/cb', scopes: ['a']),
            $this->createUser(),
            new OAuthConsentContext(scopes: ['a']),
        );
        $code->record->forceFill(['expires_at' => now()->subMinute()])->save();

        $request = request()->create('/oauth/token', 'POST', [
            'grant_type' => 'authorization_code', 'client_id' => $client->id,
            'redirect_uri' => 'https://client.test/cb', 'code' => $code->code,
        ]);
        $sql = $this->captureSql(function () use ($oauth, $request): void {
            try {
                $oauth->issueTokenFromRequest($request);
                self::fail('Expected an invalid grant.');
            } catch (OAuthInvalidGrantException $oAuthInvalidGrantException) {
                self::assertSame('OAuth authorization code is invalid.', $oAuthInvalidGrantException->getMessage());
            }
        });

        $selects = array_filter($sql, static fn (string $q): bool => str_starts_with($q, 'select') && str_contains($q, 'sp_oauth_auth_codes'));
        self::assertNotEmpty($selects);
        foreach ($selects as $query) {
            self::assertStringContainsString('"expires_at" >', $query);
        }
    }

    public function test_email_verification_resolves_the_user_once(): void
    {
        $user = $this->createUser();
        $dispatch = app(EmailVerificationBroker::class)->createVerificationToken($user, 'user@example.com');

        $sql = $this->captureSql(fn () => app(EmailVerificationBroker::class)->verifyEmailToken($dispatch->token));

        self::assertSame(1, $this->countQueries($sql, 'select', 'from "users"'));
    }

    public function test_identity_and_consent_upserts_do_not_pre_query_the_id(): void
    {
        $store = app(ExternalIdentityStore::class);
        $identity = new ExternalIdentity(provider: 'google', providerUserId: 'g1');
        $store->store($identity);
        $this->travel(5)->seconds(); // last_resolved_at changes, so the row is dirty

        $sql = $this->captureSql(fn () => $store->store($identity));

        self::assertSame(1, $this->countQueries($sql, 'select', 'sp_jwt_external_identities'));
        self::assertSame(1, $this->countQueries($sql, 'update', 'sp_jwt_external_identities'));
    }

    public function test_failed_first_factor_attempt_does_not_reselect_the_code(): void
    {
        config()->set('sp-jwt-auth.first_factor_otp.enabled', true);
        config()->set('sp-jwt-auth.first_factor_otp.test_mode', true);
        config()->set('sp-jwt-auth.first_factor_otp.test_code', '424242');
        config()->set('sp-jwt-auth.first_factor_otp.limits.verify_per_ip', 100);

        $user = $this->createUser();
        $this->app->instance(FirstFactorUserResolver::class, new readonly class ($user) implements FirstFactorUserResolver {
            public function __construct(private Authenticatable $user)
            {
            }

            public function resolve(OtpDestination $destination, string $purpose): Authenticatable
            {
                return $this->user;
            }

            public function create(OtpDestination $destination, string $purpose, ?string $requestedType): ?Authenticatable
            {
                return null;
            }
        });
        $broker = app(FirstFactorOtpBroker::class);
        $dispatch = $broker->request(OtpDestination::email('user@example.com'), 'login', null, '10.1.1.1');

        $sql = $this->captureSql(function () use ($broker, $dispatch): void {
            try {
                $broker->verify($dispatch->otpId, '000000', null, '10.1.1.1');
            } catch (AuthenticationException) {
            }
        });

        self::assertSame(1, $this->countQueries($sql, 'select', 'sp_jwt_first_factor_otp_codes'));
        self::assertSame(1, FirstFactorOtpCode::query()->find($dispatch->otpId)->attempts);
    }
}
