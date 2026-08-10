<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Override;
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Services\JwtTokenService;
use Sopheak\JwtAuth\Tests\TestCase;

final class JwtTokenHttpTest extends TestCase
{
    #[Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('sp-jwt-auth.token_endpoints.enabled', true);
    }

    private function issuePair(): array
    {
        $user = $this->createUser();
        $pair = $this->app->make(JwtTokenService::class)->issueTokenPair($user, TokenContext::make());

        return [$user, $pair];
    }

    public function test_refresh_endpoint_rotates_valid_pair(): void
    {
        [, $pair] = $this->issuePair();

        $response = $this->postJson('/auth/token/refresh', ['refresh_token' => $pair->refreshToken]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['access_token', 'refresh_token', 'token_type', 'expires_in']);
    }

    public function test_refresh_endpoint_rejects_invalid_token_with_401(): void
    {
        $this->postJson('/auth/token/refresh', ['refresh_token' => 'not-a-real-token.at-all'])
            ->assertStatus(401);
    }

    public function test_refresh_endpoint_rejects_reused_token_with_401(): void
    {
        [, $pair] = $this->issuePair();

        $this->postJson('/auth/token/refresh', ['refresh_token' => $pair->refreshToken])->assertStatus(200);

        $this->postJson('/auth/token/refresh', ['refresh_token' => $pair->refreshToken])
            ->assertStatus(401);
    }

    public function test_revoke_endpoint_revokes_whole_session(): void
    {
        $user = $this->createUser();
        $pair = $this->app->make(JwtTokenService::class)->issueTokenPair($user, TokenContext::make());

        $this->withToken($pair->accessToken)
            ->postJson('/auth/token/revoke')
            ->assertStatus(200);

        $this->postJson('/auth/token/refresh', ['refresh_token' => $pair->refreshToken])
            ->assertStatus(401);
    }

    public function test_revoke_endpoint_requires_authentication(): void
    {
        $this->postJson('/auth/token/revoke')
            ->assertStatus(401);
    }
}
