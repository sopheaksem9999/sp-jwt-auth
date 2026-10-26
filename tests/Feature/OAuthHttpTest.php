<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Override;
use Sopheak\JwtAuth\DTO\OAuthClientData;
use Sopheak\JwtAuth\Services\OAuthClientRepository;
use Sopheak\JwtAuth\Tests\TestCase;

/** HTTP behaviour of the OAuth routes (RFC 6749 / 7009 / 7662). */
final class OAuthHttpTest extends TestCase
{
    #[Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('sp-jwt-auth.oauth_server.enabled', true);
    }

    /** @return array{0: string, 1: string} */
    private function client(string $name = 'M2M', array $scopes = ['invoices.read']): array
    {
        $result = app(OAuthClientRepository::class)->createClient(new OAuthClientData(
            name: $name,
            allowedGrants: ['client_credentials'],
            allowedScopes: $scopes,
        ));

        return [$result->client->id, (string) $result->plaintextSecret];
    }

    private function issue(string $id, string $secret): array
    {
        return $this->postJson('/oauth/token', [
            'grant_type' => 'client_credentials',
            'client_id' => $id,
            'client_secret' => $secret,
            'scope' => 'invoices.read',
        ])->assertOk()->json();
    }

    public function test_expires_in_matches_configured_ttl(): void
    {
        [$id, $secret] = $this->client();

        $expiresIn = $this->issue($id, $secret)['expires_in'];

        self::assertEqualsWithDelta(3600, $expiresIn, 2);
    }

    public function test_token_errors_follow_rfc_6749(): void
    {
        [$id, $secret] = $this->client();

        $this->postJson('/oauth/token', ['grant_type' => 'client_credentials', 'client_id' => $id, 'client_secret' => 'wrong'])
            ->assertStatus(401)->assertJsonPath('error', 'invalid_client')->assertHeader('WWW-Authenticate');

        $this->postJson('/oauth/token', ['grant_type' => 'password', 'client_id' => $id, 'client_secret' => $secret])
            ->assertStatus(400)->assertJsonPath('error', 'unsupported_grant_type');

        $this->postJson('/oauth/token', ['grant_type' => 'client_credentials', 'client_id' => $id, 'client_secret' => $secret, 'scope' => 'not.allowed'])
            ->assertStatus(400)->assertJsonPath('error', 'invalid_scope');
    }

    public function test_malformed_refresh_token_is_invalid_grant(): void
    {
        $result = app(OAuthClientRepository::class)->createClient(new OAuthClientData(
            name: 'Refresher',
            allowedGrants: ['refresh_token'],
        ));

        $this->postJson('/oauth/token', [
            'grant_type' => 'refresh_token',
            'client_id' => $result->client->id,
            'client_secret' => $result->plaintextSecret,
            'refresh_token' => 'abc',
        ])->assertStatus(400)->assertJsonPath('error', 'invalid_grant');
    }

    public function test_introspect_and_revoke_require_client_authentication(): void
    {
        [$id, $secret] = $this->client();
        $token = $this->issue($id, $secret)['access_token'];

        $this->postJson('/oauth/introspect', ['token' => $token])->assertStatus(401);
        $this->postJson('/oauth/revoke', ['token' => $token])->assertStatus(401);

        $this->postJson('/oauth/introspect', ['token' => $token, 'client_id' => $id, 'client_secret' => $secret])
            ->assertOk()
            ->assertJsonPath('active', true)
            ->assertJsonPath('client_id', $id)
            ->assertJsonPath('scope', 'invoices.read')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonMissingPath('clientId');
    }

    public function test_client_cannot_introspect_or_revoke_another_clients_token(): void
    {
        [$idA, $secretA] = $this->client('A');
        [$idB, $secretB] = $this->client('B');
        $tokenA = $this->issue($idA, $secretA)['access_token'];

        $this->postJson('/oauth/introspect', ['token' => $tokenA, 'client_id' => $idB, 'client_secret' => $secretB])
            ->assertOk()->assertExactJson(['active' => false]);

        $this->postJson('/oauth/revoke', ['token' => $tokenA, 'client_id' => $idB, 'client_secret' => $secretB])->assertOk();

        $this->postJson('/oauth/introspect', ['token' => $tokenA, 'client_id' => $idA, 'client_secret' => $secretA])
            ->assertOk()->assertJsonPath('active', true);

        $this->postJson('/oauth/revoke', ['token' => $tokenA, 'client_id' => $idA, 'client_secret' => $secretA])->assertOk();
        $this->postJson('/oauth/introspect', ['token' => $tokenA, 'client_id' => $idA, 'client_secret' => $secretA])
            ->assertOk()->assertExactJson(['active' => false]);
    }

    public function test_malformed_tokens_are_inactive_noop_and_unauthorized(): void
    {
        [$id, $secret] = $this->client();
        $auth = ['client_id' => $id, 'client_secret' => $secret];

        $this->postJson('/oauth/introspect', ['token' => 'abc'] + $auth)->assertOk()->assertExactJson(['active' => false]);
        $this->postJson('/oauth/revoke', ['token' => 'abc'] + $auth)->assertOk();

        Route::middleware('sp.oauth')->get('/oauth-protected-malformed', fn (): array => ['ok' => true]);
        $this->withToken('abc')->getJson('/oauth-protected-malformed')->assertUnauthorized();
    }

    public function test_legacy_responses_flag_restores_old_introspection_shape(): void
    {
        config()->set('sp-jwt-auth.oauth_server.legacy_responses', true);
        [$id, $secret] = $this->client();
        $token = $this->issue($id, $secret)['access_token'];

        $this->postJson('/oauth/introspect', ['token' => $token, 'client_id' => $id, 'client_secret' => $secret])
            ->assertOk()->assertJsonPath('clientId', $id);
    }
}
