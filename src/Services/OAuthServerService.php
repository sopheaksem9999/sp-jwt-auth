<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Services;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Sopheak\JwtAuth\DTO\OAuthAuthorizationCode as OAuthAuthorizationCodeDto;
use Sopheak\JwtAuth\DTO\OAuthAuthorizationRequest;
use Sopheak\JwtAuth\DTO\OAuthConsentContext;
use Sopheak\JwtAuth\DTO\OAuthIntrospectionPayload;
use Sopheak\JwtAuth\DTO\OAuthPrincipal;
use Sopheak\JwtAuth\DTO\OAuthTokenResponse;
use Sopheak\JwtAuth\Events\OAuthAuthorizationApproved;
use Sopheak\JwtAuth\Events\OAuthTokenIssued;
use Sopheak\JwtAuth\Events\OAuthTokenRevoked;
use Sopheak\JwtAuth\Models\OAuthAccessToken;
use Sopheak\JwtAuth\Models\OAuthAuthorizationCode;
use Sopheak\JwtAuth\Models\OAuthClient;
use Sopheak\JwtAuth\Models\OAuthRefreshToken;
use Sopheak\JwtAuth\Security\SecretHasher;

final readonly class OAuthServerService
{
    public function __construct(
        private OAuthClientRepository $clients,
        private OAuthConsentRepository $consents,
        private OAuthScopeRepository $scopes,
        private SecretHasher $hasher,
    ) {
    }

    public function validateAuthorizationRequest(Request $request): OAuthAuthorizationRequest
    {
        if ($request->query('response_type') !== 'code') {
            throw new InvalidArgumentException('Only authorization-code response type is supported.');
        }

        $client = $this->clients->findActiveClient((string) $request->query('client_id'));

        if (! $client instanceof OAuthClient || ! $client->allowsGrant('authorization_code')) {
            throw new AuthenticationException('OAuth client is invalid.');
        }

        $redirectUri = (string) $request->query('redirect_uri');

        if (! $client->allowsRedirectUri($redirectUri)) {
            throw new InvalidArgumentException('OAuth redirect URI is invalid.');
        }

        $codeChallenge = $request->query('code_challenge');
        $codeChallengeMethod = $request->query('code_challenge_method', $codeChallenge !== null ? 'plain' : null);

        if (! $client->confidential && (bool) config('sp-jwt-auth.oauth_server.require_pkce_for_public_clients', true) && ! is_string($codeChallenge)) {
            throw new InvalidArgumentException('Public OAuth clients must use PKCE.');
        }

        if ($codeChallengeMethod !== null && ! in_array($codeChallengeMethod, ['plain', 'S256'], true)) {
            throw new InvalidArgumentException('OAuth PKCE code challenge method is invalid.');
        }

        return new OAuthAuthorizationRequest(
            client: $client,
            redirectUri: $redirectUri,
            scopes: $this->scopes->validateForClient($client, $this->scopes->parse($request->query('scope'))),
            state: is_string($request->query('state')) ? $request->query('state') : null,
            codeChallenge: is_string($codeChallenge) ? $codeChallenge : null,
            codeChallengeMethod: is_string($codeChallengeMethod) ? $codeChallengeMethod : null,
        );
    }

    public function approveAuthorizationRequest(
        OAuthAuthorizationRequest $request,
        Authenticatable $user,
        OAuthConsentContext $context,
    ): OAuthAuthorizationCodeDto {
        $scopes = $this->scopes->validateForClient($request->client, $context->scopes ?: $request->scopes);
        $code = bin2hex(random_bytes(32));
        $hash = $this->hasher->hash($code);
        $expiresAt = now()->addMinutes((int) config('sp-jwt-auth.oauth_server.auth_code_ttl_minutes', 10));

        $record = OAuthAuthorizationCode::query()->create([
            'id' => (string) Str::uuid(),
            'client_id' => $request->client->id,
            'user_type' => $user::class,
            'user_id' => (string) $user->getAuthIdentifier(),
            'code_hash' => $hash['hash'],
            'hash_key_id' => $hash['hash_key_id'],
            'redirect_uri' => $request->redirectUri,
            'scopes' => $scopes,
            'claims' => $context->claims,
            'code_challenge' => $request->codeChallenge,
            'code_challenge_method' => $request->codeChallengeMethod,
            'expires_at' => $expiresAt,
        ]);

        if ($context->remember) {
            $this->consents->rememberConsent($user, $request->client, $scopes);
        }

        Event::dispatch(new OAuthAuthorizationApproved($request, $user, $record));

        return new OAuthAuthorizationCodeDto($code, $request->redirectUri, $expiresAt, $record);
    }

    public function issueTokenFromRequest(Request $request): OAuthTokenResponse
    {
        return match ((string) $request->request->get('grant_type')) {
            'authorization_code' => $this->issueAuthorizationCodeToken($request),
            'client_credentials' => $this->issueClientCredentialsToken($request),
            'refresh_token' => $this->issueRefreshToken($request),
            default => throw new InvalidArgumentException('OAuth grant type is unsupported.'),
        };
    }

    public function revokeToken(string $token, ?string $hint = null): void
    {
        if ($hint === 'refresh_token') {
            OAuthRefreshToken::query()->where('public_id', $this->publicId($token))->update(['revoked_at' => now()]);
        } else {
            OAuthAccessToken::query()->where('public_id', $this->publicId($token))->update(['revoked_at' => now()]);
            OAuthRefreshToken::query()->where('public_id', $this->publicId($token))->update(['revoked_at' => now()]);
        }

        Event::dispatch(new OAuthTokenRevoked($token));
    }

    public function introspect(string $token): OAuthIntrospectionPayload
    {
        try {
            $principal = $this->validateResourceToken($token);

            return new OAuthIntrospectionPayload(
                active: true,
                clientId: $principal->clientId,
                userType: $principal->userType,
                userId: $principal->userId,
                grantType: $principal->grantType,
                scopes: $principal->scopes,
                claims: $principal->claims,
                tokenId: $principal->tokenId,
                expiresAt: $principal->expiresAt,
            );
        } catch (AuthenticationException) {
            return new OAuthIntrospectionPayload(active: false);
        }
    }

    public function validateResourceToken(string $token): OAuthPrincipal
    {
        [$publicId, $secret] = $this->parseToken($token);
        $row = OAuthAccessToken::query()->where('public_id', $publicId)->first();

        if (! $row instanceof OAuthAccessToken
            || $row->revoked_at !== null
            || $row->expires_at->isPast()
            || ! $this->hasher->verify($secret, $row->secret_hash, $row->hash_key_id)
        ) {
            throw new AuthenticationException('OAuth token is invalid.');
        }

        $client = $this->clients->findActiveClient($row->client_id);

        if (! $client instanceof OAuthClient) {
            throw new AuthenticationException('OAuth token is invalid.');
        }

        $row->forceFill(['last_used_at' => now()])->save();

        return new OAuthPrincipal(
            clientId: $row->client_id,
            userType: $row->user_type,
            userId: $row->user_id,
            grantType: $row->grant_type,
            scopes: $row->scopes ?? [],
            claims: $row->claims ?? [],
            tokenId: $row->id,
            expiresAt: $row->expires_at,
        );
    }

    private function issueAuthorizationCodeToken(Request $request): OAuthTokenResponse
    {
        return DB::transaction(function () use ($request): OAuthTokenResponse {
            $client = $this->authenticatedClient($request, 'authorization_code');
            $code = (string) $request->request->get('code');
            $redirectUri = (string) $request->request->get('redirect_uri');

            foreach (OAuthAuthorizationCode::query()->where('client_id', $client->id)->whereNull('revoked_at')->lockForUpdate()->get() as $row) {
                if (! $row instanceof OAuthAuthorizationCode) {
                    continue;
                }

                if (! $this->hasher->verify($code, $row->code_hash, $row->hash_key_id)) {
                    continue;
                }

                if ($row->expires_at->isPast() || $row->redirect_uri !== $redirectUri || ! $this->validPkce($row, (string) $request->request->get('code_verifier'))) {
                    throw new AuthenticationException('OAuth authorization code is invalid.');
                }

                $row->forceFill(['revoked_at' => now()])->save();

                return $this->issueTokenPair($client, 'authorization_code', $row->scopes ?? [], $row->claims ?? [], $row->user_type, $row->user_id);
            }

            throw new AuthenticationException('OAuth authorization code is invalid.');
        });
    }

    private function issueClientCredentialsToken(Request $request): OAuthTokenResponse
    {
        $client = $this->authenticatedClient($request, 'client_credentials');
        $scopes = $this->scopes->validateForClient($client, $this->scopes->parse($request->request->get('scope')));

        return $this->issueAccessToken($client, 'client_credentials', $scopes, []);
    }

    private function issueRefreshToken(Request $request): OAuthTokenResponse
    {
        return DB::transaction(function () use ($request): OAuthTokenResponse {
            $client = $this->authenticatedClient($request, 'refresh_token');
            [$publicId, $secret] = $this->parseToken((string) $request->request->get('refresh_token'));
            $row = OAuthRefreshToken::query()
                ->where('client_id', $client->id)
                ->where('public_id', $publicId)
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->first();

            if (! $row instanceof OAuthRefreshToken
                || $row->expires_at->isPast()
                || ! $this->hasher->verify($secret, $row->secret_hash, $row->hash_key_id)
            ) {
                throw new AuthenticationException('OAuth refresh token is invalid.');
            }

            $row->forceFill(['revoked_at' => now()])->save();

            return $this->issueTokenPair($client, 'refresh_token', $row->scopes ?? [], $row->claims ?? [], $row->user_type, $row->user_id);
        });
    }

    private function authenticatedClient(Request $request, string $grant): OAuthClient
    {
        [$clientId, $clientSecret] = $this->clientCredentials($request);
        $client = $this->clients->findActiveClient($clientId);

        if (! $client instanceof OAuthClient || ! $client->allowsGrant($grant) || ! $this->clients->validateSecret($client, $clientSecret)) {
            throw new AuthenticationException('OAuth client is invalid.');
        }

        return $client;
    }

    /**
     * @return array<int, string|null>
     */
    private function clientCredentials(Request $request): array
    {
        $clientId = (string) $request->request->get('client_id', '');
        $clientSecret = $request->request->get('client_secret');

        if ($request->getUser() !== null) {
            $clientId = $request->getUser();
            $clientSecret = $request->getPassword();
        }

        if ($request->query->has('client_secret')) {
            throw new AuthenticationException('OAuth client secret is not accepted in the query string.');
        }

        return [$clientId, is_string($clientSecret) ? $clientSecret : null];
    }

    private function issueTokenPair(OAuthClient $client, string $grant, array $scopes, array $claims, ?string $userType, ?string $userId): OAuthTokenResponse
    {
        $access = $this->createAccessToken($client, $grant, $scopes, $claims, $userType, $userId);
        $refresh = null;

        if ($client->allowsGrant('refresh_token')) {
            $refresh = $this->createRefreshToken($access['record'], $client, $scopes, $claims, $userType, $userId);
        }

        return new OAuthTokenResponse(
            accessToken: $access['plaintext'],
            tokenType: 'Bearer',
            expiresIn: max(0, $access['record']->expires_at->diffInSeconds(now())),
            refreshToken: $refresh,
            scopes: $scopes,
        );
    }

    private function issueAccessToken(OAuthClient $client, string $grant, array $scopes, array $claims, ?string $userType = null, ?string $userId = null): OAuthTokenResponse
    {
        $access = $this->createAccessToken($client, $grant, $scopes, $claims, $userType, $userId);

        return new OAuthTokenResponse(
            accessToken: $access['plaintext'],
            tokenType: 'Bearer',
            expiresIn: max(0, $access['record']->expires_at->diffInSeconds(now())),
            scopes: $scopes,
        );
    }

    /** @return array{plaintext: string, record: OAuthAccessToken} */
    private function createAccessToken(OAuthClient $client, string $grant, array $scopes, array $claims, ?string $userType, ?string $userId): array
    {
        [$publicId, $secret, $plaintext] = $this->makeOpaqueToken('spoat');
        $hash = $this->hasher->hash($secret);
        $record = OAuthAccessToken::query()->create([
            'id' => (string) Str::uuid(),
            'client_id' => $client->id,
            'user_type' => $userType,
            'user_id' => $userId,
            'grant_type' => $grant,
            'public_id' => $publicId,
            'secret_hash' => $hash['hash'],
            'hash_key_id' => $hash['hash_key_id'],
            'scopes' => array_values($scopes),
            'claims' => $claims,
            'expires_at' => now()->addMinutes((int) config('sp-jwt-auth.oauth_server.access_token_ttl_minutes', 60)),
        ]);

        Event::dispatch(new OAuthTokenIssued($record));

        return ['plaintext' => $plaintext, 'record' => $record];
    }

    private function createRefreshToken(OAuthAccessToken $access, OAuthClient $client, array $scopes, array $claims, ?string $userType, ?string $userId): string
    {
        [$publicId, $secret, $plaintext] = $this->makeOpaqueToken('sport');
        $hash = $this->hasher->hash($secret);
        OAuthRefreshToken::query()->create([
            'id' => (string) Str::uuid(),
            'access_token_id' => $access->id,
            'client_id' => $client->id,
            'user_type' => $userType,
            'user_id' => $userId,
            'public_id' => $publicId,
            'secret_hash' => $hash['hash'],
            'hash_key_id' => $hash['hash_key_id'],
            'scopes' => array_values($scopes),
            'claims' => $claims,
            'expires_at' => now()->addDays((int) config('sp-jwt-auth.oauth_server.refresh_token_ttl_days', 30)),
        ]);

        return $plaintext;
    }

    private function validPkce(OAuthAuthorizationCode $code, string $verifier): bool
    {
        if ($code->code_challenge === null) {
            return true;
        }

        if ($verifier === '') {
            return false;
        }

        $actual = $code->code_challenge_method === 'S256'
            ? rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=')
            : $verifier;

        return hash_equals($code->code_challenge, $actual);
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function makeOpaqueToken(string $prefix): array
    {
        $publicId = $prefix . '_' . Str::lower(Str::random(24));
        $secret = bin2hex(random_bytes(32));

        return [$publicId, $secret, $publicId . '.' . $secret];
    }

    private function publicId(string $token): string
    {
        return $this->parseToken($token)[0];
    }

    /** @return array{0: string, 1: string} */
    private function parseToken(string $token): array
    {
        $parts = explode('.', $token, 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw new RuntimeException('OAuth token is malformed.');
        }

        return [$parts[0], $parts[1]];
    }
}
