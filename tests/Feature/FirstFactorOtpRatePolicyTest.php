<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use RuntimeException;
use Override;
use Illuminate\Auth\AuthenticationException;
use Sopheak\JwtAuth\Contracts\OtpChannelSender;
use Sopheak\JwtAuth\DTO\OtpDestination;
use Sopheak\JwtAuth\DTO\OtpDispatch;
use Sopheak\JwtAuth\Services\FirstFactorOtpBroker;
use Sopheak\JwtAuth\Tests\Fixtures\User;
use Sopheak\JwtAuth\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

final class FirstFactorOtpRatePolicyTest extends TestCase
{
    #[Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('sp-jwt-auth.first_factor_otp.user_model', User::class);
    }

    public function test_direct_broker_calls_share_the_sms_project_quota_across_destinations(): void
    {
        config()->set('sp-jwt-auth.first_factor_otp.resend_cooldown_seconds', 0);
        config()->set('sp-jwt-auth.first_factor_otp.limits.sms_per_project', ['max_attempts' => 2, 'decay_seconds' => 3600]);

        $broker = app(FirstFactorOtpBroker::class);
        $broker->request(OtpDestination::phone('+85512345671'), 'login', ip: '192.0.2.1');

        app(FirstFactorOtpBroker::class)->request(OtpDestination::phone('+85512345672'), 'login', ip: '192.0.2.2');

        try {
            $broker->request(OtpDestination::phone('+85512345673'), 'login', ip: '192.0.2.3');
            self::fail('Expected the project SMS quota to reject the third send.');
        } catch (TooManyRequestsHttpException $tooManyRequestsHttpException) {
            self::assertGreaterThan(0, (int) $tooManyRequestsHttpException->getHeaders()['Retry-After']);
        }

        $broker->request(OtpDestination::email('user@example.com'), 'login', ip: '192.0.2.3');
    }

    public function test_send_and_verify_policies_use_independent_windows(): void
    {
        config()->set('sp-jwt-auth.first_factor_otp.resend_cooldown_seconds', 0);
        config()->set('sp-jwt-auth.first_factor_otp.limits.send_per_destination', ['max_attempts' => 1, 'decay_seconds' => 5]);
        config()->set('sp-jwt-auth.first_factor_otp.limits.send_per_ip', ['max_attempts' => 2, 'decay_seconds' => 60]);
        config()->set('sp-jwt-auth.first_factor_otp.limits.verify_per_ip', ['max_attempts' => 1, 'decay_seconds' => 120]);

        $broker = app(FirstFactorOtpBroker::class);
        $first = $broker->request(OtpDestination::email('one@example.com'), 'login', ip: '192.0.2.10');
        $broker->request(OtpDestination::email('two@example.com'), 'login', ip: '192.0.2.10');

        try {
            $broker->verify($first->otpId, '000000', ip: '192.0.2.10');
        } catch (AuthenticationException) {
            // The first verification is allowed through the rate policy.
        }

        $this->expectException(TooManyRequestsHttpException::class);
        $broker->verify($first->otpId, '000000', ip: '192.0.2.10');
    }

    public function test_cooldown_retry_after_reports_remaining_seconds(): void
    {
        $broker = app(FirstFactorOtpBroker::class);
        $destination = OtpDestination::phone('+85512345674');
        $broker->request($destination, 'login');

        try {
            $broker->request($destination, 'login');
            self::fail('Expected cooldown rejection.');
        } catch (TooManyRequestsHttpException $tooManyRequestsHttpException) {
            self::assertGreaterThanOrEqual(58, (int) $tooManyRequestsHttpException->getHeaders()['Retry-After']);
        }
    }

    public function test_send_per_ip_limit_applies_to_direct_broker_calls(): void
    {
        config()->set('sp-jwt-auth.first_factor_otp.limits.send_per_ip', ['max_attempts' => 1, 'decay_seconds' => 45]);

        $broker = app(FirstFactorOtpBroker::class);
        $broker->request(OtpDestination::email('one@example.com'), 'login', ip: '192.0.2.20');

        try {
            $broker->request(OtpDestination::email('two@example.com'), 'login', ip: '192.0.2.20');
            self::fail('Expected the IP send policy to reject the second request.');
        } catch (TooManyRequestsHttpException $tooManyRequestsHttpException) {
            self::assertGreaterThanOrEqual(43, (int) $tooManyRequestsHttpException->getHeaders()['Retry-After']);
        }

        $broker->request(OtpDestination::email('three@example.com'), 'login', ip: '192.0.2.21');
    }

    public function test_send_per_destination_has_its_own_decay_window(): void
    {
        config()->set('sp-jwt-auth.first_factor_otp.resend_cooldown_seconds', 0);
        config()->set('sp-jwt-auth.first_factor_otp.limits.send_per_destination', ['max_attempts' => 1, 'decay_seconds' => 12]);
        config()->set('sp-jwt-auth.first_factor_otp.limits.decay_minutes', 60);

        $broker = app(FirstFactorOtpBroker::class);
        $destination = OtpDestination::email('window@example.com');
        $broker->request($destination, 'login', ip: '192.0.2.22');

        try {
            $broker->request($destination, 'login', ip: '192.0.2.22');
            self::fail('Expected the destination send policy to reject the second request.');
        } catch (TooManyRequestsHttpException $tooManyRequestsHttpException) {
            self::assertGreaterThanOrEqual(10, (int) $tooManyRequestsHttpException->getHeaders()['Retry-After']);
            self::assertLessThanOrEqual(12, (int) $tooManyRequestsHttpException->getHeaders()['Retry-After']);
        }
    }

    public function test_request_resend_and_resend_by_destination_share_the_sms_quota(): void
    {
        config()->set('sp-jwt-auth.first_factor_otp.resend_cooldown_seconds', 0);
        config()->set('sp-jwt-auth.first_factor_otp.limits.sms_per_project', ['max_attempts' => 3, 'decay_seconds' => 3600]);

        $broker = app(FirstFactorOtpBroker::class);
        $destination = OtpDestination::phone('+85512345675');
        $first = $broker->request($destination, 'login');
        $second = $broker->resend($first->otpId, $destination);
        $broker->resendByDestination($destination, 'login');

        $this->expectException(TooManyRequestsHttpException::class);
        $broker->resend($second->otpId, $destination);
    }

    public function test_failed_delivery_releases_sms_quota_for_immediate_retry(): void
    {
        config()->set('sp-jwt-auth.first_factor_otp.limits.sms_per_project', ['max_attempts' => 1, 'decay_seconds' => 3600]);

        $sender = new class implements OtpChannelSender {
            public bool $fail = true;

            public function send(OtpDispatch $dispatch): void
            {
                if ($this->fail) {
                    throw new RuntimeException('gateway unavailable');
                }
            }
        };
        $this->app->instance(OtpChannelSender::class, $sender);
        $broker = app(FirstFactorOtpBroker::class);

        try {
            $broker->request(OtpDestination::phone('+85512345676'), 'login');
            self::fail('Expected the sender to fail.');
        } catch (RuntimeException) {
            $sender->fail = false;
        }

        $dispatch = $broker->request(OtpDestination::phone('+85512345676'), 'login');

        self::assertNotEmpty($dispatch->otpId);
    }

    public function test_in_flight_sms_reservation_blocks_a_second_send(): void
    {
        config()->set('sp-jwt-auth.first_factor_otp.limits.sms_per_project', ['max_attempts' => 1, 'decay_seconds' => 3600]);

        $broker = app(FirstFactorOtpBroker::class);
        $sender = new class ($broker) implements OtpChannelSender {
            public bool $blocked = false;

            public function __construct(private readonly FirstFactorOtpBroker $broker) {}

            public function send(OtpDispatch $dispatch): void
            {
                try {
                    $this->broker->request(OtpDestination::phone('+85512345678'), 'login');
                } catch (TooManyRequestsHttpException) {
                    $this->blocked = true;
                }
            }
        };
        $this->app->instance(OtpChannelSender::class, $sender);

        $broker->request(OtpDestination::phone('+85512345677'), 'login');

        self::assertTrue($sender->blocked);
    }

    public function test_legacy_destination_and_verify_limit_values_still_apply(): void
    {
        config()->set('sp-jwt-auth.first_factor_otp.resend_cooldown_seconds', 0);
        config()->set('sp-jwt-auth.first_factor_otp.limits.request_per_destination', 1);
        config()->set('sp-jwt-auth.first_factor_otp.limits.verify_per_ip', 1);

        $broker = app(FirstFactorOtpBroker::class);
        $first = $broker->request(OtpDestination::email('legacy@example.com'), 'login', ip: '192.0.2.30');

        try {
            $broker->request(OtpDestination::email('legacy@example.com'), 'login', ip: '192.0.2.30');
            self::fail('Expected the legacy destination limit to apply.');
        } catch (TooManyRequestsHttpException) {
            // The destination limit is active.
        }

        try {
            $broker->verify($first->otpId, '000000', ip: '192.0.2.30');
        } catch (AuthenticationException) {
            // A wrong code still consumes one verification allowance.
        }

        $this->expectException(TooManyRequestsHttpException::class);
        $broker->verify($first->otpId, '000000', ip: '192.0.2.30');
    }
}
