<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Closure;
use InvalidArgumentException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Event;
use Sopheak\JwtAuth\Contracts\FirstFactorUserResolver;
use Sopheak\JwtAuth\Contracts\OtpChannelSender;
use Sopheak\JwtAuth\DTO\OtpDestination;
use Sopheak\JwtAuth\DTO\OtpDispatch;
use Sopheak\JwtAuth\Events\OtpCodeCreated;
use Sopheak\JwtAuth\Events\OtpCodeSent;
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

    public function test_verify_signs_in_existing_user_and_issues_token_pair(): void
    {
        $user = $this->createUser();
        $this->bindResolver(existing: $user);

        $dispatch = $this->broker()->request(OtpDestination::email('a@b.com'), 'login');

        $verification = $this->broker()->verify($dispatch->otpId, $dispatch->plaintextCode);

        $this->assertSame($user->getAuthIdentifier(), $verification->user->getAuthIdentifier());
        $this->assertNotEmpty($verification->pair->accessToken);
        $this->assertNotEmpty($verification->pair->refreshToken);
        $this->assertNotNull(FirstFactorOtpCode::query()->findOrFail($dispatch->otpId)->verified_at);
    }

    public function test_verify_creates_user_when_none_exists_and_marks_email_verified(): void
    {
        $this->bindResolver(creator: function (OtpDestination $destination): User {
            $user = new User();
            $user->forceFill([
                'name' => 'New User',
                'email' => $destination->normalizedDestination,
                'password' => bcrypt('unused'),
            ])->save();

            return $user;
        });

        $dispatch = $this->broker()->request(OtpDestination::email('new@example.com'), 'login', 'driver');

        // Deviation D: pass the plaintext destination so the resolver can create the user:
        $verification = $this->broker()->verify($dispatch->otpId, $dispatch->plaintextCode, OtpDestination::email('new@example.com'));

        $this->assertSame('new@example.com', $verification->user->getAttribute('email'));
        $this->assertNotNull($verification->user->getAttribute('email_verified_at'));
        $this->assertNotEmpty($verification->pair->accessToken);
    }

    public function test_verify_wrong_code_increments_attempts_and_fails(): void
    {
        $this->bindResolver(existing: $this->createUser());

        $dispatch = $this->broker()->request(OtpDestination::email('a@b.com'), 'login');

        try {
            $this->broker()->verify($dispatch->otpId, '000000');
            $this->fail('Expected AuthenticationException');
        } catch (AuthenticationException) {
        }

        $this->assertSame(1, FirstFactorOtpCode::query()->findOrFail($dispatch->otpId)->attempts);
        $this->assertNull(FirstFactorOtpCode::query()->findOrFail($dispatch->otpId)->verified_at);
    }

    public function test_verify_locks_after_max_attempts(): void
    {
        $this->bindResolver(existing: $this->createUser());

        $dispatch = $this->broker()->request(OtpDestination::email('a@b.com'), 'login');

        for ($i = 0; $i < 5; $i++) {
            try {
                $this->broker()->verify($dispatch->otpId, '000000');
            } catch (AuthenticationException) {
            }
        }

        $this->expectException(AuthenticationException::class);

        $this->broker()->verify($dispatch->otpId, $dispatch->plaintextCode);
    }

    public function test_verify_expired_code_fails(): void
    {
        $this->bindResolver(existing: $this->createUser());

        $dispatch = $this->broker()->request(OtpDestination::email('a@b.com'), 'login');

        FirstFactorOtpCode::query()->whereKey($dispatch->otpId)->update(['expires_at' => now()->subMinute()]);

        $this->expectException(AuthenticationException::class);

        $this->broker()->verify($dispatch->otpId, $dispatch->plaintextCode);
    }

    public function test_verify_second_verification_against_same_challenge_fails(): void
    {
        $this->bindResolver(existing: $this->createUser());

        $dispatch = $this->broker()->request(OtpDestination::email('a@b.com'), 'login');

        $this->broker()->verify($dispatch->otpId, $dispatch->plaintextCode);

        $this->expectException(AuthenticationException::class);

        $this->broker()->verify($dispatch->otpId, $dispatch->plaintextCode);
    }

    public function test_verify_rejects_when_resolver_returns_no_user(): void
    {
        $this->bindResolver();

        $dispatch = $this->broker()->request(OtpDestination::email('a@b.com'), 'login');

        $this->expectException(AuthenticationException::class);

        $this->broker()->verify($dispatch->otpId, $dispatch->plaintextCode);
    }

    public function test_verify_rejects_mismatched_destination_without_consuming(): void
    {
        $this->bindResolver(existing: $this->createUser());

        $dispatch = $this->broker()->request(OtpDestination::email('a@b.com'), 'login');

        try {
            $this->broker()->verify($dispatch->otpId, $dispatch->plaintextCode, OtpDestination::email('other@example.com'));
            $this->fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException) {
        }

        $this->assertNull(FirstFactorOtpCode::query()->findOrFail($dispatch->otpId)->verified_at);

        $verification = $this->broker()->verify($dispatch->otpId, $dispatch->plaintextCode, OtpDestination::email('a@b.com'));

        $this->assertNotEmpty($verification->pair->accessToken);
    }

    public function test_resend_by_destination_inherits_requested_type(): void
    {
        $dispatch = $this->broker()->request(OtpDestination::email('a@b.com'), 'login', 'driver');

        FirstFactorOtpCode::query()->whereKey($dispatch->otpId)->update(['last_sent_at' => now()->subMinutes(2)]);

        $resent = $this->broker()->resendByDestination(OtpDestination::email('a@b.com'), 'login');

        $this->assertNotSame($dispatch->otpId, $resent->otpId);
        $this->assertSame('driver', FirstFactorOtpCode::query()->findOrFail($resent->otpId)->requested_type);
    }

    public function test_resend_by_destination_shares_cooldown(): void
    {
        $this->broker()->request(OtpDestination::email('a@b.com'), 'login');

        try {
            $this->broker()->resendByDestination(OtpDestination::email('a@b.com'), 'login');
            $this->fail('Expected TooManyRequestsHttpException');
        } catch (TooManyRequestsHttpException $tooManyRequestsHttpException) {
            $this->assertGreaterThan(0, (int) $tooManyRequestsHttpException->getHeaders()['Retry-After']);
        }
    }

    public function test_resend_by_destination_without_active_challenge_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->broker()->resendByDestination(OtpDestination::email('nobody@example.com'), 'login');
    }

    public function test_resend_by_destination_ignores_other_purpose(): void
    {
        $this->broker()->request(OtpDestination::email('a@b.com'), 'login');

        $this->expectException(InvalidArgumentException::class);

        $this->broker()->resendByDestination(OtpDestination::email('a@b.com'), 'register');
    }

    public function test_request_uses_per_destination_test_code(): void
    {
        config()->set('sp-jwt-auth.first_factor_otp.test_codes', 'dev@mail.com:009988');

        $dispatch = $this->broker()->request(OtpDestination::email('dev@mail.com'), 'login');

        $this->assertSame('009988', $dispatch->plaintextCode);
    }

    public function test_request_ignores_test_code_for_other_destination(): void
    {
        config()->set('sp-jwt-auth.first_factor_otp.test_codes', 'dev@mail.com:009988');

        $dispatch = $this->broker()->request(OtpDestination::email('other@mail.com'), 'login');

        $this->assertNotSame('009988', $dispatch->plaintextCode);
        $this->assertSame(6, strlen($dispatch->plaintextCode));
    }

    public function test_test_codes_support_phone_and_multiple_entries(): void
    {
        config()->set('sp-jwt-auth.first_factor_otp.test_codes', '+85511002233:123456,dev@mail.com:009988');

        $dispatch = $this->broker()->request(OtpDestination::phone('+85511002233'), 'login');

        $this->assertSame('123456', $dispatch->plaintextCode);
    }

    public function test_request_skips_malformed_test_code_pairs(): void
    {
        config()->set('sp-jwt-auth.first_factor_otp.test_codes', 'dev@mail.com');

        $dispatch = $this->broker()->request(OtpDestination::email('dev@mail.com'), 'login');

        $this->assertSame(6, strlen($dispatch->plaintextCode));
    }

    public function test_request_with_test_code_skips_sender(): void
    {
        config()->set('sp-jwt-auth.first_factor_otp.test_codes', 'dev@mail.com:009988');

        Event::fake([OtpCodeCreated::class, OtpCodeSent::class]);

        $sender = new class implements OtpChannelSender {
            public int $sent = 0;

            public function send(OtpDispatch $dispatch): void
            {
                $this->sent++;
            }
        };

        $this->app->instance(OtpChannelSender::class, $sender);

        $this->broker()->request(OtpDestination::email('dev@mail.com'), 'login');

        $this->assertSame(0, $sender->sent);
        Event::assertDispatched(OtpCodeCreated::class);
        Event::assertNotDispatched(OtpCodeSent::class);
    }

    public function test_request_without_test_code_calls_sender(): void
    {
        $sender = new class implements OtpChannelSender {
            public int $sent = 0;

            public function send(OtpDispatch $dispatch): void
            {
                $this->sent++;
            }
        };

        $this->app->instance(OtpChannelSender::class, $sender);

        $this->broker()->request(OtpDestination::email('a@b.com'), 'login');

        $this->assertSame(1, $sender->sent);
    }

    public function test_global_test_mode_skips_sender(): void
    {
        config()->set('sp-jwt-auth.first_factor_otp.test_mode', true);
        config()->set('sp-jwt-auth.first_factor_otp.test_code', '424242');

        $sender = new class implements OtpChannelSender {
            public int $sent = 0;

            public function send(OtpDispatch $dispatch): void
            {
                $this->sent++;
            }
        };

        $this->app->instance(OtpChannelSender::class, $sender);

        $this->broker()->request(OtpDestination::email('a@b.com'), 'login');

        $this->assertSame(0, $sender->sent);
    }

    public function test_verify_with_per_destination_test_code_issues_token_pair(): void
    {
        $this->bindResolver(existing: $this->createUser());

        config()->set('sp-jwt-auth.first_factor_otp.test_codes', 'dev@mail.com:009988');

        $dispatch = $this->broker()->request(OtpDestination::email('dev@mail.com'), 'login');

        $verification = $this->broker()->verify($dispatch->otpId, '009988');

        $this->assertNotEmpty($verification->pair->accessToken);
        $this->assertNotNull(FirstFactorOtpCode::query()->findOrFail($dispatch->otpId)->verified_at);
    }
}
