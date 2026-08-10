<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Override;
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Services\JwtTokenService;
use Sopheak\JwtAuth\Tests\TestCase;

final class JwtTokenEnvelopeTest extends TestCase
{
    #[Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('sp-jwt-auth.token_endpoints.enabled', true);
        $app['config']->set('sp-jwt-auth.token_endpoints.response_envelope', 'laravel');
    }

    public function test_refresh_endpoint_wraps_pair_in_data(): void
    {
        $user = $this->createUser();
        $pair = $this->app->make(JwtTokenService::class)->issueTokenPair($user, TokenContext::make());

        $this->postJson('/auth/token/refresh', ['refresh_token' => $pair->refreshToken])
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['access_token', 'refresh_token', 'token_type', 'expires_in']]);
    }

    public function test_revoke_endpoint_wraps_empty_data(): void
    {
        $user = $this->createUser();
        $pair = $this->app->make(JwtTokenService::class)->issueTokenPair($user, TokenContext::make());

        $this->withToken($pair->accessToken)
            ->postJson('/auth/token/revoke')
            ->assertStatus(200)
            ->assertJson(['data' => []]);
    }
}
