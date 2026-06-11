<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Services;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Sopheak\JwtAuth\Contracts\EmailVerificationSender;
use Sopheak\JwtAuth\DTO\EmailVerificationDispatch;
use Sopheak\JwtAuth\DTO\EmailVerificationResult;
use Sopheak\JwtAuth\Events\EmailVerificationSent;
use Sopheak\JwtAuth\Events\EmailVerificationTokenCreated;
use Sopheak\JwtAuth\Events\EmailVerified;
use Sopheak\JwtAuth\Models\EmailVerificationToken;
use Sopheak\JwtAuth\Security\SecretHasher;

final readonly class EmailVerificationBroker
{
    public function __construct(private SecretHasher $hasher)
    {
    }

    public function createVerificationToken(Authenticatable $user, string $email, array $metadata = []): EmailVerificationDispatch
    {
        $id = (string) Str::uuid();
        $secret = bin2hex(random_bytes(32));
        $hash = $this->hasher->hash($secret);
        $emailHash = $this->hasher->hash(strtolower(trim($email)));

        $record = EmailVerificationToken::query()->create([
            'id' => $id,
            'user_type' => $user::class,
            'user_id' => (string) $user->getAuthIdentifier(),
            'email_hash' => $emailHash['hash'],
            'email_masked' => $this->maskEmail($email),
            'token_hash' => $hash['hash'],
            'hash_key_id' => $hash['hash_key_id'],
            'metadata' => $metadata,
            'sent_at' => now(),
            'expires_at' => now()->addMinutes((int) config('sp-jwt-auth.email_verification.ttl_minutes', 60)),
        ]);

        $dispatch = new EmailVerificationDispatch($id, $id . '.' . $secret, $secret, $user, strtolower(trim($email)), $record);

        if (app()->bound(EmailVerificationSender::class)) {
            app(EmailVerificationSender::class)->send($dispatch);
            Event::dispatch(new EmailVerificationSent($dispatch));
        }

        Event::dispatch(new EmailVerificationTokenCreated($dispatch));

        return $dispatch;
    }

    public function resendVerificationToken(string $tokenId): EmailVerificationDispatch
    {
        $record = EmailVerificationToken::query()->findOrFail($tokenId);
        $user = $this->resolveUser($record->user_type, $record->user_id);

        return $this->createVerificationToken($user, $record->email_masked, $record->metadata ?? []);
    }

    public function verifyEmailToken(string $token): EmailVerificationResult
    {
        [$id, $secret] = $this->parseToken($token);
        $record = EmailVerificationToken::query()->find($id);

        if (! $record instanceof EmailVerificationToken || $record->verified_at !== null || $record->expires_at->isPast()) {
            throw new AuthenticationException('Verification token is invalid.');
        }

        if (! $this->hasher->verify($secret, $record->token_hash, $record->hash_key_id)) {
            throw new AuthenticationException('Verification token is invalid.');
        }

        $record->forceFill(['verified_at' => now()])->save();

        $result = new EmailVerificationResult($this->resolveUser($record->user_type, $record->user_id), $this->unmaskableEmail($record));
        Event::dispatch(new EmailVerified($result));

        return $result;
    }

    public function revokeVerificationTokens(Authenticatable $user): void
    {
        EmailVerificationToken::query()
            ->where('user_type', $user::class)
            ->where('user_id', (string) $user->getAuthIdentifier())
            ->whereNull('verified_at')
            ->update(['verified_at' => now()]);
    }

    private function parseToken(string $token): array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new AuthenticationException('Verification token is invalid.');
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

    private function unmaskableEmail(EmailVerificationToken $record): string
    {
        $user = $this->resolveUser($record->user_type, $record->user_id);

        return (string) ($user->email ?? $record->email_masked);
    }
}
