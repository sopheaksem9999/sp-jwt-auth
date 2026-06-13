<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Route;
use Sopheak\JwtAuth\DTO\ApiKeyContext;
use Sopheak\JwtAuth\Models\ApiKey;
use Sopheak\JwtAuth\Services\ApiKeyService;
use Sopheak\JwtAuth\Tests\TestCase;

final class ApiKeyTest extends TestCase
{
    public function test_api_key_plaintext_is_returned_once_and_validates_principal(): void
    {
        config()->set('sp-jwt-auth.api_keys.enabled', true);

        $result = app(ApiKeyService::class)->createApiKey(new ApiKeyContext(
            ownerType: 'tenant',
            ownerId: '42',
            name: 'ERP sync',
            scopes: ['invoices.write'],
            claims: ['tenant_id' => 42],
        ));

        self::assertStringStartsWith('spak_live_', $result->plaintextKey);
        self::assertDatabaseMissing('sp_jwt_api_keys', ['secret_hash' => explode('.', $result->plaintextKey, 2)[1]]);

        $principal = app(ApiKeyService::class)->validateApiKey($result->plaintextKey);

        self::assertSame('tenant', $principal->ownerType);
        self::assertSame('42', $principal->ownerId);
        self::assertTrue($principal->can('invoices.write'));
        self::assertNotNull(ApiKey::query()->find($result->apiKey->id)->last_used_at);
    }

    public function test_api_key_context_can_be_created_for_company_integrations(): void
    {
        config()->set('sp-jwt-auth.api_keys.enabled', true);

        $context = ApiKeyContext::forCompany(
            companyId: 42,
            name: 'QuickBooks sync',
            scopes: ['qbo.sync'],
            claims: ['environment' => 'production'],
            allowedIps: ['203.0.113.0/24'],
        );

        $result = app(ApiKeyService::class)->createApiKey($context);

        self::assertSame('company', $result->apiKey->owner_type);
        self::assertSame('42', $result->apiKey->owner_id);
        self::assertSame(['environment' => 'production', 'company_id' => 42], $result->apiKey->claims);
        self::assertSame(['qbo.sync'], $result->apiKey->scopes);
    }

    public function test_api_key_middleware_authenticates_and_checks_scope(): void
    {
        config()->set('sp-jwt-auth.api_keys.enabled', true);
        Route::middleware(['sp.api_key', 'sp.api_key.scope:invoices.write'])
            ->get('/api-key-protected', fn (): array => ['owner' => request()->attributes->get('sp_api_key_principal')->ownerId]);

        $result = app(ApiKeyService::class)->createApiKey(new ApiKeyContext(
            ownerType: 'tenant',
            ownerId: '42',
            name: 'ERP sync',
            scopes: ['invoices.write'],
        ));

        $this->withHeader('Authorization', 'Bearer ' . $result->plaintextKey)
            ->getJson('/api-key-protected')
            ->assertOk()
            ->assertJson(['owner' => '42']);
    }

    public function test_revoked_api_key_fails_validation(): void
    {
        config()->set('sp-jwt-auth.api_keys.enabled', true);
        $result = app(ApiKeyService::class)->createApiKey(new ApiKeyContext(
            ownerType: 'tenant',
            ownerId: '42',
            name: 'ERP sync',
        ));

        app(ApiKeyService::class)->revokeApiKey($result->apiKey->id);

        $this->expectException(AuthenticationException::class);
        app(ApiKeyService::class)->validateApiKey($result->plaintextKey);
    }
}
