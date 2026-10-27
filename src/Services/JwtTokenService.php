<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Services;

use Throwable;
use RuntimeException;
use Carbon\CarbonImmutable;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\DTO\TokenPair;
use Sopheak\JwtAuth\Events\AllUserTokensRevoked;
use Sopheak\JwtAuth\Events\RefreshTokenReuseDetected;
use Sopheak\JwtAuth\Events\SessionRevoked;
use Sopheak\JwtAuth\Events\TokenIssued;
use Sopheak\JwtAuth\Events\TokenRefreshed;
use Sopheak\JwtAuth\Events\TokenRevoked;
use Sopheak\JwtAuth\Models\JwtAccessToken;
use Sopheak\JwtAuth\Models\JwtRefreshToken;
use Sopheak\JwtAuth\Security\SecretHasher;
use Sopheak\JwtAuth\Signing\SigningKeyRepository;
use Sopheak\JwtAuth\Support\HookRegistry;

final readonly class JwtTokenService
{
    public function __construct(
        private SigningKeyRepository $keys,
        private SecretHasher $hasher,
        private HookRegistry $hooks,
    ) {}

    public function issueTokenPair(Authenticatable $user, TokenContext $context): TokenPair
    {
        foreach ($this->hooks->validateTokenContextHooks() as $hook) {
            $callable = is_string($hook) ? app($hook) : $hook;
            $callable->validate($user, $context);
        }

        foreach ($this->hooks->beforeTokenIssueHooks() as $hook) {
            $callable = is_string($hook) ? app($hook) : $hook;
            $context = $callable($user, $context);
        }

        $key = $this->keys->active();
        $now = CarbonImmutable::now();
        $accessExpiresAt = $now->addMinutes((int) config('sp-jwt-auth.access_ttl_minutes', 15));
        $refreshExpiresAt = $now->addDays((int) config('sp-jwt-auth.refresh_ttl_days', 60));
        $accessId = (string) Str::uuid();
        $refreshId = (string) Str::uuid();
        $sessionId = $context->sessionId ?: (string) Str::uuid();
        $issuer = (string) config('sp-jwt-auth.issuer');
        $audience = $context->audience ?? config('sp-jwt-auth.audience');
        $subject = $context->subjectValue();

        $accessToken = JwtAccessToken::query()->create([
            'id' => $accessId,
            'user_type' => $user::class,
            'user_id' => (string) $user->getAuthIdentifier(),
            'session_id' => $sessionId,
            'device_id' => $context->deviceId,
            'device_name' => $context->deviceName,
            'ip_address' => $this->requestIp($context),
            'user_agent' => $this->requestUserAgent($context),
            'subject_type' => $subject?->type,
            'subject_id' => $subject?->id,
            'scopes' => $context->scopes,
            'claims' => $context->claims,
            'issuer' => $issuer,
            'audience' => $audience,
            'key_id' => $key->kid,
            'expires_at' => $accessExpiresAt,
        ]);

        $refreshSecret = bin2hex(random_bytes(32));
        $hash = $this->hasher->hash($refreshSecret);
        $refreshToken = JwtRefreshToken::query()->create([
            'id' => $refreshId,
            'access_token_id' => $accessId,
            'user_type' => $user::class,
            'user_id' => (string) $user->getAuthIdentifier(),
            'session_id' => $sessionId,
            'secret_hash' => $hash['hash'],
            'hash_key_id' => $hash['hash_key_id'],
            'scopes' => $context->scopes,
            'claims' => $context->claims,
            'expires_at' => $refreshExpiresAt,
        ]);

        $payload = [
            'iss' => $issuer,
            'sub' => (string) $user->getAuthIdentifier(),
            'jti' => $accessId,
            'iat' => $now->getTimestamp(),
            'nbf' => $now->getTimestamp(),
            'exp' => $accessExpiresAt->getTimestamp(),
            'scopes' => $context->scopes,
            'sid' => $sessionId,
            ...$context->claims,
        ];

        if ($audience !== null && $audience !== '') {
            $payload['aud'] = $audience;
        }

        if ($subject !== null) {
            $payload['subject'] = $subject->toArray();
        }

        $jwt = JWT::encode($payload, (string) $key->privateKey, $key->algorithm, $key->kid);
        $pair = new TokenPair($jwt, $refreshId . '.' . $refreshSecret, $accessExpiresAt, $refreshExpiresAt, $accessToken, $refreshToken);

        Event::dispatch(new TokenIssued($user, $pair));

        foreach ($this->hooks->afterTokenIssueHooks() as $hook) {
            try {
                $callable = is_string($hook) ? app($hook) : $hook;
                $callable($user, $pair);
            } catch (Throwable $throwable) {
                report($throwable);
            }
        }

        return $pair;
    }

    public function validateAccessToken(string $jwt): JwtAccessToken
    {
        try {
            $parts = explode('.', $jwt);
            if (count($parts) !== 3) {
                throw new RuntimeException('Malformed JWT.');
            }

            $header = JWT::jsonDecode(JWT::urlsafeB64Decode($parts[0]));
            $kid = (string) ($header->kid ?? '');
            $key = $this->keys->forVerification($kid);
            JWT::$leeway = max(0, (int) config('sp-jwt-auth.clock_skew_seconds', 0));
            $decoded = JWT::decode($jwt, new Key($key->publicKey, $key->algorithm));
            $issuer = (string) config('sp-jwt-auth.issuer');
            $audience = config('sp-jwt-auth.audience');

            if (($decoded->iss ?? null) !== $issuer) {
                throw new RuntimeException('Invalid issuer.');
            }

            if ($audience !== null && $audience !== '' && ($decoded->aud ?? null) !== $audience) {
                throw new RuntimeException('Invalid audience.');
            }

            $token = JwtAccessToken::query()->find((string) ($decoded->jti ?? ''));
            $leewaySeconds = max(0, (int) config('sp-jwt-auth.clock_skew_seconds', 0));

            if (! $token instanceof JwtAccessToken || $token->isRevoked() || $token->expires_at->copy()->addSeconds($leewaySeconds)->isPast()) {
                throw new RuntimeException('Access token is inactive.');
            }

            return $token;
        } catch (Throwable) {
            throw new AuthenticationException('Unauthenticated.');
        }
    }

    public function rotateRefreshToken(string $refreshToken, ?TokenContext $override = null): TokenPair
    {
        [$id, $secret] = $this->parseRefreshToken($refreshToken);

        /** @var JwtRefreshToken|null $row */
        $row = JwtRefreshToken::query()->find($id);

        if (! $row instanceof JwtRefreshToken) {
            throw new AuthenticationException('Unauthenticated.');
        }

        if ($row->isRevoked()) {
            Event::dispatch(new RefreshTokenReuseDetected($row));
            $this->applyReuseDetection($row);
            throw new AuthenticationException('Unauthenticated.');
        }

        return DB::transaction(function () use ($id, $secret, $override): TokenPair {
            /** @var JwtRefreshToken|null $row */
            $row = JwtRefreshToken::query()->lockForUpdate()->find($id);

            if (! $row instanceof JwtRefreshToken) {
                throw new AuthenticationException('Unauthenticated.');
            }

            if ($row->isRevoked()) {
                throw new AuthenticationException('Unauthenticated.');
            }

            if ($row->expires_at->isPast() || ! $this->hasher->verify($secret, $row->secret_hash, $row->hash_key_id)) {
                throw new AuthenticationException('Unauthenticated.');
            }

            $oldAccess = JwtAccessToken::query()->find($row->access_token_id);
            $context = $override ?? new TokenContext(
                scopes: $row->scopes ?? [],
                claims: $row->claims ?? [],
                subjectType: $oldAccess?->subject_type,
                subjectId: $oldAccess?->subject_id,
                audience: $oldAccess?->audience,
                deviceId: $oldAccess?->device_id,
                deviceName: $oldAccess?->device_name,
                sessionId: $row->session_id,
            );

            $now = now();

            if ($oldAccess instanceof JwtAccessToken) {
                $oldAccess->forceFill(['revoked_at' => $now])->save();
            }

            $user = $this->resolveRefreshUser($row);
            $pair = $this->issueTokenPair($user, $context);

            $row->forceFill(['revoked_at' => $now, 'replaced_by_id' => $pair->refreshTokenRecord->id])->save();

            Event::dispatch(new TokenRefreshed($row, $pair));

            return $pair;
        });
    }

    public function revokeAccessToken(string $jti): void
    {
        JwtAccessToken::query()->whereKey($jti)->update(['revoked_at' => now()]);
        JwtRefreshToken::query()->where('access_token_id', $jti)->whereNull('revoked_at')->update(['revoked_at' => now()]);
        Event::dispatch(new TokenRevoked($jti));
    }

    /**
     * Revoke the session a refresh token belongs to, whether or not the token is
     * already revoked or expired (logout must be idempotent). The secret is still
     * verified. Returns false when the token is malformed, unknown or has a wrong secret.
     */
    public function revokeSessionByRefreshToken(string $refreshToken): bool
    {
        try {
            [$id, $secret] = $this->parseRefreshToken($refreshToken);
        } catch (AuthenticationException) {
            return false;
        }

        $row = JwtRefreshToken::query()->find($id);

        if (! $row instanceof JwtRefreshToken || ! $this->hasher->verify($secret, $row->secret_hash, $row->hash_key_id)) {
            return false;
        }

        $this->revokeSession($row->session_id);

        return true;
    }

    public function revokeSession(string $sessionId): void
    {
        JwtAccessToken::query()->where('session_id', $sessionId)->whereNull('revoked_at')->update(['revoked_at' => now()]);
        JwtRefreshToken::query()->where('session_id', $sessionId)->whereNull('revoked_at')->update(['revoked_at' => now()]);
        Event::dispatch(new SessionRevoked($sessionId));
    }

    public function revokeAllForUser(Authenticatable $user, ?string $exceptSessionId = null): void
    {
        $userType = $user::class;
        $userId = (string) $user->getAuthIdentifier();

        $accessQuery = JwtAccessToken::query()
            ->where('user_type', $userType)
            ->where('user_id', $userId)
            ->whereNull('revoked_at');

        $refreshQuery = JwtRefreshToken::query()
            ->where('user_type', $userType)
            ->where('user_id', $userId)
            ->whereNull('revoked_at');

        if ($exceptSessionId !== null) {
            $accessQuery->where('session_id', '!=', $exceptSessionId);
            $refreshQuery->where('session_id', '!=', $exceptSessionId);
        }

        $accessQuery->update(['revoked_at' => now()]);
        $refreshQuery->update(['revoked_at' => now()]);
        Event::dispatch(new AllUserTokensRevoked($user));
    }

    public function revokeDevice(Authenticatable $user, string $deviceId, ?string $exceptSessionId = null): void
    {
        $userType = $user::class;
        $userId = (string) $user->getAuthIdentifier();

        $sessionIds = JwtAccessToken::query()
            ->where('user_type', $userType)
            ->where('user_id', $userId)
            ->where('device_id', $deviceId)
            ->when($exceptSessionId !== null, fn($query) => $query->where('session_id', '!=', $exceptSessionId))
            ->pluck('session_id')
            ->all();

        if ($sessionIds === []) {
            return;
        }

        JwtAccessToken::query()
            ->where('user_type', $userType)
            ->where('user_id', $userId)
            ->whereIn('session_id', $sessionIds)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        JwtRefreshToken::query()
            ->where('user_type', $userType)
            ->where('user_id', $userId)
            ->whereIn('session_id', $sessionIds)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    /**
     * One entry per session whose refresh token is still usable, represented by
     * the session's newest access-token row (so idle sessions are listed too).
     *
     * @return Collection<int, JwtAccessToken>
     */
    public function activeSessionsForUser(Authenticatable $user): Collection
    {
        $refreshTokens = JwtRefreshToken::query()
            ->where('user_type', $user::class)
            ->where('user_id', (string) $user->getAuthIdentifier())
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->get()
            ->groupBy('session_id')
            ->map(static fn (Collection $group): JwtRefreshToken => $group->sortByDesc('created_at')->first());

        return JwtAccessToken::query()
            ->whereIn('id', $refreshTokens->pluck('access_token_id')->all())
            ->get()
            ->sortByDesc(static fn (JwtAccessToken $token): array => [
                $token->last_used_at?->getTimestamp() ?? 0,
                $token->created_at?->getTimestamp() ?? 0,
            ])
            ->values();
    }

    /** @return array{0: string, 1: string} */
    private function parseRefreshToken(string $refreshToken): array
    {
        $parts = explode('.', $refreshToken, 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '' || ! Str::isUuid($parts[0])) {
            throw new AuthenticationException('Unauthenticated.');
        }

        return [$parts[0], $parts[1]];
    }

    private function applyReuseDetection(JwtRefreshToken $row): void
    {
        match ((string) config('sp-jwt-auth.reuse_detection', 'revoke_session')) {
            'revoke_user' => $this->revokeRowsForUser($row->user_type, $row->user_id),
            'revoke_session' => $this->revokeSession($row->session_id),
            default => null,
        };
    }

    private function revokeRowsForUser(string $userType, string $userId): void
    {
        JwtAccessToken::query()->where('user_type', $userType)->where('user_id', $userId)->whereNull('revoked_at')->update(['revoked_at' => now()]);
        JwtRefreshToken::query()->where('user_type', $userType)->where('user_id', $userId)->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }

    private function resolveRefreshUser(JwtRefreshToken $row): Authenticatable
    {
        /** @var class-string<Authenticatable> $class */
        $class = $row->user_type;
        $user = $class::query()->whereKey($row->user_id)->first();

        if (! $user instanceof Authenticatable) {
            throw new AuthenticationException('Unauthenticated.');
        }

        return $user;
    }

    private function requestIp(TokenContext $context): ?string
    {
        $ip = $context->metadata['ip_address'] ?? (app()->bound('request') ? request()->ip() : null);

        return is_string($ip) && $ip !== '' ? mb_substr($ip, 0, 45) : null;
    }

    private function requestUserAgent(TokenContext $context): ?string
    {
        $agent = $context->metadata['user_agent'] ?? (app()->bound('request') ? request()->userAgent() : null);

        return is_string($agent) && $agent !== '' ? mb_substr($agent, 0, 1000) : null;
    }
}
