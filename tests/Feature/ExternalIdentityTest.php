<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Sopheak\JwtAuth\DTO\ExternalIdentity;
use Sopheak\JwtAuth\Events\ExternalIdentityResolved;
use Sopheak\JwtAuth\Models\ExternalIdentity as ExternalIdentityModel;
use Sopheak\JwtAuth\Services\ExternalIdentityStore;
use Sopheak\JwtAuth\Tests\TestCase;

final class ExternalIdentityTest extends TestCase
{
    public function test_external_identity_is_stored_without_provider_tokens_by_default(): void
    {
        Event::fake([ExternalIdentityResolved::class]);
        $user = $this->createUser();

        $identity = new ExternalIdentity(
            provider: 'google',
            providerUserId: 'google-123',
            email: 'user@example.com',
            emailVerified: true,
            name: 'Test User',
            avatar: 'https://example.test/avatar.png',
            rawProfile: ['locale' => 'en'],
            providerTokens: ['access_token' => 'provider-secret'],
        );

        $row = app(ExternalIdentityStore::class)->store($identity, $user);

        self::assertSame($user::class, $row->user_type);
        self::assertSame((string) $user->getAuthIdentifier(), $row->user_id);
        self::assertNull(ExternalIdentityModel::query()->find($row->id)->provider_tokens);
        Event::assertDispatched(ExternalIdentityResolved::class);
    }

    public function test_external_identity_store_can_keep_provider_tokens_when_enabled(): void
    {
        config()->set('sp-jwt-auth.external_identities.store_provider_tokens', true);
        $user = $this->createUser();

        $row = app(ExternalIdentityStore::class)->store(new ExternalIdentity(
            provider: 'github',
            providerUserId: 'github-456',
            email: 'dev@example.com',
            providerTokens: ['access_token' => 'provider-secret'],
        ), $user);

        self::assertSame(['access_token' => 'provider-secret'], $row->provider_tokens);
    }
}
