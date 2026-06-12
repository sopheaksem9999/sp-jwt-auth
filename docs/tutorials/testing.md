---
title: "Testing"
description: "Write PHPUnit tests for auth endpoints using sopheak/sp-jwt-auth."
---

# Testing

These examples use Orchestra Testbench, matching the package's own test setup.

## Test Case Setup

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Services\JwtTokenService;
use Tests\TestCase;

final class AuthTest extends TestCase
{
    private User $user;
    private JwtTokenService $jwt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('secret123'),
        ]);

        $this->jwt = app(JwtTokenService::class);
    }
}
```

## Issue a Token in Tests

```php
public function test_protected_route_with_token(): void
{
    $pair = $this->jwt->issueTokenPair(
        $this->user,
        TokenContext::make()->scopes(['profile.read']),
    );

    $response = $this->withToken($pair->accessToken)
        ->getJson('/api/me');

    $response->assertOk();
}
```

Claims and tenant/company context work the same way in tests:

```php
public function test_company_route_with_token_claims(): void
{
    $pair = $this->jwt->issueTokenPair(
        $this->user,
        TokenContext::make()
            ->subject('company', '42')
            ->scopes(['invoices.read'])
            ->claims(['company_id' => 42]),
    );

    $response = $this->withToken($pair->accessToken)
        ->getJson('/api/invoices');

    $response->assertOk();
}
```

`JwtTokenService::issueTokenPair()` persists access and refresh token rows, so tests still need the package token tables. A dedicated `JwtTokenTestHelper` is not part of the current API; use package migrations plus `issueTokenPair()` for now.

## Test Login Endpoint

```php
public function test_login_success(): void
{
    $response = $this->postJson('/api/auth/login', [
        'email' => 'test@example.com',
        'password' => 'secret123',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'user' => ['id', 'name', 'email'],
            'token' => ['token_type', 'expires_in', 'access_token', 'refresh_token'],
        ]);
}

public function test_login_with_invalid_credentials(): void
{
    $response = $this->postJson('/api/auth/login', [
        'email' => 'test@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertUnprocessable();
}
```

## Test Token Refresh

```php
public function test_refresh_token_rotation(): void
{
    $pair = $this->jwt->issueTokenPair(
        $this->user,
        TokenContext::make(),
    );

    $response = $this->postJson('/api/auth/refresh', [
        'refresh_token' => $pair->refreshToken,
    ]);

    $response->assertOk();

    // Old refresh token is now invalid
    $response = $this->postJson('/api/auth/refresh', [
        'refresh_token' => $pair->refreshToken,
    ]);

    $response->assertStatus(422); // reuse detected
}
```

## Test Scope Enforcement

```php
public function test_route_requires_scope(): void
{
    $pair = $this->jwt->issueTokenPair(
        $this->user,
        TokenContext::make()->scopes(['profile.read']),
    );

    // Route requires invoices.write
    $response = $this->withToken($pair->accessToken)
        ->getJson('/api/invoices');

    $response->assertForbidden();
}
```

## Test Logout

```php
public function test_logout_revokes_token(): void
{
    $pair = $this->jwt->issueTokenPair(
        $this->user,
        TokenContext::make(),
    );

    $response = $this->withToken($pair->accessToken)
        ->postJson('/api/auth/logout');

    $response->assertOk();

    // Token is now revoked
    $response = $this->withToken($pair->accessToken)
        ->getJson('/api/me');

    $response->assertUnauthorized();
}
```

## Test Password Reset

```php
public function test_password_reset_flow(): void
{
    $dispatch = app(PasswordResetBroker::class)->createResetToken(
        $this->user,
        $this->user->email,
    );

    $response = $this->postJson('/api/auth/reset-password', [
        'token' => $dispatch->plaintextToken,
        'email' => $this->user->email,
        'password' => 'new-secret-456',
        'password_confirmation' => 'new-secret-456',
    ]);

    $response->assertOk();

    // Old password no longer works
    $response = $this->postJson('/api/auth/login', [
        'email' => $this->user->email,
        'password' => 'secret123',
    ]);
    $response->assertUnprocessable();
}
```

## Test Helpers

Create a trait to reuse token issuance across test cases:

```php
<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Services\JwtTokenService;

trait WithAccessToken
{
    protected function actAs(Authenticatable $user, array $scopes = ['*']): self
    {
        $pair = app(JwtTokenService::class)->issueTokenPair(
            $user,
            TokenContext::make()->scopes($scopes),
        );

        return $this->withToken($pair->accessToken);
    }
}
```

```php
// In your test
public function test_something(): void
{
    $this->actAs($this->user, ['profile.read'])
        ->getJson('/api/me')
        ->assertOk();
}
```
