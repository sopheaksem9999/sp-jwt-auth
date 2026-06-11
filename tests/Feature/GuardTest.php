<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Services\JwtTokenService;
use Sopheak\JwtAuth\Tests\TestCase;

final class GuardTest extends TestCase
{
    public function test_auth_api_authenticates_valid_bearer_token(): void
    {
        Route::middleware('auth:api')->get('/guard-user', fn (): array => [
            'id' => auth('api')->id(),
            'can_client' => auth('api')->user()->tokenCan('client'),
        ]);

        $user = $this->createUser();
        $pair = app(JwtTokenService::class)->issueTokenPair($user, TokenContext::make()->scopes(['client']));

        $this->withToken($pair->accessToken)
            ->getJson('/guard-user')
            ->assertOk()
            ->assertJson(['id' => $user->getAuthIdentifier(), 'can_client' => true]);
    }

    public function test_package_does_not_replace_web_guard(): void
    {
        config()->set('auth.guards.web', ['driver' => 'session', 'provider' => 'users']);

        self::assertSame('session', config('auth.guards.web.driver'));
    }
}
