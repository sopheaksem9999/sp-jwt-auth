# Project Context

## TL;DR
- Product: Laravel JWT authentication package.
- Package: `sopheak/sp-jwt-auth`.
- Stack: PHP 8.3+, Laravel 12/13, `firebase/php-jwt`, Orchestra Testbench, PHPUnit.
- Goal: Modular Laravel authentication infrastructure with first-party JWT, account security brokers, API keys, external identity storage, and optional OAuth server mode.
- Non-goals: Password login, registration, tenant rules, role assignment, UI, provider-specific Social/OIDC controller flows, and app-owned OAuth consent screens.

## Repo Map
- `src/`: Package source.
- `src/Console/`: Artisan commands.
- `src/Contracts/`: Extension contracts.
- `src/DTO/`: Token, account security, API key, external identity, and OAuth value objects.
- `src/Events/`: Token, account security, API key, external identity, and OAuth lifecycle events.
- `src/Guards/`: Laravel auth guard driver.
- `src/Http/Middleware/`: JWT, API key, and OAuth resource middleware.
- `src/Models/`: Package-owned JWT, account security, API key, external identity, and OAuth models.
- `src/Security/`: HMAC hash-key helpers.
- `src/Services/`: Token, account security, API key, external identity, and OAuth services.
- `src/Signing/`: JWT signing key and JWKS helpers.
- `src/Support/`: Hook registry and response helpers.
- `src/Traits/`: User model helper traits.
- `config/`: Publishable package configuration.
- `database/migrations/`: Package-owned token tables.
- `routes/`: Package routes such as JWKS and optional OAuth endpoints.
- `tests/`: PHPUnit/Testbench coverage.
- `docs/superpowers/specs/`: Product specification.
- `docs/superpowers/plans/`: Implementation plans.

## Implemented Scope
The implementation covers the modular roadmap:

- `v1.0` Core JWT: guard, signed access tokens, rotating refresh tokens, scopes, claims, revocation, key rotation, JWKS, events, hooks.
- `v1.1` Account Security: MFA challenge broker, hashed OTP, email verification, password reset tokens, delivery contracts, events.
- `v1.2` SaaS Integrations: scoped API keys with hashed secrets, rotation, revocation, and middleware.
- `v2.0` External Identity: normalized external identity DTO, provider contract, and storage.
- `v2.1` OAuth Server: separate OAuth storage, client registry, consents, authorization-code + PKCE, refresh tokens, client credentials, revocation, introspection, and resource middleware.

Optional modules remain disabled by default and must not change the first-party JWT behavior unless explicitly used.
