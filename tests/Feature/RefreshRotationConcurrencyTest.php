<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Event;
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Events\RefreshTokenReuseDetected;
use Sopheak\JwtAuth\Models\JwtRefreshToken;
use Sopheak\JwtAuth\Services\JwtTokenService;
use Sopheak\JwtAuth\Tests\TestCase;

final class RefreshRotationConcurrencyTest extends TestCase
{
    public function test_concurrent_reuse_inside_transaction_triggers_reuse_detection_and_event(): void
    {
        Event::fake([RefreshTokenReuseDetected::class]);

        $user = $this->createUser();
        $service = app(JwtTokenService::class);
        $pair = $service->issueTokenPair($user, TokenContext::make()->sessionId('session-concurrent'));

        $firstRead = true;
        JwtRefreshToken::retrieved(static function (JwtRefreshToken $model) use (&$firstRead, $pair): void {
            if ($firstRead && $model->id === $pair->refreshTokenRecord->id) {
                $firstRead = false;
                // Simulate another thread committing revocation right after the pre-check:
                JwtRefreshToken::query()->whereKey($pair->refreshTokenRecord->id)->update(['revoked_at' => now()]);
            }
        });

        try {
            $service->rotateRefreshToken($pair->refreshToken);
            self::fail('Expected AuthenticationException');
        } catch (AuthenticationException) {
            // Expected
        }

        Event::assertDispatched(RefreshTokenReuseDetected::class, static fn(RefreshTokenReuseDetected $event): bool => $event->refreshToken->id === $pair->refreshTokenRecord->id);
    }
}
