---
title: "API Reference"
description: "Key classes, contracts, DTOs, middleware, and events in sopheak/sp-jwt-auth."
---

# API Reference

## Service Provider

| Class | Description |
|---|---|
| `CoreSpJwtAuthServiceProvider` | Registers the `sp-jwt` guard, middleware aliases, commands, migrations, routes, and container bindings |

## Services (Main Entry Points)

| Service | Description |
|---|---|
| `JwtTokenService` | Issue, validate, rotate, and revoke first-party JWT token pairs |
| `MfaChallengeBroker` | Create MFA challenges that store pending token context |
| `OtpChallengeBroker` | Create hashed OTP codes, verify, check expiry and lockout |
| `EmailVerificationBroker` | Create and verify one-time email verification tokens |
| `PasswordResetBroker` | Create, verify, and consume one-time password reset tokens |
| `ApiKeyService` | Create, validate, rotate, and revoke scoped API keys |
| `ExternalIdentityStore` | Store normalized provider identity records |
| `OAuthServerService` | Authorization requests, token grants, revocation, introspection |
| `OAuthClientRepository` | Register and manage OAuth clients |
| `OAuthConsentRepository` | Store and revoke user consents |
| `OAuthScopeRepository` | Validate allowed OAuth scopes |

## Contracts (Bind in Container for App-Owned Delivery)

| Contract | Bind when using |
|---|---|
| `OtpChannelSender` | OTP delivery (email, SMS, etc.) |
| `EmailVerificationSender` | Email verification notification delivery |
| `PasswordResetSender` | Password reset notification delivery |
| `ExternalIdentityProvider` | Custom Socialite/OIDC provider adapter |
| `TokenContextValidator` | Pre-issue token context validation hook |

## Key DTOs

| DTO | Purpose |
|---|---|
| `TokenContext` | Scopes, claims, subject, device, session for token issue |
| `TokenSubject` | Type/id pair embedded in tokens (e.g. tenant, user) |
| `TokenPair` | Access + refresh token response |
| `OtpDestination` | Channel, normalized destination, masked destination |
| `ApiKeyContext` | Owner, scopes, claims, IPs for API key creation |
| `ApiKeyPrincipal` | Authenticated API key identity |
| `ExternalIdentity` | Normalized provider profile data |
| `OAuthClientData` | Client name, redirect URIs, allowed grants, scopes |
| `OAuthAuthorizationRequest` | Validated incoming authorization request |
| `OAuthConsentContext` | User-approved scopes and remember flag |

## Guards and Middleware

| Middleware | Alias | Purpose |
|---|---|---|
| `AuthenticateJwt` | `sp.jwt` | Authenticate bearer JWT via the configured guard |
| `RequireJwtScope` | `sp.jwt.scope` | Require every listed JWT scope |
| `RequireAnyJwtScope` | `sp.jwt.any_scope` | Require any listed JWT scope |
| `AuthenticateApiKey` | `sp.api_key` | Authenticate API key bearer token |
| `RequireApiKeyScope` | `sp.api_key.scope` | Require every listed API key scope |
| `RequireAnyApiKeyScope` | `sp.api_key.any_scope` | Require any listed API key scope |
| `AuthenticateOAuthToken` | `sp.oauth` | Authenticate OAuth resource token |
| `RequireOAuthScope` | `sp.oauth.scope` | Require every listed OAuth scope |
| `RequireAnyOAuthScope` | `sp.oauth.any_scope` | Require any listed OAuth scope |
| `RequireOAuthClient` | `sp.oauth.client` | Restrict by client id |

## Artisan Commands

| Command | Description |
|---|---|
| `sp-jwt-auth:install` | Publish config, migrations, and optionally generate keys |
| `sp-jwt-auth:setup` | Publish client scaffolding, patch the API guard when safe, and optionally generate keys |
| `sp-jwt-auth:validate` | Validate client app guard, provider, key, hash key, and JWKS setup |
| `sp-jwt-auth:keys` | Generate, rotate, retire, or revoke signing keys |
| `sp-jwt-auth:jwks` | Print or export JWKS public key payload |
| `sp-jwt-auth:prune` | Delete expired or revoked token rows |

## Traits

| Trait | Model Method |
|---|---|
| `HasJwtTokens` | `$user->token()`, `$user->tokenCan('scope')` |

## Support

| Class | Description |
|---|---|
| `TokenResponse` | `passportCompatible()` helper for Laravel Passport-shaped responses |
| `HookRegistry` | Register pre-issue validation, pre-issue mutation, and post-issue hooks |
| `SpJwtAuth` | Package config and key repository accessor facade |
| `SecretHasher` | HMAC hash/verify for opaque token secrets |
| `HashKeyRepository` | Manage active and previous HMAC hash keys |
| `ConfigSigningKeyRepository` | Load signing keys from package config |
| `JwksFormatter` | Build JWKS payload from active and previous signing keys |

## Events (33 total)

| Group | Events |
|---|---|
| **Core JWT** | `TokenIssued`, `TokenRefreshed`, `TokenRevoked`, `SessionRevoked`, `AllUserTokensRevoked`, `RefreshTokenReuseDetected` |
| **Account Security** | `MfaChallengeCreated`, `MfaChallengeCompleted`, `OtpCodeCreated`, `OtpCodeSent`, `OtpCodeResent`, `OtpCodeVerified`, `OtpCodeFailed`, `OtpCodeLocked`, `OtpCodeExpired`, `EmailVerificationTokenCreated`, `EmailVerificationSent`, `EmailVerified`, `PasswordResetTokenCreated`, `PasswordResetSent`, `PasswordResetTokenConsumed` |
| **API Keys** | `ApiKeyCreated`, `ApiKeyUsed`, `ApiKeyRevoked`, `ApiKeyRotated` |
| **External Identity** | `ExternalIdentityResolved` |
| **OAuth Server** | `OAuthClientCreated`, `OAuthClientSecretRotated`, `OAuthClientRevoked`, `OAuthAuthorizationApproved`, `OAuthAuthorizationDenied`, `OAuthTokenIssued`, `OAuthTokenRevoked`, `OAuthConsentRevoked` |
