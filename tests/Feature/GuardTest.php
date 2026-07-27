<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Guards\JwtGuard;
use Sopheak\JwtAuth\Services\JwtTokenService;
use Sopheak\JwtAuth\Tests\TestCase;

final class GuardTest extends TestCase
{
    public function test_auth_api_authenticates_valid_bearer_token(): void
    {
        Route::middleware('auth:api')->get('/guard-user', fn(): array => [
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

    public function test_guard_reuses_resolved_user_within_same_request(): void
    {
        $tokenTouchCount = 0;

        Event::listen(QueryExecuted::class, function (QueryExecuted $event) use (&$tokenTouchCount): void {
            $sql = strtolower($event->sql);

            if (str_starts_with($sql, 'update') && str_contains($sql, 'sp_jwt_access_tokens')) {
                $tokenTouchCount++;
            }
        });

        Route::middleware('auth:api')->get('/guard-user-cache', function (): array {
            $first = auth('api')->user();
            $second = auth('api')->user();

            return [
                'same_user_instance' => $first === $second,
                'id' => auth('api')->id(),
            ];
        });

        $user = $this->createUser();
        $pair = app(JwtTokenService::class)->issueTokenPair($user, TokenContext::make()->scopes(['client']));

        $this->withToken($pair->accessToken)
            ->getJson('/guard-user-cache')
            ->assertOk()
            ->assertJson([
                'same_user_instance' => true,
                'id' => $user->getAuthIdentifier(),
            ]);

        self::assertSame(1, $tokenTouchCount);
    }

    public function test_guard_methods_and_edge_cases(): void
    {
        /** @var JwtGuard $guard */
        $guard = auth('api');

        // Unauthenticated guard state
        self::assertNull($guard->user());
        self::assertNull($guard->id());
        self::assertFalse($guard->check());
        self::assertTrue($guard->guest());
        self::assertFalse($guard->hasUser());
        self::assertFalse($guard->validate(['username' => 'foo']));

        // Explicit setUser
        $user = $this->createUser();
        $guard->setUser($user);

        self::assertTrue($guard->hasUser());
        self::assertTrue($guard->check());
        self::assertFalse($guard->guest());
        self::assertSame($user, $guard->user());
        self::assertSame($user->getAuthIdentifier(), $guard->id());

        // setRequest resets resolved user
        $guard->setRequest(Request::create('/test', 'GET'));
        self::assertFalse($guard->hasUser());
        self::assertNull($guard->user());
    }

    public function test_guard_returns_null_when_token_is_invalid_or_user_deleted(): void
    {
        /** @var JwtGuard $guard */
        $guard = auth('api');

        // Invalid token
        $request = Request::create('/test', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer invalid-token-string',
        ]);
        $guard->setRequest($request);
        self::assertNull($guard->user());

        // Token valid but user removed from DB
        $user = $this->createUser();
        $pair = app(JwtTokenService::class)->issueTokenPair($user, TokenContext::make());

        $requestWithToken = Request::create('/test', 'GET', [], [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $pair->accessToken,
        ]);
        $guard->setRequest($requestWithToken);

        $user->delete();
        self::assertNull($guard->user());
    }

    public function test_package_does_not_replace_web_guard(): void
    {
        config()->set('auth.guards.web', ['driver' => 'session', 'provider' => 'users']);

        self::assertSame('session', config('auth.guards.web.driver'));
    }
}
