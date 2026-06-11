---
title: "External Identity — Social Login"
description: "Add social login (Google, GitHub, etc.) using ExternalIdentity with Laravel Socialite."
---

# External Identity — Social Login

This tutorial uses Laravel Socialite as the adapter to integrate social login, with the package normalizing and storing external identities.

## 1. Install Socialite

```bash
composer require laravel/socialite
```

Add provider credentials to `config/services.php`:

```php
'google' => [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect' => env('GOOGLE_REDIRECT_URI'),
],
```

## 2. Implement ExternalIdentityProvider

```php
<?php

declare(strict_types=1);

namespace App\Adapters;

use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Sopheak\JwtAuth\Contracts\ExternalIdentityProvider;
use Sopheak\JwtAuth\DTO\ExternalIdentity;

final class SocialiteIdentityProvider implements ExternalIdentityProvider
{
    public function redirect(string $provider, array $options = []): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        return Socialite::driver($provider)
            ->stateless()
            ->redirect();
    }

    public function callback(string $provider, Request $request): ExternalIdentity
    {
        $socialUser = Socialite::driver($provider)->stateless()->user();

        return new ExternalIdentity(
            provider: $provider,
            providerUserId: $socialUser->getId(),
            email: $socialUser->getEmail(),
            emailVerified: true,
            name: $socialUser->getName(),
            avatar: $socialUser->getAvatar(),
            rawProfile: $socialUser->getRaw(),
            providerTokens: [
                'access_token' => $socialUser->token,
                'refresh_token' => $socialUser->refreshToken,
            ],
        );
    }
}
```

## 3. Bind the Provider

```php
// AppServiceProvider
use App\Adapters\SocialiteIdentityProvider;
use Sopheak\JwtAuth\Contracts\ExternalIdentityProvider;

$this->app->bind(ExternalIdentityProvider::class, SocialiteIdentityProvider::class);
```

## 4. Redirect Route

```php
use Sopheak\JwtAuth\Contracts\ExternalIdentityProvider;

Route::get('/auth/{provider}/redirect', function (string $provider) {
    return app(ExternalIdentityProvider::class)->redirect($provider);
});
```

## 5. Callback Route

```php
use Sopheak\JwtAuth\Contracts\ExternalIdentityProvider;
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Services\ExternalIdentityStore;
use Sopheak\JwtAuth\Services\JwtTokenService;
use Sopheak\JwtAuth\Support\TokenResponse;

Route::get('/auth/{provider}/callback', function (string $provider, Request $request) {
    // 1. Normalize the provider user
    $identity = app(ExternalIdentityProvider::class)->callback($provider, $request);

    // 2. Store or update the external identity record
    $record = app(ExternalIdentityStore::class)->store($identity);

    // 3. App-owned policy: find or create local user
    $user = null;

    if ($record->user_id !== null) {
        // Already linked — fetch the existing user
        $user = User::find($record->user_id);
    }

    if ($user === null && $identity->email !== null) {
        // Try to match by email
        $user = User::where('email', $identity->email)->first();
    }

    if ($user === null) {
        // Create a new local user
        $user = User::query()->create([
            'name' => $identity->name ?? $identity->providerUserId,
            'email' => $identity->email ?? $identity->providerUserId . '@' . $provider . '.example',
            'password' => Hash::make(Str::random(32)),
        ]);

        // Link the identity to the new user
        app(ExternalIdentityStore::class)->store($identity, $user);
    } elseif ($record->user_id === null) {
        // Link the identity to the matched user
        app(ExternalIdentityStore::class)->store($identity, $user);
    }

    // 4. Apply MFA, tenant, or business rules here
    // if ($user->mfa_enabled) { ... }

    // 5. Issue JWT tokens
    $pair = app(JwtTokenService::class)->issueTokenPair(
        $user,
        TokenContext::make()->scopes(['profile.read']),
    );

    return response()->json([
        'user' => $user->only('id', 'name', 'email'),
        'token' => TokenResponse::passportCompatible($pair),
    ]);
});
```

## 6. Enable Provider Token Storage

If your app needs the provider access/refresh tokens (e.g., to call Google APIs on behalf of the user):

```env
SP_JWT_EXTERNAL_IDENTITIES_STORE_PROVIDER_TOKENS=true
```

Provider tokens are stored in the `provider_tokens` JSON column. Enable encryption for sensitive tokens:

```env
SP_JWT_EXTERNAL_IDENTITIES_ENCRYPT_PROVIDER_TOKENS=true
```

## Events

The `ExternalIdentityResolved` event is fired every time `ExternalIdentityStore::store()` is called:

```php
use Sopheak\JwtAuth\Events\ExternalIdentityResolved;

// $event->identity (DTO)
// $event->record  (Model)
```

Listen for this event to trigger post-login actions (welcome email, analytics, tenant assignment).
