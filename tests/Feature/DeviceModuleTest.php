<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Sopheak\JwtAuth\DTO\DeviceRegistrationResult;
use Illuminate\Support\Str;
use Illuminate\Contracts\Auth\Authenticatable;
use Sopheak\JwtAuth\Support\SetupValidator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Override;
use Sopheak\JwtAuth\Contracts\AttestationVerifier;
use Sopheak\JwtAuth\DTO\DeviceRegistration;
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Events\DeviceRegistered;
use Sopheak\JwtAuth\Events\DeviceRevoked;
use Sopheak\JwtAuth\Events\DeviceUpgradedToUser;
use Sopheak\JwtAuth\Models\Device;
use Sopheak\JwtAuth\Models\DeviceUser;
use Sopheak\JwtAuth\Models\JwtAccessToken;
use Sopheak\JwtAuth\Models\JwtRefreshToken;
use Sopheak\JwtAuth\Services\DeviceService;
use Sopheak\JwtAuth\Services\JwtTokenService;
use Sopheak\JwtAuth\Tests\TestCase;
use Illuminate\Auth\AuthenticationException;

final class DeviceModuleTest extends TestCase
{
    #[Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('sp-jwt-auth.devices.enabled', true);
        $app['config']->set('sp-jwt-auth.token_endpoints.enabled', true);
    }

    private function service(): DeviceService
    {
        return app(DeviceService::class);
    }

    private function register(string $name = 'iPhone'): DeviceRegistrationResult
    {
        return $this->service()->register(new DeviceRegistration(platform: 'ios', name: $name, clientVersion: '1.0'));
    }

    // --- registration ---------------------------------------------------------------

    public function test_register_returns_the_credential_once_and_stores_only_its_hash(): void
    {
        Event::fake([DeviceRegistered::class]);

        $result = $this->register();
        [$publicId, $secret] = explode('.', $result->credential, 2);

        self::assertSame($result->device->public_id, $publicId);
        self::assertTrue(Str::isUuid($publicId));
        self::assertSame(64, strlen($secret));
        self::assertDatabaseMissing('sp_jwt_devices', ['secret_hash' => $secret]);
        self::assertNotNull($result->device->fresh()->hash_key_id);
        self::assertSame(Device::class, $result->tokens->accessTokenRecord->user_type);
        self::assertSame($publicId, $result->tokens->accessTokenRecord->device_id);
        self::assertSame(['device'], $result->tokens->accessTokenRecord->scopes);
        Event::assertDispatched(DeviceRegistered::class);
    }

    public function test_register_endpoint_validates_the_platform_and_returns_tokens(): void
    {
        $this->postJson('/auth/devices', ['platform' => 'toaster'])->assertStatus(422);

        $this->postJson('/auth/devices', ['platform' => 'web', 'name' => 'Chrome'])
            ->assertCreated()
            ->assertJsonStructure(['device_id', 'device_credential', 'access_token', 'refresh_token', 'token_type', 'expires_in']);
    }

    public function test_register_endpoint_is_rate_limited_per_ip(): void
    {
        config()->set('sp-jwt-auth.devices.rate_limit.per_minute', 2);
        // The limiter closure reads the config when it runs, so no re-registration is needed.
        $this->postJson('/auth/devices', ['platform' => 'web'])->assertCreated();
        $this->postJson('/auth/devices', ['platform' => 'web'])->assertCreated();

        $this->postJson('/auth/devices', ['platform' => 'web'])->assertStatus(429);
    }

    public function test_credential_exchange_issues_a_fresh_pair_and_rejects_bad_or_revoked_credentials(): void
    {
        $result = $this->register();
        $secret = explode('.', $result->credential, 2)[1];

        $this->postJson('/auth/devices/token', ['credential' => $result->credential])
            ->assertOk()->assertJsonPath('device_id', $result->device->public_id);
        $this->postJson('/auth/devices/token', ['credential' => $result->device->public_id . '.' . str_repeat('0', 64)])->assertUnauthorized();
        $this->postJson('/auth/devices/token', ['credential' => 'nonsense'])->assertUnauthorized();

        $this->service()->revoke($result->device->public_id);
        $this->postJson('/auth/devices/token', ['credential' => $result->device->public_id . '.' . $secret])->assertUnauthorized();
    }

    // --- tokens, guard and middleware isolation -----------------------------------------

    public function test_device_token_passes_device_middleware_and_is_rejected_by_user_routes(): void
    {
        Route::middleware('sp.jwt')->get('/user-only', fn (): array => ['ok' => true]);
        Route::middleware('sp.jwt.device')->get('/device-only', fn (): array => ['device' => request()->attributes->get('sp_device')->public_id]);
        Route::middleware('sp.jwt.any')->get('/either', fn (): array => ['device' => request()->attributes->get('sp_device')?->public_id]);

        $device = $this->register();
        $user = $this->createUser();
        $userPair = app(JwtTokenService::class)->issueTokenPair($user, TokenContext::make());

        $this->withToken($device->tokens->accessToken)->getJson('/user-only')->assertUnauthorized();
        $this->withToken($device->tokens->accessToken)->getJson('/device-only')->assertOk()->assertJsonPath('device', $device->device->public_id);
        $this->withToken($device->tokens->accessToken)->getJson('/either')->assertOk()->assertJsonPath('device', $device->device->public_id);

        $this->withToken($userPair->accessToken)->getJson('/user-only')->assertOk();
        $this->withToken($userPair->accessToken)->getJson('/device-only')->assertUnauthorized();
        $this->withToken($userPair->accessToken)->getJson('/either')->assertOk()->assertJsonPath('device', null);
        $this->flushHeaders()->getJson('/either')->assertUnauthorized();
    }

    public function test_device_refresh_goes_through_the_core_token_endpoint(): void
    {
        $device = $this->register();

        $response = $this->postJson('/auth/token/refresh', ['refresh_token' => $device->tokens->refreshToken]);

        $response->assertOk();

        $next = JwtAccessToken::query()->where('device_id', $device->device->public_id)->latest('created_at')->first();
        self::assertSame(Device::class, $next->user_type);
    }

    public function test_revoked_device_is_rejected_even_with_a_live_token(): void
    {
        Route::middleware('sp.jwt.device')->get('/device-live', fn (): array => ['ok' => true]);
        $device = $this->register();
        $device->device->forceFill(['revoked_at' => now()])->save();

        $this->withToken($device->tokens->accessToken)->getJson('/device-live')->assertUnauthorized();
    }

    // --- upgrade -----------------------------------------------------------------------

    public function test_upgrade_ends_only_the_guest_session_and_embeds_the_device(): void
    {
        Event::fake([DeviceUpgradedToUser::class]);
        $device = $this->register();
        $other = $this->register('Pixel');
        $user = $this->createUser();
        $bystander = app(JwtTokenService::class)->issueTokenPair($this->createUser(['email' => 'b@example.com', 'username' => 'b']), TokenContext::make());

        $pair = $this->service()->upgradeToUser($device->device, $user);

        self::assertNotNull(JwtAccessToken::query()->find($device->tokens->accessTokenRecord->id)->revoked_at);
        self::assertNotNull(JwtRefreshToken::query()->find($device->tokens->refreshTokenRecord->id)->revoked_at);
        self::assertNull(JwtAccessToken::query()->find($other->tokens->accessTokenRecord->id)->revoked_at);
        self::assertNull(JwtAccessToken::query()->find($bystander->accessTokenRecord->id)->revoked_at);
        self::assertSame($user::class, $pair->accessTokenRecord->user_type);
        self::assertSame($device->device->public_id, $pair->accessTokenRecord->device_id);
        self::assertSame('iPhone', $pair->accessTokenRecord->device_name);
        Event::assertDispatched(DeviceUpgradedToUser::class);
    }

    public function test_upgrade_can_keep_the_guest_session_and_twice_keeps_one_link_row(): void
    {
        $device = $this->register();
        $user = $this->createUser();

        $this->service()->upgradeToUser($device->device, $user, endGuestSessions: false);
        $first = DeviceUser::query()->firstOrFail();
        $this->service()->upgradeToUser($device->device, $user, endGuestSessions: false);

        self::assertNull(JwtAccessToken::query()->find($device->tokens->accessTokenRecord->id)->revoked_at);
        self::assertSame(1, DeviceUser::query()->count());
        self::assertSame($first->id, DeviceUser::query()->firstOrFail()->id);
    }

    public function test_a_revoked_device_cannot_be_upgraded(): void
    {
        $device = $this->register();
        $this->service()->revoke($device->device->public_id);

        $this->expectException(AuthenticationException::class);
        $this->service()->upgradeToUser($device->device->fresh(), $this->createUser());
    }

    // --- revocation -------------------------------------------------------------------

    public function test_revoke_kills_device_tokens_and_every_users_tokens_on_that_device(): void
    {
        Event::fake([DeviceRevoked::class]);
        $device = $this->register();
        $userPair = $this->service()->upgradeToUser($device->device, $this->createUser());
        $elsewhere = app(JwtTokenService::class)->issueTokenPair($this->createUser(['email' => 'c@example.com', 'username' => 'c']), TokenContext::make());

        $this->service()->revoke($device->device->public_id);

        foreach ([$device->tokens, $userPair] as $pair) {
            self::assertNotNull(JwtAccessToken::query()->find($pair->accessTokenRecord->id)->revoked_at);
            self::assertNotNull(JwtRefreshToken::query()->find($pair->refreshTokenRecord->id)->revoked_at);
        }

        self::assertNull(JwtAccessToken::query()->find($elsewhere->accessTokenRecord->id)->revoked_at);
        self::assertNotNull($device->device->fresh()->revoked_at);
        self::assertNotNull(DeviceUser::query()->firstOrFail()->revoked_at);
        Event::assertDispatched(DeviceRevoked::class, fn (DeviceRevoked $e): bool => !$e->user instanceof Authenticatable);
    }

    public function test_revoke_for_user_affects_only_that_user_and_lists_respect_it(): void
    {
        $device = $this->register();
        $alice = $this->createUser();
        $bob = $this->createUser(['email' => 'bob@example.com', 'username' => 'bob']);
        $alicePair = $this->service()->upgradeToUser($device->device, $alice, endGuestSessions: false);
        $bobPair = $this->service()->upgradeToUser($device->device, $bob, endGuestSessions: false);

        self::assertCount(1, $this->service()->devicesForUser($alice));

        $this->service()->revokeForUser($alice, $device->device->public_id);

        self::assertNotNull(JwtAccessToken::query()->find($alicePair->accessTokenRecord->id)->revoked_at);
        self::assertNull(JwtAccessToken::query()->find($bobPair->accessTokenRecord->id)->revoked_at);
        self::assertNull($device->device->fresh()->revoked_at);
        self::assertCount(0, $this->service()->devicesForUser($alice));
        self::assertCount(1, $this->service()->devicesForUser($bob));
    }

    // --- attestation ------------------------------------------------------------------

    public function test_attestation_optional_without_a_verifier_registers_unattested(): void
    {
        self::assertNull($this->service()->register(new DeviceRegistration(platform: 'ios', attestation: ['token' => 'x']))->device->attested_at);
    }

    public function test_required_attestation_fails_closed_without_a_verifier(): void
    {
        config()->set('sp-jwt-auth.devices.attestation.required', true);

        $this->postJson('/auth/devices', ['platform' => 'ios', 'attestation' => ['token' => 'x']])->assertUnauthorized();
        self::assertSame(0, Device::query()->count());
    }

    public function test_custom_verifier_is_used_when_attestation_is_sent_or_required(): void
    {
        $this->app->instance(AttestationVerifier::class, new class implements AttestationVerifier {
            public function verify(DeviceRegistration|Device $subject, array $attestation): bool
            {
                return ($attestation['token'] ?? null) === 'good';
            }
        });
        $this->app->forgetInstance(DeviceService::class);

        $ok = $this->service()->register(new DeviceRegistration(platform: 'ios', attestation: ['token' => 'good']));
        self::assertNotNull($ok->device->attested_at);
        self::assertNull($this->service()->register(new DeviceRegistration(platform: 'ios'))->device->attested_at);

        try {
            $this->service()->register(new DeviceRegistration(platform: 'ios', attestation: ['token' => 'bad']));
            self::fail('A failed attestation must be rejected.');
        } catch (AuthenticationException) {
            self::assertTrue(true);
        }

        config()->set('sp-jwt-auth.devices.attestation.required', true);
        $this->expectException(AuthenticationException::class);
        $this->service()->register(new DeviceRegistration(platform: 'ios'));
    }

    // --- prune & validate ----------------------------------------------------------------

    public function test_prune_devices_removes_inactive_and_revoked_devices_with_their_tokens_in_chunks(): void
    {
        $stale = [];
        for ($i = 0; $i < 5; $i++) {
            $stale[] = $this->register('stale-' . $i);
        }

        foreach ($stale as $result) {
            $result->device->forceFill(['last_seen_at' => now()->subDays(200)])->save();
        }

        $active = $this->register('active');
        $revoked = $this->register('revoked');
        $this->service()->revoke($revoked->device->public_id);
        $revoked->device->fresh()->forceFill(['revoked_at' => now()->subDays(200)])->save();

        $this->artisan('sp-jwt-auth:prune-devices', ['--dry-run' => true])->expectsOutput('Would prune 6 devices.')->assertExitCode(0);
        self::assertSame(7, Device::query()->count());

        $this->artisan('sp-jwt-auth:prune-devices', ['--chunk' => 2])->expectsOutput('Pruned 6 devices.')->assertExitCode(0);

        self::assertSame([$active->device->id], Device::query()->pluck('id')->all());
        self::assertSame(1, JwtAccessToken::query()->where('user_type', Device::class)->count());
        self::assertSame(1, JwtRefreshToken::query()->where('user_type', Device::class)->count());
    }

    public function test_validate_reports_required_attestation_without_a_verifier(): void
    {
        config()->set('sp-jwt-auth.devices.attestation.required', true);

        $report = app(SetupValidator::class)->report();

        self::assertContains('sp-jwt-auth.devices.attestation.required is true but no AttestationVerifier is bound; device registration will always fail.', $report['errors']);
    }

    public function test_device_tables_exist_with_the_expected_unique_constraint(): void
    {
        self::assertTrue(Schema::hasIndex('sp_jwt_devices', 'sp_jwt_devices_public_unique'));
        self::assertTrue(Schema::hasIndex('sp_jwt_device_users', 'sp_jwt_device_users_unique'));
        self::assertSame(0, DB::table('sp_jwt_devices')->count());
    }
}
