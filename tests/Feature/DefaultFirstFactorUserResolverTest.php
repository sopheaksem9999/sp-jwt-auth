<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Override;
use RuntimeException;
use Sopheak\JwtAuth\DTO\OtpDestination;
use Sopheak\JwtAuth\Services\DefaultFirstFactorUserResolver;
use Sopheak\JwtAuth\Tests\Fixtures\User;
use Sopheak\JwtAuth\Tests\TestCase;

final class DefaultFirstFactorUserResolverTest extends TestCase
{
    private function resolver(): DefaultFirstFactorUserResolver
    {
        return new DefaultFirstFactorUserResolver();
    }

    #[Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('sp-jwt-auth.first_factor_otp.user_model', User::class);
    }

    public function test_resolve_finds_user_by_email(): void
    {
        $user = $this->createUser(['email' => 'existing@example.com']);

        $found = $this->resolver()->resolve(OtpDestination::email('existing@example.com'), 'login');

        $this->assertNotNull($found);
        $this->assertSame($user->getAuthIdentifier(), $found->getAuthIdentifier());
    }

    public function test_resolve_finds_user_by_phone(): void
    {
        $user = $this->createUser(['phone' => '+85512345678']);

        $found = $this->resolver()->resolve(OtpDestination::phone('+85512345678'), 'login');

        $this->assertNotNull($found);
        $this->assertSame($user->getAuthIdentifier(), $found->getAuthIdentifier());
    }

    public function test_resolve_returns_null_when_no_match(): void
    {
        $this->assertNull($this->resolver()->resolve(OtpDestination::email('nobody@example.com'), 'login'));
    }

    public function test_resolve_returns_null_when_column_missing(): void
    {
        config()->set('sp-jwt-auth.first_factor_otp.destination_columns', ['phone' => 'phone_col', 'email' => 'email']);

        $this->assertNull($this->resolver()->resolve(OtpDestination::phone('+85512345678'), 'login'));
    }

    public function test_create_sets_destination_and_requested_type(): void
    {
        $user = $this->resolver()->create(OtpDestination::email('new@example.com'), 'register', 'driver');

        $this->assertNotNull($user);
        $this->assertSame('new@example.com', $user->getAttribute('email'));
        $this->assertSame('driver', $user->getAttribute('type'));
    }

    public function test_create_without_requested_type_leaves_column_null(): void
    {
        $user = $this->resolver()->create(OtpDestination::phone('+85512345678'), 'login', null);

        $this->assertNotNull($user);
        $this->assertSame('+85512345678', $user->getAttribute('phone'));
        $this->assertNull($user->getAttribute('type'));
    }

    public function test_create_returns_existing_user_on_unique_race(): void
    {
        $this->createUser(['email' => 'taken@example.com']);

        $user = $this->resolver()->create(OtpDestination::email('taken@example.com'), 'login', null);

        $this->assertNotNull($user);
        $this->assertSame('taken@example.com', $user->getAttribute('email'));
    }

    public function test_missing_model_class_throws(): void
    {
        config()->set('sp-jwt-auth.first_factor_otp.user_model', 'App\\Models\\DoesNotExist');

        $this->expectException(RuntimeException::class);

        $this->resolver()->resolve(OtpDestination::email('a@b.com'), 'login');
    }
}
