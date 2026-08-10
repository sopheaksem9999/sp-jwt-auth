<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Override;
use Illuminate\Contracts\Auth\Authenticatable;
use Sopheak\JwtAuth\Contracts\FirstFactorUserResolver;
use Sopheak\JwtAuth\DTO\OtpDestination;
use Sopheak\JwtAuth\Tests\Fixtures\TestEnvelope;
use Sopheak\JwtAuth\Tests\Fixtures\TestUserResource;
use Sopheak\JwtAuth\Tests\Fixtures\User;
use Sopheak\JwtAuth\Tests\TestCase;

final class FirstFactorOtpEnvelopeTest extends TestCase
{
    #[Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('sp-jwt-auth.first_factor_otp.enabled', true);
        $app['config']->set('sp-jwt-auth.first_factor_otp.resend_cooldown_seconds', 0);
        $app['config']->set('sp-jwt-auth.first_factor_otp.response_envelope', 'laravel');
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

    public function test_request_endpoint_wraps_payload_in_data(): void
    {
        $this->bindResolver($this->createUser());

        $response = $this->postJson('/otp/request', [
            'destination' => 'user@example.com',
            'channel' => 'email',
            'purpose' => 'login',
        ]);

        $response->assertStatus(202);
        $response->assertJsonPath('data.destination_masked', 'u***@example.com');
        $response->assertJsonStructure(['data' => ['otp_id', 'destination_masked', 'expires_in']]);
    }

    public function test_verify_endpoint_includes_user_in_data(): void
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
            'otp_id' => $request->json('data.otp_id'),
            'code' => '424242',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['access_token', 'refresh_token', 'token_type', 'expires_in', 'user']]);
    }

    public function test_verify_endpoint_serializes_user_via_resource(): void
    {
        config()->set('sp-jwt-auth.first_factor_otp.test_mode', true);
        config()->set('sp-jwt-auth.first_factor_otp.test_code', '424242');
        config()->set('sp-jwt-auth.first_factor_otp.user_resource', TestUserResource::class);

        $this->bindResolver($this->createUser());

        $request = $this->postJson('/otp/request', [
            'destination' => 'user@example.com',
            'channel' => 'email',
            'purpose' => 'login',
        ]);

        $response = $this->postJson('/otp/verify', [
            'otp_id' => $request->json('data.otp_id'),
            'code' => '424242',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.user.resource', true);
    }

    public function test_custom_envelope_class_wraps_responses(): void
    {
        config()->set('sp-jwt-auth.first_factor_otp.response_envelope', TestEnvelope::class);

        $this->bindResolver($this->createUser());

        $this->postJson('/otp/request', [
            'destination' => 'user@example.com',
            'channel' => 'email',
            'purpose' => 'login',
        ])->assertStatus(202)->assertJsonStructure(['wrapped' => ['otp_id', 'destination_masked', 'expires_in']]);
    }
}
