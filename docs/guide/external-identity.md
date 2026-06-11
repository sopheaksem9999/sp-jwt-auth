---
title: "External Identity"
description: "Normalize and store Socialite/OIDC-style external identities."
---

# External Identity

External identity support gives applications a common shape for Socialite, OIDC, and other provider profiles.

The package does not decide whether a local user should be created, linked, or denied. The app owns that policy.

## ExternalIdentity DTO

```php
use Sopheak\JwtAuth\DTO\ExternalIdentity;

$identity = new ExternalIdentity(
    provider: 'google',
    providerUserId: 'google-123',
    email: 'user@example.com',
    emailVerified: true,
    name: 'Test User',
    avatar: 'https://example.test/avatar.png',
    rawProfile: ['locale' => 'en'],
    providerTokens: ['access_token' => 'provider-token'],
);
```

## Store or Link an Identity

```php
use Sopheak\JwtAuth\Services\ExternalIdentityStore;

$record = app(ExternalIdentityStore::class)->store($identity, $user);
```

By default, provider tokens are not stored. Enable `external_identities.store_provider_tokens` only when the app really needs them.

## Provider Contract

Adapters can implement `ExternalIdentityProvider`:

```php
use Sopheak\JwtAuth\Contracts\ExternalIdentityProvider;

final class SocialiteIdentityProvider implements ExternalIdentityProvider
{
    // redirect(string $provider, array $options = [])
    // callback(string $provider, Request $request)
}
```

## Typical App Flow

1. App redirects the browser/client to the provider.
2. Provider redirects back to the app callback.
3. Adapter normalizes the provider user into `ExternalIdentity`.
4. App links or creates the local user.
5. App applies MFA, tenant, and business rules.
6. App calls `JwtTokenService::issueTokenPair()`.
