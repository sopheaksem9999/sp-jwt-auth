<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Services;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Events\MfaChallengeCompleted;
use Sopheak\JwtAuth\Events\MfaChallengeCreated;
use Sopheak\JwtAuth\Models\MfaChallenge;
use Sopheak\JwtAuth\Services\Concerns\SerializesTokenContext;

final class MfaChallengeBroker
{
    use SerializesTokenContext;

    public function create(Authenticatable $user, TokenContext $context): MfaChallenge
    {
        $sessionId = $context->sessionId ?: (string) Str::uuid();
        $context = $context->sessionId($sessionId);

        $challenge = MfaChallenge::query()->create([
            'id' => (string) Str::uuid(),
            'user_type' => $user::class,
            'user_id' => (string) $user->getAuthIdentifier(),
            'session_id' => $sessionId,
            'context' => $this->contextToArray($context),
            'methods' => ['otp'],
            'expires_at' => now()->addMinutes((int) config('sp-jwt-auth.mfa.challenge_ttl_minutes', 5)),
        ]);

        Event::dispatch(new MfaChallengeCreated($challenge));

        return $challenge;
    }

    public function resolve(string $challengeId): MfaChallenge
    {
        $challenge = MfaChallenge::query()->find($challengeId);

        if (! $challenge instanceof MfaChallenge || $challenge->expires_at->isPast() || $challenge->completed_at !== null) {
            throw new AuthenticationException('MFA challenge is invalid.');
        }

        return $challenge;
    }

    public function complete(string $challengeId): TokenContext
    {
        $challenge = $this->resolve($challengeId);
        $challenge->forceFill(['completed_at' => now()])->save();
        Event::dispatch(new MfaChallengeCompleted($challenge));

        return $this->contextFromArray($challenge->context ?? []);
    }

    public function expire(string $challengeId): void
    {
        MfaChallenge::query()->whereKey($challengeId)->update(['expires_at' => now()]);
    }
}
