---
title: "Events and Hooks"
description: "Lifecycle events and extension hooks emitted by sopheak/sp-jwt-auth."
---

# Events and Hooks

Applications can listen to package events for audit logging, metrics, notifications, or policy side effects.

## Core Token Events

- `TokenIssued`
- `TokenRefreshed`
- `TokenRevoked`
- `SessionRevoked`
- `AllUserTokensRevoked`
- `RefreshTokenReuseDetected`

## Account Security Events

- `MfaChallengeCreated`
- `MfaChallengeCompleted`
- `OtpCodeCreated`
- `OtpCodeSent`
- `OtpCodeResent`
- `OtpCodeVerified`
- `OtpCodeFailed`
- `OtpCodeLocked`
- `OtpCodeExpired`
- `EmailVerificationTokenCreated`
- `EmailVerificationSent`
- `EmailVerified`
- `PasswordResetTokenCreated`
- `PasswordResetSent`
- `PasswordResetTokenConsumed`

## API Key Events

- `ApiKeyCreated`
- `ApiKeyUsed`
- `ApiKeyRevoked`
- `ApiKeyRotated`

## External Identity Events

- `ExternalIdentityResolved`

## OAuth Events

- `OAuthClientCreated`
- `OAuthClientSecretRotated`
- `OAuthClientRevoked`
- `OAuthAuthorizationApproved`
- `OAuthAuthorizationDenied`
- `OAuthTokenIssued`
- `OAuthTokenRevoked`
- `OAuthConsentRevoked`

## Token Hooks

`HookRegistry` supports package-level token extension points:

- Validate token context before issue.
- Mutate token context before issue.
- Run side effects after token issue.

Use hooks for product-specific policy that should stay outside the package, such as tenant checks, role-to-scope mapping, audit correlation, or device policy.
