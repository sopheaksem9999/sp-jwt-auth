<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Route;
use Sopheak\JwtAuth\DTO\OAuthClientData;
use Sopheak\JwtAuth\DTO\OAuthConsentContext;
use Sopheak\JwtAuth\Models\OAuthAuthorizationCode;
use Sopheak\JwtAuth\Services\OAuthClientRepository;
use Sopheak\JwtAuth\Services\OAuthServerService;
use Sopheak\JwtAuth\Tests\TestCase;

final class OAuthServerTest extends TestCase
{
    public function test_oauth_client_secret_is_returned_once_and_hashed_at_rest(): void
    {
        config()->set('sp-jwt-auth.oauth_server.enabled', true);

        $result = app(OAuthClientRepository::class)->createClient(new OAuthClientData(
            name: 'ERP Connector',
            redirectUris: ['https://client.test/callback'],
            allowedGrants: ['authorization_code', 'refresh_token'],
            allowedScopes: ['invoices.read'],
        ));

        self::assertNotNull($result->plaintextSecret);
        self::assertDatabaseMissing('sp_oauth_clients', ['secret_hash' => $result->plaintextSecret]);
        self::assertTrue(app(OAuthClientRepository::class)->validateSecret($result->client, $result->plaintextSecret));
    }

    public function test_authorization_code_with_pkce_issues_oauth_tokens_and_rejects_code_reuse(): void
    {
        config()->set('sp-jwt-auth.oauth_server.enabled', true);
        $user = $this->createUser();
        $verifier = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ01234567890123456789';
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $client = app(OAuthClientRepository::class)->createClient(new OAuthClientData(
            name: 'SPA Client',
            redirectUris: ['https://client.test/callback'],
            allowedGrants: ['authorization_code', 'refresh_token'],
            allowedScopes: ['invoices.read'],
            confidential: false,
        ))->client;

        $authorization = app(OAuthServerService::class)->validateAuthorizationRequest(request()->create('/oauth/authorize', 'GET', [
            'response_type' => 'code',
            'client_id' => $client->id,
            'redirect_uri' => 'https://client.test/callback',
            'scope' => 'invoices.read',
            'state' => 'state-123',
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]));

        $code = app(OAuthServerService::class)->approveAuthorizationRequest($authorization, $user, new OAuthConsentContext(
            scopes: ['invoices.read'],
            remember: true,
        ));
        $token = app(OAuthServerService::class)->issueTokenFromRequest(request()->create('/oauth/token', 'POST', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->id,
            'redirect_uri' => 'https://client.test/callback',
            'code' => $code->code,
            'code_verifier' => $verifier,
        ]));

        self::assertSame('Bearer', $token->tokenType);
        self::assertNotNull($token->refreshToken);
        self::assertTrue(app(OAuthServerService::class)->introspect($token->accessToken)->active);
        self::assertNotNull(OAuthAuthorizationCode::query()->first()->revoked_at);

        $this->expectException(AuthenticationException::class);
        app(OAuthServerService::class)->issueTokenFromRequest(request()->create('/oauth/token', 'POST', [
            'grant_type' => 'authorization_code',
            'client_id' => $client->id,
            'redirect_uri' => 'https://client.test/callback',
            'code' => $code->code,
            'code_verifier' => $verifier,
        ]));
    }

    public function test_client_credentials_token_authenticates_resource_middleware(): void
    {
        config()->set('sp-jwt-auth.oauth_server.enabled', true);
        Route::middleware(['sp.oauth', 'sp.oauth.scope:invoices.read', 'sp.oauth.client:'])
            ->get('/oauth-protected', fn (): array => [
                'client' => request()->attributes->get('sp_oauth_principal')->clientId,
                'user' => request()->attributes->get('sp_oauth_principal')->userId,
            ]);

        $clientSecret = app(OAuthClientRepository::class)->createClient(new OAuthClientData(
            name: 'M2M Client',
            allowedGrants: ['client_credentials'],
            allowedScopes: ['invoices.read'],
        ));

        $token = app(OAuthServerService::class)->issueTokenFromRequest(request()->create('/oauth/token', 'POST', [
            'grant_type' => 'client_credentials',
            'client_id' => $clientSecret->client->id,
            'client_secret' => $clientSecret->plaintextSecret,
            'scope' => 'invoices.read',
        ]));

        $this->withHeader('Authorization', 'Bearer ' . $token->accessToken)
            ->getJson('/oauth-protected')
            ->assertOk()
            ->assertJson([
                'client' => $clientSecret->client->id,
                'user' => null,
            ]);
    }
}
