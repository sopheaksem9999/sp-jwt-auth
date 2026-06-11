---
title: "Auth Controller Example"
description: "Complete auth controller using sopheak/sp-jwt-auth with register, login, password reset, logout, and profile."
---

# Auth Controller Example

This example shows a typical Laravel auth controller using the package services. The app owns password hashing, user creation, response shape, and notification delivery. The package owns token infrastructure, refresh rotation, and security brokers.

## Controller

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Services\JwtTokenService;
use Sopheak\JwtAuth\Services\PasswordResetBroker;
use Sopheak\JwtAuth\Support\TokenResponse;

final class AuthController
{
    public function __construct(
        private readonly JwtTokenService $jwt,
        private readonly PasswordResetBroker $passwordReset,
    ) {
    }

    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $pair = $this->jwt->issueTokenPair(
            $user,
            TokenContext::make()->scopes(['profile.read']),
        );

        return response()->json([
            'user' => $user->only('id', 'name', 'email'),
            'token' => TokenResponse::passportCompatible($pair),
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $pair = $this->jwt->issueTokenPair(
            $user,
            TokenContext::make()
                ->subject('tenant', (string) ($user->tenant_id ?? '0'))
                ->scopes(['profile.read', 'invoices.read'])
                ->claims(['tenant_id' => $user->tenant_id ?? 0]),
        );

        return response()->json([
            'user' => $user->only('id', 'name', 'email'),
            'token' => TokenResponse::passportCompatible($pair),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'token' => [
                'scopes' => $request->user()->token()?->scopes,
                'expires_at' => $request->user()->token()?->expires_at,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->token();

        if ($token !== null) {
            $this->jwt->revokeAccessToken($token->id);
        }

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function refresh(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'refresh_token' => 'required|string',
        ]);

        $pair = $this->jwt->rotateRefreshToken($validated['refresh_token']);

        return response()->json([
            'token' => TokenResponse::passportCompatible($pair),
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::query()->where('email', $validated['email'])->first();

        $dispatch = $this->passwordReset->createResetToken(
            $user,
            $validated['email'],
            ['ip' => $request->ip()],
        );

        // App-owned delivery — send via notification, email, SMS, etc.
        // $user->notulate(new ResetPasswordNotification($dispatch->plaintextToken));

        return response()->json([
            'message' => 'If the email exists, a reset link has been sent.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $result = $this->passwordReset->consumeResetToken($validated['token']);

        // App-owned password update
        $result->user->forceFill([
            'password' => Hash::make($validated['password']),
        ])->save();

        // Revoke all existing sessions so old tokens are invalidated
        $this->jwt->revokeAllForUser($result->user);

        return response()->json(['message' => 'Password reset successfully.']);
    }
}
```

## Routes

```php
<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
Route::post('/auth/refresh', [AuthController::class, 'refresh']);

Route::middleware('auth:api')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
});
```

## Sender Contracts

The package emits reset tokens via `PasswordResetSender`. Bind your notification sender in a service provider:

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use App\Notifications\PasswordResetNotification;
use Illuminate\Support\ServiceProvider;
use Sopheak\JwtAuth\Contracts\PasswordResetSender;
use Sopheak\JwtAuth\DTO\PasswordResetDispatch;

final class AppServiceProvider extends ServiceProvider
{
    public array $singletons = [
        PasswordResetSender::class => function (): PasswordResetSender {
            return new class implements PasswordResetSender
            {
                public function send(PasswordResetDispatch $dispatch): void
                {
                    $dispatch->user->notulate(new PasswordResetNotification(
                        $dispatch->plaintextToken,
                    ));
                }
            };
        },
    ];
}
```
