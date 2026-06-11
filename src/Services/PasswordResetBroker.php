<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Services;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Sopheak\JwtAuth\Contracts\PasswordResetSender;
use Sopheak\JwtAuth\DTO\PasswordResetDispatch;
use Sopheak\JwtAuth\DTO\PasswordResetResult;
use Sopheak\JwtAuth\Events\PasswordResetSent;
use Sopheak\JwtAuth\Events\PasswordResetTokenConsumed;
use Sopheak\JwtAuth\Events\PasswordResetTokenCreated;
use Sopheak\JwtAuth\Models\PasswordResetToken;
use Sopheak\JwtAuth\Security\SecretHasher;

final readonly class PasswordResetBroker
{
    public function __construct(private SecretHasher $hasher)
    {
    }

    public function createResetToken(Authenticatable $user, string $email, array $metadata = []): PasswordResetDispatch
    {
        $id = (string) Str::uuid();
        $secret = bin2hex(random_bytes(32));
        $hash = $this->hasher->hash($secret);
        $emailHash = $this->hasher->hash(strtolower(trim($email)));

        $record = PasswordResetToken::query()->create([
            'id' => $id,
            'user_type' => $user::class,
            'user_id' => (string) $user->getAuthIdentifier(),
            'email_hash' => $emailHash['hash'],
            'email_masked' => $this->maskEmail($email),
            'token_hash' => $hash['hash'],
            'hash_key_id' => $hash['hash_key_id'],
            'max_attempts' => (int) config('sp-jwt-auth.password_reset.max_attempts', 5),
            'metadata' => $metadata,
            'sent_at' => now(),
            'expires_at' => now()->addMinutes((int) config('sp-jwt-auth.password_reset.ttl_minutes', 60)),
        ]);

        $dispatch = new PasswordResetDispatch($id, $id . '.' . $secret, $secret, $user, strtolower(trim($email)), $record);

        if (app()->bound(PasswordResetSender::class)) {
            app(PasswordResetSender::class)->send($dispatch);
            Event::dispatch(new PasswordResetSent($dispatch));
        }

        Event::dispatch(new PasswordResetTokenCreated($dispatch));

        return $dispatch;
    }

    public function verifyResetToken(string $token): PasswordResetResult
    {
        return $this->check($token, consume: false);
    }

    public function consumeResetToken(string $token): PasswordResetResult
    {
        return $this->check($token, consume: true);
    }

    public function revokeResetTokens(Authenticatable $user): void
    {
        PasswordResetToken::query()
            ->where('user_type', $user::class)
            ->where('user_id', (string) $user->getAuthIdentifier())
            ->whereNull('used_at')
            ->update(['used_at' => now()]);
    }

    private function check(string $token, bool $consume): PasswordResetResult
    {
        [$id, $secret] = $this->parseToken($token);
        $record = PasswordResetToken::query()->find($id);

        if (! $record instanceof PasswordResetToken || $record->used_at !== null || $record->expires_at->isPast() || $record->attempts >= $record->max_attempts) {
            throw new AuthenticationException('Password reset token is invalid.');
        }

        if (! $this->hasher->verify($secret, $record->token_hash, $record->hash_key_id)) {
            $record->increment('attempts');
            throw new AuthenticationException('Password reset token is invalid.');
        }

        if ($consume) {
            $record->forceFill(['used_at' => now()])->save();
        }

        $user = $this->resolveUser($record->user_type, $record->user_id);
        $result = new PasswordResetResult($user, (string) ($user->email ?? $record->email_masked));

        if ($consume) {
            Event::dispatch(new PasswordResetTokenConsumed($result));
        }

        return $result;
    }

    private function parseToken(string $token): array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new AuthenticationException('Password reset token is invalid.');
        }

        return $parts;
    }

    private function resolveUser(string $userType, string $userId): Authenticatable
    {
        $user = $userType::query()->whereKey($userId)->first();
        if (! $user instanceof Authenticatable) {
            throw new AuthenticationException('User is invalid.');
        }

        return $user;
    }

    private function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', strtolower(trim($email)), 2), 2, '');

        return ($name[0] ?? '*') . '***@' . $domain;
    }
}
