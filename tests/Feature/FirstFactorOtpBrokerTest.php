<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Closure;
use InvalidArgumentException;
use Illuminate\Contracts\Auth\Authenticatable;
use Sopheak\JwtAuth\Contracts\FirstFactorUserResolver;
use Sopheak\JwtAuth\DTO\OtpDestination;
use Sopheak\JwtAuth\Models\FirstFactorOtpCode;
use Sopheak\JwtAuth\Services\FirstFactorOtpBroker;
use Sopheak\JwtAuth\Tests\Fixtures\User;
use Sopheak\JwtAuth\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

final class FirstFactorOtpBrokerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->bindResolver();
    }

    private function broker(): FirstFactorOtpBroker
    {
        return $this->app->make(FirstFactorOtpBroker::class);
    }

    private function bindResolver(?User $existing = null, ?Closure $creator = null): void
    {
        $this->app->instance(FirstFactorUserResolver::class, new readonly class ($existing, $creator) implements FirstFactorUserResolver {
            public function __construct(private ?User $existing, private ?Closure $creator)
            {
            }

            public function resolve(OtpDestination $destination, string $purpose): ?Authenticatable
            {
                return $this->existing;
            }

            public function create(OtpDestination $destination, string $purpose, ?string $requestedType): ?Authenticatable
            {
                return $this->creator instanceof Closure ? ($this->creator)($destination, $purpose, $requestedType) : null;
            }
        });
    }

    public function test_request_creates_hashed_code_without_plaintext_in_database(): void
    {
        $dispatch = $this->broker()->request(OtpDestination::email('User@Example.com'), 'login');

        $otp = FirstFactorOtpCode::query()->findOrFail($dispatch->otpId);

        $this->assertSame('u***@example.com', $otp->destination_masked);
        $this->assertNotSame($dispatch->plaintextCode, $otp->code_hash);
        $this->assertNotNull($otp->hash_key_id);
        $this->assertNull($otp->verified_at);
    }

    public function test_request_invalidates_prior_active_challenge_for_same_destination_and_purpose(): void
    {
        $first = $this->broker()->request(OtpDestination::phone('+85512345678'), 'login');

        FirstFactorOtpCode::query()->whereKey($first->otpId)->update(['last_sent_at' => now()->subMinutes(2)]);

        $this->broker()->request(OtpDestination::phone('+85512345678'), 'login');

        $this->assertTrue(FirstFactorOtpCode::query()->findOrFail($first->otpId)->expires_at->isPast());
    }

    public function test_request_does_not_invalidate_other_purpose_or_destination(): void
    {
        $first = $this->broker()->request(OtpDestination::phone('+85512345678'), 'login');
        $this->broker()->request(OtpDestination::phone('+85587654321'), 'login');
        $this->broker()->request(OtpDestination::phone('+85512345678'), 'register');

        $this->assertFalse(FirstFactorOtpCode::query()->findOrFail($first->otpId)->expires_at->isPast());
    }

    public function test_request_respects_resend_cooldown(): void
    {
        $this->broker()->request(OtpDestination::phone('+85512345678'), 'login');

        try {
            $this->broker()->request(OtpDestination::phone('+85512345678'), 'login');
            $this->fail('Expected TooManyRequestsException');
        } catch (TooManyRequestsHttpException $tooManyRequestsHttpException) {
            $this->assertGreaterThan(0, (int) $tooManyRequestsHttpException->getHeaders()['Retry-After']);
        }
    }

    public function test_request_uses_test_code_when_test_mode_enabled(): void
    {
        config()->set('sp-jwt-auth.first_factor_otp.test_mode', true);
        config()->set('sp-jwt-auth.first_factor_otp.test_code', '424242');

        $dispatch = $this->broker()->request(OtpDestination::email('a@b.com'), 'login');

        $this->assertSame('424242', $dispatch->plaintextCode);
    }

    public function test_request_with_unknown_purpose_is_rejected(): void
    {
        config()->set('sp-jwt-auth.first_factor_otp.purposes', ['login']);

        $this->expectException(InvalidArgumentException::class);

        $this->broker()->request(OtpDestination::email('a@b.com'), 'register');
    }

    public function test_resend_creates_new_code_and_expires_previous(): void
    {
        $first = $this->broker()->request(OtpDestination::phone('+85512345678'), 'login');

        // Deviation (plan owner-approved): the broker enforces a 60s cooldown,
        // so rewind last_sent_at before resending:
        FirstFactorOtpCode::query()->whereKey($first->otpId)->update(['last_sent_at' => now()->subMinutes(2)]);

        $resent = $this->broker()->resend($first->otpId, OtpDestination::phone('+85512345678'));

        $this->assertNotSame($first->otpId, $resent->otpId);
        $this->assertTrue(FirstFactorOtpCode::query()->findOrFail($first->otpId)->expires_at->isPast());
    }

    public function test_resend_respects_cooldown(): void
    {
        $first = $this->broker()->request(OtpDestination::phone('+85512345678'), 'login');

        try {
            $this->broker()->resend($first->otpId, OtpDestination::phone('+85512345678'));
            $this->fail('Expected TooManyRequestsHttpException');
        } catch (TooManyRequestsHttpException $tooManyRequestsHttpException) {
            $this->assertGreaterThan(0, (int) $tooManyRequestsHttpException->getHeaders()['Retry-After']);
        }
    }

    public function test_resend_rejects_mismatched_destination(): void
    {
        $first = $this->broker()->request(OtpDestination::phone('+85512345678'), 'login');

        $this->expectException(InvalidArgumentException::class);

        $this->broker()->resend($first->otpId, OtpDestination::phone('+85599999999'));
    }
}
