<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;
use Illuminate\Support\Facades\Event;
use Sopheak\JwtAuth\Contracts\FirstFactorUserResolver;
use Sopheak\JwtAuth\Contracts\OtpChannelSender;
use Sopheak\JwtAuth\Contracts\OtpDeliveryAwareSender;
use Sopheak\JwtAuth\DTO\OtpDeliveryResult;
use Sopheak\JwtAuth\DTO\OtpDestination;
use Sopheak\JwtAuth\DTO\OtpDispatch;
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Events\OtpCodeCreated;
use Sopheak\JwtAuth\Events\OtpCodeResent;
use Sopheak\JwtAuth\Events\OtpCodeSent;
use Sopheak\JwtAuth\Events\OtpDeliveryFailed;
use Sopheak\JwtAuth\Exceptions\OtpDeliveryFailedException;
use Sopheak\JwtAuth\Models\FirstFactorOtpCode;
use Sopheak\JwtAuth\Models\MfaChallenge;
use Sopheak\JwtAuth\Models\MfaOtpCode;
use Sopheak\JwtAuth\Services\FirstFactorOtpBroker;
use Sopheak\JwtAuth\Services\MfaChallengeBroker;
use Sopheak\JwtAuth\Services\OtpChallengeBroker;
use Sopheak\JwtAuth\Tests\TestCase;

