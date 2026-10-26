<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Override;
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Services\JwtTokenService;
use Sopheak\JwtAuth\Tests\Fixtures\User;
use Sopheak\JwtAuth\Tests\TestCase;

final class CustomGuardRevokeRouteTest extends TestCase
{
    #[Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('sp-jwt-auth.token_endpoints.enabled', true);
        $app['config']->set('sp-jwt-auth.guard', 'jwt');
        $app['config']->set('auth.guards.jwt', ['driver' => 'sp-jwt', 'provider' => 'users']);
        $app['config']->set('auth.guards.api', null);
        $app['config']->set('auth.providers.users.model', User::class);
    }

    public function test_revoke_uses_configured_guard_and_returns_an_empty_object(): void
    {
        $pair = app(JwtTokenService::class)->issueTokenPair($this->createUser(), TokenContext::make());

        $response = $this->withToken($pair->accessToken)->postJson('/auth/token/revoke');

        $response->assertOk();
        self::assertSame('{}', $response->getContent());
    }
}
