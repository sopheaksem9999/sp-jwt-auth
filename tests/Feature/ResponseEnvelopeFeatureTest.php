<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;
use Sopheak\JwtAuth\Support\ResponseEnvelope;
use Sopheak\JwtAuth\Tests\Fixtures\TestEnvelope;
use Sopheak\JwtAuth\Tests\Fixtures\TestUserResource;
use Sopheak\JwtAuth\Tests\Fixtures\User;
use Sopheak\JwtAuth\Tests\TestCase;

final class ResponseEnvelopeFeatureTest extends TestCase
{
    public function test_custom_class_mode_resolves_envelope_from_container(): void
    {
        $result = ResponseEnvelope::wrap(['otp_id' => 'abc'], TestEnvelope::class);

        $this->assertSame(['wrapped' => ['otp_id' => 'abc']], $result);
    }

    public function test_custom_class_not_implementing_contract_throws(): void
    {
        $this->expectException(RuntimeException::class);

        ResponseEnvelope::wrap([], User::class);
    }

    public function test_serialize_user_via_resource(): void
    {
        $user = $this->createUser();

        $result = ResponseEnvelope::serializeUser($user, TestUserResource::class);

        $this->assertSame(['id' => $user->getAuthIdentifier(), 'resource' => true], $result);
    }

    public function test_serialize_user_defaults_to_to_array(): void
    {
        $user = $this->createUser();

        $result = ResponseEnvelope::serializeUser($user);

        $this->assertSame($user->getAuthIdentifier(), $result['id']);
        $this->assertArrayNotHasKey('password', $result);
    }

    public function test_serialize_non_model_user_returns_id_only(): void
    {
        $principal = new class implements Authenticatable {
            public function getAuthIdentifierName(): string
            {
                return 'id';
            }

            public function getAuthIdentifier(): mixed
            {
                return 'p1';
            }

            public function getAuthPasswordName(): string
            {
                return 'password';
            }

            public function getAuthPassword(): ?string
            {
                return null;
            }

            public function getRememberToken(): ?string
            {
                return null;
            }

            public function setRememberToken($value): void
            {
            }

            public function getRememberTokenName(): ?string
            {
                return null;
            }
        };

        $this->assertSame(['id' => 'p1'], ResponseEnvelope::serializeUser($principal));
    }
}
