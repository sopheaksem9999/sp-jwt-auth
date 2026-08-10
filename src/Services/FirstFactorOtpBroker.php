<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Services;

use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Sopheak\JwtAuth\Contracts\FirstFactorUserResolver;
use Sopheak\JwtAuth\Contracts\OtpChannelSender;
use Sopheak\JwtAuth\DTO\FirstFactorVerification;
use Sopheak\JwtAuth\DTO\OtpDestination;
use Sopheak\JwtAuth\DTO\OtpDispatch;
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Events\FirstFactorOtpExpired;
use Sopheak\JwtAuth\Events\FirstFactorOtpFailed;
use Sopheak\JwtAuth\Events\FirstFactorOtpLocked;
use Sopheak\JwtAuth\Events\FirstFactorOtpVerified;
use Sopheak\JwtAuth\Events\OtpCodeCreated;
use Sopheak\JwtAuth\Events\OtpCodeResent;
use Sopheak\JwtAuth\Events\OtpCodeSent;
use Sopheak\JwtAuth\Models\FirstFactorOtpCode;
use Sopheak\JwtAuth\Security\SecretHasher;

final readonly class FirstFactorOtpBroker
{
    public function __construct(
        private SecretHasher $hasher,
        private FirstFactorUserResolver $resolver,
        private JwtTokenService $jwt,
    ) {
    }

    public function request(OtpDestination $destination, string $purpose, ?string $requestedType = null): OtpDispatch
    {
        $this->assertPurposeAllowed($purpose);
        $this->assertRequestedTypeAllowed($requestedType);

        $otp = $this->latestActive($destination, $purpose);

        if ($otp instanceof FirstFactorOtpCode && ! $otp->last_sent_at->addSeconds($this->cooldownSeconds())->isPast()) {
            $retryAfter = (int) $otp->last_sent_at->addSeconds($this->cooldownSeconds())->diffInSeconds(now(), false);

            throw new TooManyRequestsHttpException(max(1, $retryAfter), 'Too many requests.');
        }

        $this->invalidatePrior($destination, $purpose);

        $plaintext = $this->generateCode((int) config('sp-jwt-auth.first_factor_otp.digits', 6));
        $hash = $this->hasher->hash($plaintext);
        $destinationHash = $this->hasher->hash($destination->normalizedDestination);

        $otp = FirstFactorOtpCode::query()->create([
            'id' => (string) Str::uuid(),
            'channel' => $destination->channel,
            'destination_hash' => $destinationHash['hash'],
            'destination_masked' => $destination->maskedDestination,
            'purpose' => $purpose,
            'requested_type' => $requestedType,
            'code_hash' => $hash['hash'],
            'hash_key_id' => $hash['hash_key_id'],
            'max_attempts' => (int) config('sp-jwt-auth.first_factor_otp.max_attempts', 5),
            'last_sent_at' => now(),
            'expires_at' => now()->addMinutes((int) config('sp-jwt-auth.first_factor_otp.ttl_minutes', 5)),
        ]);

        $dispatch = new OtpDispatch($otp->id, $otp->id, $plaintext, $destination, $otp);

        if (app()->bound(OtpChannelSender::class)) {
            app(OtpChannelSender::class)->send($dispatch);
            Event::dispatch(new OtpCodeSent($dispatch));
        }

        Event::dispatch(new OtpCodeCreated($dispatch));

        return $dispatch;
    }

    public function resend(string $otpId, OtpDestination $destination): OtpDispatch
    {
        $otp = FirstFactorOtpCode::query()->findOrFail($otpId);

        $destinationHash = $this->hasher->hash($destination->normalizedDestination);

        if (! hash_equals($otp->destination_hash, $destinationHash['hash'])) {
            throw new InvalidArgumentException('Destination does not match the challenge.');
        }

        if (! $otp->last_sent_at->addSeconds($this->cooldownSeconds())->isPast()) {
            $retryAfter = (int) $otp->last_sent_at->addSeconds($this->cooldownSeconds())->diffInSeconds(now(), false);

            throw new TooManyRequestsHttpException(max(1, $retryAfter), 'Too many requests.');
        }

        $dispatch = $this->request($destination, $otp->purpose, $otp->requested_type);

        Event::dispatch(new OtpCodeResent($dispatch));

        return $dispatch;
    }

    public function verify(string $otpId, string $code, ?OtpDestination $destination = null): FirstFactorVerification
    {
        $otp = $this->consume($otpId, $code);

        if ($destination instanceof OtpDestination) {
            $destinationHash = $this->hasher->hash($destination->normalizedDestination);

            if (! hash_equals($otp->destination_hash, $destinationHash['hash'])) {
                throw new InvalidArgumentException('Destination does not match the challenge.');
            }
        } else {
            $destination = new OtpDestination($otp->channel, '', $otp->destination_masked);
        }

        $user = $this->resolver->resolve($destination, $otp->purpose)
            ?? $this->resolver->create($destination, $otp->purpose, $otp->requested_type);

        if (! $user instanceof Authenticatable) {
            throw new AuthenticationException('OTP verification failed.');
        }

        $this->markDestinationVerified($user, $otp->channel);

        $context = TokenContext::make()->scopes((array) config('sp-jwt-auth.first_factor_otp.default_scopes', ['*']));

        return new FirstFactorVerification($user, $this->jwt->issueTokenPair($user, $context));
    }

    private function consume(string $otpId, string $code): FirstFactorOtpCode
    {
        $result = DB::transaction(function () use ($otpId, $code): array {
            $otp = FirstFactorOtpCode::query()->whereKey($otpId)->lockForUpdate()->first();

            if (! $otp instanceof FirstFactorOtpCode || $otp->verified_at !== null || $otp->expires_at->isPast() || $otp->attempts >= $otp->max_attempts) {
                if ($otp instanceof FirstFactorOtpCode && $otp->expires_at->isPast()) {
                    Event::dispatch(new FirstFactorOtpExpired($otp->id));
                }

                if ($otp instanceof FirstFactorOtpCode && $otp->attempts >= $otp->max_attempts) {
                    Event::dispatch(new FirstFactorOtpLocked($otp));
                }

                throw new AuthenticationException('OTP is invalid.');
            }

            if (! $this->hasher->verify($code, $otp->code_hash, $otp->hash_key_id)) {
                $otp->increment('attempts');
                $otp->refresh();

                Event::dispatch(new FirstFactorOtpFailed($otp));

                if ($otp->attempts >= $otp->max_attempts) {
                    Event::dispatch(new FirstFactorOtpLocked($otp));
                }

                return [$otp, false];
            }

            $otp->forceFill(['verified_at' => now()])->save();

            Event::dispatch(new FirstFactorOtpVerified($otp));

            return [$otp, true];
        });

        [$otp, $matched] = $result;

        if (! $matched) {
            throw new AuthenticationException('OTP is invalid.');
        }

        return $otp;
    }

    private function markDestinationVerified(Authenticatable $user, string $channel): void
    {
        if (! $user instanceof Model) {
            return;
        }

        $column = $channel === 'email' ? 'email_verified_at' : 'phone_verified_at';

        if (! Schema::hasColumn($user->getTable(), $column)) {
            return;
        }

        $user->forceFill([$column => now()])->save();
    }

    private function assertPurposeAllowed(string $purpose): void
    {
        $allowed = config('sp-jwt-auth.first_factor_otp.purposes', []);

        if ($allowed !== [] && ! in_array($purpose, $allowed, true)) {
            throw new InvalidArgumentException(sprintf('Purpose [%s] is not allowed.', $purpose));
        }
    }

    private function assertRequestedTypeAllowed(?string $requestedType): void
    {
        $allowed = config('sp-jwt-auth.first_factor_otp.requested_types', []);

        if ($requestedType !== null && $allowed !== [] && ! in_array($requestedType, $allowed, true)) {
            throw new InvalidArgumentException(sprintf('Requested type [%s] is not allowed.', $requestedType));
        }
    }

    private function latestActive(OtpDestination $destination, string $purpose): ?FirstFactorOtpCode
    {
        $destinationHash = $this->hasher->hash($destination->normalizedDestination);

        return FirstFactorOtpCode::query()
            ->where('destination_hash', $destinationHash['hash'])
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();
    }

    private function invalidatePrior(OtpDestination $destination, string $purpose): void
    {
        $destinationHash = $this->hasher->hash($destination->normalizedDestination);

        $ids = FirstFactorOtpCode::query()
            ->where('destination_hash', $destinationHash['hash'])
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->pluck('id');

        FirstFactorOtpCode::query()
            ->where('destination_hash', $destinationHash['hash'])
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);

        foreach ($ids as $id) {
            Event::dispatch(new FirstFactorOtpExpired((string) $id));
        }
    }

    private function generateCode(int $digits): string
    {
        $testCode = config('sp-jwt-auth.first_factor_otp.test_mode')
            ? config('sp-jwt-auth.first_factor_otp.test_code')
            : null;

        if (is_string($testCode) && $testCode !== '') {
            return $testCode;
        }

        $min = 10 ** max(1, $digits - 1);
        $max = (10 ** $digits) - 1;

        return (string) random_int($min, $max);
    }

    private function cooldownSeconds(): int
    {
        return (int) config('sp-jwt-auth.first_factor_otp.resend_cooldown_seconds', 60);
    }
}
