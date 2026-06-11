<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

<<<<<<< HEAD
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Event;
=======
>>>>>>> 11e06a7 (feat: add complete Laravel JWT auth package with OAuth support)
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

<<<<<<< HEAD
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

=======
>>>>>>> 11e06a7 (feat: add complete Laravel JWT auth package with OAuth support)
    public function test_package_does_not_replace_web_guard(): void
    {
        config()->set('auth.guards.web', ['driver' => 'session', 'provider' => 'users']);

        self::assertSame('session', config('auth.guards.web.driver'));
    }
}
