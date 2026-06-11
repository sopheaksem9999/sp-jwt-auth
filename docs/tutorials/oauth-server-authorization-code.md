---
title: "OAuth Server — Authorization Code + PKCE"
description: "Full authorization code flow with PKCE for third-party clients."
---

# OAuth Server — Authorization Code + PKCE

This tutorial walks through the end-to-end OAuth authorization code grant with PKCE, from creating a client to exchanging the authorization code for tokens.

## 1. Enable OAuth Server Mode

```env
SP_JWT_OAUTH_SERVER_ENABLED=true
```

Routes are registered under the `oauth` prefix by default.

## 2. Create a Confidential Client

```php
use Sopheak\JwtAuth\DTO\OAuthClientData;
use Sopheak\JwtAuth\Services\OAuthClientRepository;

$secret = app(OAuthClientRepository::class)->createClient(new OAuthClientData(
    name: 'ERP Connector',
    redirectUris: ['https://client.example.com/callback'],
    allowedGrants: ['authorization_code', 'refresh_token'],
    allowedScopes: ['invoices.read', 'invoices.write'],
    confidential: true,
));

// Return once — store immediately on the client
$plaintextSecret = $secret->plaintextSecret;
```

The client ID is on `$secret->client->id`. The secret is a 64-hex-char random string, returned only at creation.

## 3. Build the Authorization URL (Client)

The client redirects the user to:

```text
GET /oauth/authorize?response_type=code&client_id={client_id}&redirect_uri={redirect_uri}&scope=invoices.read&state={random_state}&code_challenge={challenge}&code_challenge_method=S256
```

- `code_challenge`: SHA-256 hash of a `code_verifier` (random 43–128 character string)
- `code_challenge_method`: `S256` (recommended) or `plain`
- `state`: Anti-CSRF token, verified on return

JavaScript PKCE helper:

```javascript
async function generatePkce() {
    const verifier = crypto.getRandomValues(new Uint8Array(64));
    const codeVerifier = btoa(String.fromCharCode(...verifier))
        .replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    const encoder = new TextEncoder();
    const hash = await crypto.subtle.digest('SHA-256', encoder.encode(codeVerifier));
    const codeChallenge = btoa(String.fromCharCode(...new Uint8Array(hash)))
        .replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    return { codeVerifier, codeChallenge };
}
```

## 4. Validate and Approve (Server)

The server-side authorization endpoint validates the incoming request:

```php
use Sopheak\JwtAuth\DTO\OAuthConsentContext;
use Sopheak\JwtAuth\Services\OAuthServerService;

// Validate the request
$authorization = app(OAuthServerService::class)->validateAuthorizationRequest($request);

// Show consent UI to the user
// $authorization->client->name
// $authorization->scopes

// If the user approves:
$code = app(OAuthServerService::class)->approveAuthorizationRequest(
    $authorization,
    $request->user(),
    new OAuthConsentContext(
        scopes: $authorization->scopes,
        claims: [],
        remember: true,
    ),
);

// Redirect back to the client with the code
return redirect()->to(
    $authorization->redirectUri .
    '?code=' . $code->code .
    '&state=' . $authorization->state,
);
```

The authorization code is one-time use and expires after `oauth_server.auth_code_ttl_minutes` (default 5 minutes).

## 5. Exchange the Code for Tokens (Client)

The client sends a POST request to the token endpoint:

```php
// POST /oauth/token
$response = Http::asForm()->post('https://api.example.com/oauth/token', [
    'grant_type' => 'authorization_code',
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'code' => $authorizationCode,
    'redirect_uri' => 'https://client.example.com/callback',
    'code_verifier' => $codeVerifier,
]);
```

On the server, this is handled automatically by `OAuthServerService::issueTokenFromRequest()`:

```php
$tokenData = app(OAuthServerService::class)->issueTokenFromRequest($request);

return $tokenData->toArray();
// {
//     "access_token": "...",
//     "token_type": "Bearer",
//     "expires_in": 3600,
//     "refresh_token": "...",
//     "scopes": ["invoices.read", "invoices.write"]
// }
```

## 6. Protect Resource Routes

```php
Route::middleware(['sp.oauth', 'sp.oauth.scope:invoices.read'])
    ->get('/partner/invoices', PartnerInvoiceController::class);
```

## 7. Access the Principal

```php
$principal = $request->attributes->get('sp_oauth_principal');

$principal->clientId;    // UUID of the client
$principal->userId;      // User ID who approved
$principal->grantType;   // 'authorization_code'
$principal->scopes;      // ['invoices.read', 'invoices.write']
$principal->can('invoices.read'); // true / false
```

## 8. Refresh Tokens

```php
// POST /oauth/token
$response = Http::asForm()->post('https://api.example.com/oauth/token', [
    'grant_type' => 'refresh_token',
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    'refresh_token' => $existingRefreshToken,
]);
```

Refresh tokens rotate on each use. The old refresh token is revoked and a new one is issued.

## 9. Revoke Tokens

```php
app(OAuthServerService::class)->revokeToken(
    token: $accessToken,
    hint: 'access_token', // or 'refresh_token'
);
```

## Events

| Event | Fired |
|---|---|
| `OAuthAuthorizationApproved` | After user approves consent |
| `OAuthTokenIssued` | After access token is created |
| `OAuthTokenRevoked` | After token is revoked |
