<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Override;
use Illuminate\Contracts\Auth\Authenticatable;
use Sopheak\JwtAuth\Contracts\FirstFactorUserResolver;
use Sopheak\JwtAuth\DTO\OtpDestination;
use Sopheak\JwtAuth\Tests\Fixtures\User;
use Sopheak\JwtAuth\Tests\TestCase;

final class FirstFactorOtpHttpTest extends TestCase
{
    #[Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('sp-jwt-auth.first_factor_otp.enabled', true);
        $app['config']->set('sp-jwt-auth.first_factor_otp.resend_cooldown_seconds', 0); // Deviation G
    }

    private function bindResolver(User $user): void
    {
        $this->app->instance(FirstFactorUserResolver::class, new readonly class ($user) implements FirstFactorUserResolver {
            public function __construct(private User $user)
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
    }

    public function test_request_endpoint_returns_masked_destination(): void
    {
        $this->bindResolver($this->createUser());

        $response = $this->postJson('/otp/request', [
            'destination' => 'user@example.com',
            'channel' => 'email',
            'purpose' => 'login',
        ]);

        $response->assertStatus(202);
        $response->assertJsonPath('destination_masked', 'u***@example.com');
        $response->assertJsonMissing(['code']);
    }

    public function test_request_endpoint_rejects_invalid_channel(): void
    {
        $this->bindResolver($this->createUser());

        $this->postJson('/otp/request', [
            'destination' => 'user@example.com',
            'channel' => 'carrier-pigeon',
            'purpose' => 'login',
        ])->assertStatus(422);
    }

    public function test_verify_endpoint_returns_token_pair(): void
    {
        config()->set('sp-jwt-auth.first_factor_otp.test_mode', true);
        config()->set('sp-jwt-auth.first_factor_otp.test_code', '424242');

        $this->bindResolver($this->createUser());

        $request = $this->postJson('/otp/request', [
            'destination' => 'user@example.com',
            'channel' => 'email',
            'purpose' => 'login',
        ]);

        $response = $this->postJson('/otp/verify', [
            'otp_id' => $request->json('otp_id'),
            'code' => '424242',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['access_token', 'refresh_token', 'token_type', 'expires_in']);
    }

    public function test_request_endpoint_rate_limits_per_destination_with_retry_after(): void
    {
        $this->bindResolver($this->createUser());

        config()->set('sp-jwt-auth.first_factor_otp.limits.request_per_destination', 2);

        $payload = ['destination' => 'ratelimit@example.com', 'channel' => 'email', 'purpose' => 'login']; // Deviation I

        $this->postJson('/otp/request', $payload)->assertStatus(202);
        $this->postJson('/otp/request', $payload)->assertStatus(202);

        $this->postJson('/otp/request', $payload)->assertStatus(429)->assertHeader('Retry-After');
    }
}
