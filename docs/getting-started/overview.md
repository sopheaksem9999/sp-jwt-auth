---
title: "Overview"
description: "Architecture and design of sopheak/sp-jwt-auth."
---

# Overview

`sopheak/sp-jwt-auth` is a Laravel package for first-party API authentication. It integrates with Laravel's auth manager through a custom `sp-jwt` guard while leaving the application's `web` guard and product-specific login flows untouched.

## Core Flow

1. The application validates credentials and resolves an `Authenticatable` user.
2. The application builds a `TokenContext` with scopes, claims, subject, device, and session metadata.
3. `JwtTokenService::issueTokenPair()` persists an access token row, signs a JWT with a configured `kid`, persists a hashed refresh token row, and returns the plaintext token pair.
4. API routes protected by `auth:api` use `JwtGuard` to validate the bearer JWT through `JwtTokenService`.
5. The guard resolves the user through Laravel's configured user provider and attaches the persisted token record.
6. Refresh calls use `JwtTokenService::rotateRefreshToken()` to revoke the old token family member, issue a new pair, and link the rotation chain.

## Package Modules

| Module | Status |
|---|---|
| Core JWT (guard, tokens, refresh, revocation) | Default |
| Account Security (MFA, OTP, email verification, password reset) | Optional |
| API Keys (scoped integration keys) | Optional |
| External Identity (Socialite/OIDC normalization) | Optional |
| OAuth Server (third-party OAuth clients) | Optional |

## What the Package Does Not Own

- Password validation, hashing, or persistence
- User registration or tenant selection
- MFA policy or delivery providers
- Account linking decisions
- Response shape or error formatting
