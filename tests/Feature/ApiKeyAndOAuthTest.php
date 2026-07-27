<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Illuminate\Auth\AuthenticationException;
use InvalidArgumentException;
use Sopheak\JwtAuth\DTO\ApiKeyContext;
use Sopheak\JwtAuth\DTO\OAuthClientData;
use Sopheak\JwtAuth\Models\ApiKey;
use Sopheak\JwtAuth\Services\ApiKeyService;
use Sopheak\JwtAuth\Services\OAuthClientRepository;
use Sopheak\JwtAuth\Services\OAuthConsentRepository;
use Sopheak\JwtAuth\Services\OAuthScopeRepository;
use Sopheak\JwtAuth\Tests\TestCase;

final class ApiKeyAndOAuthTest extends TestCase
{
    public function test_api_key_creation_validation_and_rotation(): void
    {
        $service = app(ApiKeyService::class);
        $context = new ApiKeyContext(
            ownerType: 'user',
            ownerId: '123',
            name: 'API Key Test',
            scopes: ['read', 'write'],
            allowedIps: ['192.168.1.1'],
        );

        // 1. Create API key
        $result = $service->createApiKey($context);
        self::assertStringStartsWith('spak_live_', $result->plaintextKey);

        // 2. Validate with allowed IP -> succeeds
        $principal = $service->validateApiKey($result->plaintextKey, '192.168.1.1');
        self::assertSame('user', $principal->ownerType);
        self::assertSame('123', $principal->ownerId);
        self::assertSame(['read', 'write'], $principal->scopes);

        // 3. Validate with disallowed IP -> throws AuthenticationException
        try {
            $service->validateApiKey($result->plaintextKey, '10.0.0.1');
            self::fail('Expected AuthenticationException for IP mismatch');
        } catch (AuthenticationException $authenticationException) {
            self::assertSame('API key is invalid.', $authenticationException->getMessage());
        }

        // 4. Rotate API key
        $rotatedResult = $service->rotateApiKey($result->apiKey->id);
        self::assertNotEquals($result->plaintextKey, $rotatedResult->plaintextKey);

        // Old key is revoked
        $this->expectException(AuthenticationException::class);
        $service->validateApiKey($result->plaintextKey, '192.168.1.1');
    }

    public function test_api_key_revoke_for_owner(): void
    {
        $service = app(ApiKeyService::class);
        $res1 = $service->createApiKey(new ApiKeyContext('user', '456', 'Key 1'));
        $res2 = $service->createApiKey(new ApiKeyContext('user', '456', 'Key 2'));

        $service->revokeApiKeysForOwner('user', '456');

        self::assertNotNull(ApiKey::query()->find($res1->apiKey->id)->revoked_at);
        self::assertNotNull(ApiKey::query()->find($res2->apiKey->id)->revoked_at);
    }

    public function test_oauth_client_repository_operations(): void
    {
        $repo = app(OAuthClientRepository::class);
        $clientData = new OAuthClientData(
            name: 'OAuth Client Test',
            redirectUris: ['https://example.com/callback'],
            allowedGrants: ['authorization_code'],
            allowedScopes: ['read', 'write'],
            confidential: true,
            ownerType: 'user',
            ownerId: '10',
        );

        // Create client
        $secretDto = $repo->createClient($clientData);
        $client = $secretDto->client;
        self::assertNotNull($secretDto->plaintextSecret);
        self::assertTrue($repo->validateSecret($client, $secretDto->plaintextSecret));
        self::assertFalse($repo->validateSecret($client, 'wrong-secret'));

        // Find active client
        self::assertNotNull($repo->findActiveClient($client->id));

        // Rotate secret
        $rotatedSecret = $repo->rotateSecret($client->id);
        self::assertNotEquals($secretDto->plaintextSecret, $rotatedSecret->plaintextSecret);
        self::assertTrue($repo->validateSecret($client->fresh(), $rotatedSecret->plaintextSecret));

        // Revoke client
        $repo->revokeClient($client->id);
        self::assertNull($repo->findActiveClient($client->id));
    }

    public function test_oauth_consent_repository(): void
    {
        $user = $this->createUser();
        $clientRepo = app(OAuthClientRepository::class);
        $consentRepo = app(OAuthConsentRepository::class);

        $clientSecret = $clientRepo->createClient(new OAuthClientData(
            name: 'Consent App',
            redirectUris: ['https://example.com/callback'],
            allowedGrants: ['authorization_code'],
            allowedScopes: ['read', 'write'],
            confidential: false,
            ownerType: 'user',
            ownerId: '1',
        ));
        $client = $clientSecret->client;

        self::assertFalse($consentRepo->hasConsent($user, $client, ['read']));

        $consentRepo->rememberConsent($user, $client, ['read', 'write']);
        self::assertTrue($consentRepo->hasConsent($user, $client, ['read']));
        self::assertTrue($consentRepo->hasConsent($user, $client, ['read', 'write']));

        $consentRepo->revokeConsent($user, $client);
        self::assertFalse($consentRepo->hasConsent($user, $client, ['read']));
    }

    public function test_oauth_scope_repository(): void
    {
        $scopeRepo = new OAuthScopeRepository();

        // 1. Parse scope string
        self::assertSame(['read', 'write'], $scopeRepo->parse('  read   write  '));
        self::assertSame([], $scopeRepo->parse(''));
        self::assertSame([], $scopeRepo->parse(null));

        // 2. Validate scope for client
        $clientRepo = app(OAuthClientRepository::class);
        $clientSecret = $clientRepo->createClient(new OAuthClientData(
            name: 'Scope App',
            redirectUris: ['https://example.com/callback'],
            allowedGrants: ['authorization_code'],
            allowedScopes: ['read'],
            confidential: false,
            ownerType: 'user',
            ownerId: '1',
        ));

        self::assertSame(['read'], $scopeRepo->validateForClient($clientSecret->client, ['read']));

        $this->expectException(InvalidArgumentException::class);
        $scopeRepo->validateForClient($clientSecret->client, ['admin']);
    }
}
