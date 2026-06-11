---
title: "Tutorials"
description: "End-to-end tutorial examples for sopheak/sp-jwt-auth."
---

# Tutorials

Step-by-step examples for common auth patterns using sopheak/sp-jwt-auth.

- [Auth Controller Example](./auth-controller) — full controller with register, login, logout, refresh, password reset
- [Login with MFA](./login-with-mfa) — OTP challenge flow with token issuance after verification
- [Email Verification During Registration](./email-verification-registration) — verify before issuing tokens
- [API Key Client Usage](./api-key-client-usage) — create and use scoped API keys
- [OAuth Server — Authorization Code + PKCE](./oauth-server-authorization-code) — full OAuth authorization code flow for third-party apps
- [OAuth Server — Client Credentials](./oauth-server-client-credentials) — machine-to-machine OAuth grant for service integrations
- [External Identity — Social Login](./external-identity-social-login) — social login with Google/GitHub using ExternalIdentity + Socialite
- [Testing](./testing) — PHPUnit tests for auth endpoints
- [SPA and Mobile Integration](./spa-mobile-integration) — token storage, refresh, logout for first-party clients
- [Migration from Sanctum or Passport](./migration-from-sanctum-passport) — switch existing apps to sp-jwt-auth
- [Tenant Isolation](./tenant-isolation) — multi-tenant auth with subject and claims
