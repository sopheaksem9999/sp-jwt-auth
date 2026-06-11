<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Services;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Sopheak\JwtAuth\Contracts\OtpChannelSender;
use Sopheak\JwtAuth\DTO\OtpDestination;
use Sopheak\JwtAuth\DTO\OtpDispatch;
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Events\OtpCodeCreated;
use Sopheak\JwtAuth\Events\OtpCodeExpired;
use Sopheak\JwtAuth\Events\OtpCodeFailed;
use Sopheak\JwtAuth\Events\OtpCodeLocked;
use Sopheak\JwtAuth\Events\OtpCodeResent;
use Sopheak\JwtAuth\Events\OtpCodeSent;
use Sopheak\JwtAuth\Events\OtpCodeVerified;
use Sopheak\JwtAuth\Models\MfaChallenge;
use Sopheak\JwtAuth\Models\MfaOtpCode;
use Sopheak\JwtAuth\Security\SecretHasher;

final readonly class OtpChallengeBroker
{
    public function __construct(
        private SecretHasher $hasher,
        private MfaChallengeBroker $challenges,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function createOtp(MfaChallenge $challenge, OtpDestination $destination, array $options = []): OtpDispatch
    {
        $plaintext = $this->generateCode((int) ($options['digits'] ?? config('sp-jwt-auth.mfa.otp.digits', 6)));
        $hash = $this->hasher->hash($plaintext);
        $destinationHash = $this->hasher->hash($destination->normalizedDestination);

        $otp = MfaOtpCode::query()->create([
            'id' => (string) Str::uuid(),
            'challenge_id' => $challenge->id,
            'channel' => $destination->channel,
            'destination_hash' => $destinationHash['hash'],
            'destination_masked' => $destination->maskedDestination,
            'code_hash' => $hash['hash'],
            'hash_key_id' => $hash['hash_key_id'],
            'max_attempts' => (int) config('sp-jwt-auth.mfa.otp.max_attempts', 5),
            'last_sent_at' => now(),
            'expires_at' => now()->addMinutes((int) config('sp-jwt-auth.mfa.otp.ttl_minutes', 5)),
        ]);

        $dispatch = new OtpDispatch($otp->id, $challenge->id, $plaintext, $destination, $otp);

        if (app()->bound(OtpChannelSender::class)) {
            app(OtpChannelSender::class)->send($dispatch);
            Event::dispatch(new OtpCodeSent($dispatch));
        }

        Event::dispatch(new OtpCodeCreated($dispatch));
        Event::dispatch('sp-jwt-auth.otp.created', [$dispatch]);

        return $dispatch;
    }

    public function resendOtp(string $otpId): OtpDispatch
    {
        $otp = MfaOtpCode::query()->findOrFail($otpId);
        $challenge = $this->challenges->resolve($otp->challenge_id);

        $dispatch = $this->createOtp($challenge, new OtpDestination($otp->channel, '', $otp->destination_masked));
        Event::dispatch(new OtpCodeResent($dispatch));

        return $dispatch;
    }

    public function verifyOtp(string $challengeId, string $code): TokenContext
    {
        $this->challenges->resolve($challengeId);
        $otp = MfaOtpCode::query()
            ->where('challenge_id', $challengeId)
            ->whereNull('verified_at')
            ->latest()
            ->first();

        if (! $otp instanceof MfaOtpCode || $otp->expires_at->isPast() || $otp->attempts >= $otp->max_attempts) {
            if ($otp instanceof MfaOtpCode && $otp->attempts >= $otp->max_attempts) {
                Event::dispatch(new OtpCodeLocked($otp));
            }

            throw new AuthenticationException('OTP is invalid.');
        }

        if (! $this->hasher->verify($code, $otp->code_hash, $otp->hash_key_id)) {
            $otp->increment('attempts');
            $otp->refresh();
            Event::dispatch(new OtpCodeFailed($otp));
            if ($otp->attempts >= $otp->max_attempts) {
                Event::dispatch(new OtpCodeLocked($otp));
            }

            throw new AuthenticationException('OTP is invalid.');
        }

        $otp->forceFill(['verified_at' => now()])->save();
        Event::dispatch(new OtpCodeVerified($otp));

        return $this->challenges->complete($challengeId);
    }

    public function revokeOtp(string $otpId): void
    {
        MfaOtpCode::query()->whereKey($otpId)->update(['expires_at' => now()]);
        Event::dispatch(new OtpCodeExpired($otpId));
    }

    private function generateCode(int $digits): string
    {
        $min = 10 ** max(1, $digits - 1);
        $max = (10 ** $digits) - 1;

        return (string) random_int($min, $max);
    }
}
