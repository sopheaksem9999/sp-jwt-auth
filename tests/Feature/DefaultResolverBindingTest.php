<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Override;
use Sopheak\JwtAuth\Contracts\FirstFactorUserResolver;
use Sopheak\JwtAuth\DTO\OtpDestination;
use Sopheak\JwtAuth\Services\DefaultFirstFactorUserResolver;
use Sopheak\JwtAuth\Services\FirstFactorOtpBroker;
use Sopheak\JwtAuth\Tests\Fixtures\User;
use Sopheak\JwtAuth\Tests\TestCase;

final class DefaultResolverBindingTest extends TestCase
{
    #[Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('sp-jwt-auth.first_factor_otp.user_model', User::class);
    }

    public function test_default_resolver_is_bound_when_user_model_configured(): void
    {
        $this->assertInstanceOf(DefaultFirstFactorUserResolver::class, $this->app->make(FirstFactorUserResolver::class));
    }

    public function test_verify_creates_user_without_consumer_resolver_code(): void
    {
        config()->set('sp-jwt-auth.first_factor_otp.test_mode', true);
        config()->set('sp-jwt-auth.first_factor_otp.test_code', '424242');

        $broker = $this->app->make(FirstFactorOtpBroker::class);
        $dispatch = $broker->request(OtpDestination::email('new@example.com'), 'login');

        $verification = $broker->verify($dispatch->otpId, '424242', OtpDestination::email('new@example.com'));

        $this->assertSame('new@example.com', $verification->user->getAttribute('email'));
        $this->assertNotNull($verification->user->getAttribute('email_verified_at'));
        $this->assertNotEmpty($verification->pair->accessToken);
    }

    public function test_verify_resolves_existing_user_without_consumer_resolver_code(): void
    {
        config()->set('sp-jwt-auth.first_factor_otp.test_mode', true);
        config()->set('sp-jwt-auth.first_factor_otp.test_code', '424242');

        $existing = $this->createUser(['email' => 'existing@example.com']);

        $broker = $this->app->make(FirstFactorOtpBroker::class);
        $dispatch = $broker->request(OtpDestination::email('existing@example.com'), 'login');

        $verification = $broker->verify($dispatch->otpId, '424242', OtpDestination::email('existing@example.com'));

        $this->assertSame($existing->getAuthIdentifier(), $verification->user->getAuthIdentifier());
    }
}
