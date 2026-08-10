<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Services;

use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Sopheak\JwtAuth\Contracts\FirstFactorUserResolver;
use Sopheak\JwtAuth\Contracts\OtpChannelSender;
use Sopheak\JwtAuth\DTO\OtpDestination;
use Sopheak\JwtAuth\DTO\OtpDispatch;
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

        FirstFactorOtpCode::query()
            ->where('destination_hash', $destinationHash['hash'])
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);
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