final class OtpDeliveryResultTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(FirstFactorUserResolver::class, new readonly class implements FirstFactorUserResolver {
            public function resolve(OtpDestination $destination, string $purpose): ?Authenticatable
            {
                return null;
            }

            public function create(OtpDestination $destination, string $purpose, ?string $requestedType): ?Authenticatable
            {
                return null;
            }
        });
    }

    private function broker(): FirstFactorOtpBroker
    {
        return $this->app->make(FirstFactorOtpBroker::class);
    }

    /** A legacy sender: implements only the original void contract. */
    private function legacySender(): object
    {
        return new class implements OtpChannelSender {
            public int $sent = 0;

            public function send(OtpDispatch $dispatch): void
            {
                $this->sent++;
            }
        };
    }

    /** A result-aware sender: implements only the new contract. */
    private function awareSender(OtpDeliveryResult $result): object
    {
        return new class ($result) implements OtpDeliveryAwareSender {
            public int $delivered = 0;

            public function __construct(private readonly OtpDeliveryResult $result)
            {
            }

            public function deliver(OtpDispatch $dispatch): OtpDeliveryResult
            {
                $this->delivered++;

                return $this->result;
            }
        };
    }

    // ---- Case 1: BC regression guard -------------------------------------

    public function test_legacy_void_sender_still_persists_challenge_and_dispatches_sent_event(): void
    {
        Event::fake([OtpCodeSent::class, OtpCodeCreated::class, OtpDeliveryFailed::class]);

        $sender = $this->legacySender();
        $this->app->instance(OtpChannelSender::class, $sender);

        $dispatch = $this->broker()->request(OtpDestination::email('legacy@mail.com'), 'login');

        self::assertSame(1, $sender->sent);
        self::assertNotNull(FirstFactorOtpCode::query()->find($dispatch->otpId));
        Event::assertDispatched(OtpCodeCreated::class);
        Event::assertNotDispatched(OtpDeliveryFailed::class);
        Event::assertDispatched(
            OtpCodeSent::class,
            static fn (OtpCodeSent $event): bool => !$event->delivery instanceof OtpDeliveryResult,
        );
    }

    // ---- Case 2: success carries the provider reference ------------------

    public function test_successful_delivery_persists_challenge_and_carries_provider_reference(): void
    {
        Event::fake([OtpCodeSent::class, OtpCodeCreated::class, OtpDeliveryFailed::class]);

        $sender = $this->awareSender(OtpDeliveryResult::success('msg-123'));
        $this->app->instance(OtpDeliveryAwareSender::class, $sender);

        $dispatch = $this->broker()->request(OtpDestination::email('ok@mail.com'), 'login');

        self::assertSame(1, $sender->delivered);
        self::assertNotNull(FirstFactorOtpCode::query()->find($dispatch->otpId));
        Event::assertDispatched(OtpCodeCreated::class);
        Event::assertNotDispatched(OtpDeliveryFailed::class);
        Event::assertDispatched(
            OtpCodeSent::class,
            static fn (OtpCodeSent $event): bool => $event->delivery?->successful === true
                && $event->delivery->providerReference === 'msg-123',
        );
    }

    // ---- Case 3: failure rolls the challenge back ------------------------

    public function test_failed_delivery_throws_and_leaves_no_challenge_row(): void
    {
        Event::fake([OtpCodeSent::class, OtpCodeCreated::class, OtpDeliveryFailed::class]);

        $this->app->instance(
            OtpDeliveryAwareSender::class,
            $this->awareSender(OtpDeliveryResult::failure('connection_error', 'ref-9')),
        );

        try {
            $this->broker()->request(OtpDestination::email('fail@mail.com'), 'login');
            self::fail('Expected OtpDeliveryFailedException.');
        } catch (OtpDeliveryFailedException $otpDeliveryFailedException) {
            self::assertSame(502, $otpDeliveryFailedException->getStatusCode());
            self::assertSame('connection_error', $otpDeliveryFailedException->failureReason);
            self::assertSame('ref-9', $otpDeliveryFailedException->providerReference);
        }

        self::assertSame(0, FirstFactorOtpCode::query()->count());
        Event::assertNotDispatched(OtpCodeSent::class);
        Event::assertNotDispatched(OtpCodeCreated::class);
        Event::assertDispatched(
            OtpDeliveryFailed::class,
            static fn (OtpDeliveryFailed $event): bool => $event->result->failureReason === 'connection_error',
        );
    }

    // ---- Case 4: a failed send must not arm the resend cooldown ----------

    public function test_retry_immediately_after_failed_delivery_is_not_cooldown_blocked(): void
    {
        $this->app->instance(
            OtpDeliveryAwareSender::class,
            $this->awareSender(OtpDeliveryResult::failure('connection_error')),
        );

        try {
            $this->broker()->request(OtpDestination::phone('+85512345678'), 'login');
        } catch (OtpDeliveryFailedException) {
            // expected
        }

        // Gateway recovers; the very next request must go through.
        $this->app->instance(
            OtpDeliveryAwareSender::class,
            $this->awareSender(OtpDeliveryResult::success('msg-ok')),
        );

        $dispatch = $this->broker()->request(OtpDestination::phone('+85512345678'), 'login');

        self::assertNotNull(FirstFactorOtpCode::query()->find($dispatch->otpId));
        self::assertSame(1, FirstFactorOtpCode::query()->count());
    }

    // ---- Repro of client bug report (0.1.23-beta.46) ---------------------
    // A sender that THROWS (rather than returning a failure result) must not
    // leave an orphaned row behind, on either contract.

    public function test_legacy_sender_that_throws_leaves_no_orphaned_row(): void
    {
        $this->app->instance(OtpChannelSender::class, new class implements OtpChannelSender {
            public function send(OtpDispatch $dispatch): void
            {
                throw new RuntimeException('gateway down');
            }
        });

        try {
            $this->broker()->request(OtpDestination::phone('+85512345678'), 'login');
            self::fail('Expected the sender exception to propagate.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('gateway down', $runtimeException->getMessage());
        }

        self::assertSame(0, FirstFactorOtpCode::query()->count(), 'A failed send must not persist a challenge.');
    }

    public function test_legacy_sender_that_throws_does_not_arm_the_resend_cooldown(): void
    {
        $this->app->instance(OtpChannelSender::class, new class implements OtpChannelSender {
            public function send(OtpDispatch $dispatch): void
            {
                throw new RuntimeException('gateway down');
            }
        });

        try {
            $this->broker()->request(OtpDestination::phone('+85512345678'), 'login');
        } catch (RuntimeException) {
            // expected
        }

        // Gateway recovers. The immediate retry must not hit the 60s cooldown.
        $this->app->instance(OtpChannelSender::class, $this->legacySender());

        $dispatch = $this->broker()->request(OtpDestination::phone('+85512345678'), 'login');

        self::assertNotNull(FirstFactorOtpCode::query()->find($dispatch->otpId));
    }

    public function test_result_aware_sender_that_throws_leaves_no_orphaned_row(): void
    {
        $this->app->instance(OtpDeliveryAwareSender::class, new class implements OtpDeliveryAwareSender {
            public function deliver(OtpDispatch $dispatch): OtpDeliveryResult
            {
                throw new RuntimeException('connection reset');
            }
        });

        try {
            $this->broker()->request(OtpDestination::phone('+85599990000'), 'login');
            self::fail('Expected the sender exception to propagate.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('connection reset', $runtimeException->getMessage());
        }

        self::assertSame(0, FirstFactorOtpCode::query()->count(), 'A throwing deliver() must not persist a challenge.');
    }

    // ---- Case 5: dual-interface class bound only to the old key ----------

    public function test_sender_implementing_both_contracts_prefers_deliver(): void
    {
        $sender = new class implements OtpChannelSender, OtpDeliveryAwareSender {
            public int $sent = 0;

            public int $delivered = 0;

            public function send(OtpDispatch $dispatch): void
            {
                $this->sent++;
            }

            public function deliver(OtpDispatch $dispatch): OtpDeliveryResult
            {
                $this->delivered++;

                return OtpDeliveryResult::success('dual-1');
            }
        };

        $this->app->instance(OtpChannelSender::class, $sender);

        $this->broker()->request(OtpDestination::email('dual@mail.com'), 'login');

        self::assertSame(1, $sender->delivered);
        self::assertSame(0, $sender->sent);
    }

    // ---- Cases 6 & 7: test codes bypass delivery entirely ----------------

    public function test_per_destination_test_code_skips_result_aware_sender(): void
    {
        config()->set('sp-jwt-auth.first_factor_otp.test_codes', 'dev@mail.com:009988');

        Event::fake([OtpCodeSent::class, OtpCodeCreated::class]);

        $sender = $this->awareSender(OtpDeliveryResult::failure('should_never_run'));
        $this->app->instance(OtpDeliveryAwareSender::class, $sender);

        $this->broker()->request(OtpDestination::email('dev@mail.com'), 'login');

        self::assertSame(0, $sender->delivered);
        Event::assertDispatched(OtpCodeCreated::class);
        Event::assertNotDispatched(OtpCodeSent::class);
    }

    public function test_global_test_mode_skips_result_aware_sender(): void
    {
        config()->set('sp-jwt-auth.first_factor_otp.test_mode', true);
        config()->set('sp-jwt-auth.first_factor_otp.test_code', '424242');

        Event::fake([OtpCodeSent::class, OtpCodeCreated::class]);

        $sender = $this->awareSender(OtpDeliveryResult::failure('should_never_run'));
        $this->app->instance(OtpDeliveryAwareSender::class, $sender);

        $this->broker()->request(OtpDestination::email('any@mail.com'), 'login');

        self::assertSame(0, $sender->delivered);
        Event::assertDispatched(OtpCodeCreated::class);
        Event::assertNotDispatched(OtpCodeSent::class);
    }

    // ---- Case 8: resend surfaces the failure -----------------------------

    public function test_resend_with_failed_delivery_throws_and_does_not_dispatch_resent_event(): void
    {
        $this->app->instance(
            OtpDeliveryAwareSender::class,
            $this->awareSender(OtpDeliveryResult::success('msg-1')),
        );

        $first = $this->broker()->request(OtpDestination::phone('+85599887766'), 'login');

        FirstFactorOtpCode::query()->whereKey($first->otpId)->update(['last_sent_at' => now()->subMinutes(5)]);

        Event::fake([OtpCodeResent::class, OtpDeliveryFailed::class]);

        $this->app->instance(
            OtpDeliveryAwareSender::class,
            $this->awareSender(OtpDeliveryResult::failure('http_500')),
        );

        $this->expectException(OtpDeliveryFailedException::class);

        try {
            $this->broker()->resend($first->otpId, OtpDestination::phone('+85599887766'));
        } finally {
            Event::assertNotDispatched(OtpCodeResent::class);
            Event::assertDispatched(OtpDeliveryFailed::class);
        }
    }

    // ---- Case 9: MFA failure deletes the code, keeps the challenge -------

    public function test_mfa_failed_delivery_deletes_otp_but_keeps_parent_challenge(): void
    {
        $user = $this->createUser();
        $challenge = $this->app->make(MfaChallengeBroker::class)->create($user, TokenContext::make());

        $this->app->instance(
            OtpDeliveryAwareSender::class,
            $this->awareSender(OtpDeliveryResult::failure('connection_error')),
        );

        try {
            $this->app->make(OtpChallengeBroker::class)
                ->createOtp($challenge, OtpDestination::phone('+85512341234'));
            self::fail('Expected OtpDeliveryFailedException.');
        } catch (OtpDeliveryFailedException $otpDeliveryFailedException) {
            self::assertSame(502, $otpDeliveryFailedException->getStatusCode());
        }

        self::assertSame(0, MfaOtpCode::query()->where('challenge_id', $challenge->id)->count());

        $fresh = MfaChallenge::query()->find($challenge->id);
        self::assertNotNull($fresh);
        self::assertNull($fresh->completed_at);
    }

    // ---- Case 10: MFA BC regression guard --------------------------------

    public function test_mfa_legacy_void_sender_is_unchanged(): void
    {
        $user = $this->createUser();
        $challenge = $this->app->make(MfaChallengeBroker::class)->create($user, TokenContext::make());

        $sender = $this->legacySender();
        $this->app->instance(OtpChannelSender::class, $sender);

        $dispatch = $this->app->make(OtpChallengeBroker::class)
            ->createOtp($challenge, OtpDestination::phone('+85512341234'));

        self::assertSame(1, $sender->sent);
        self::assertNotNull(MfaOtpCode::query()->find($dispatch->otpId));
    }
}
