---
name: sp-jwt-auth
description: 'ACTIVATE when the user works on authentication in a Laravel app using sopheak/sp-jwt-auth. Includes JWT access tokens, rotating refresh tokens, bearer token auth, the sp-jwt guard, token issuance or validation, token scope checks, API keys, JWKS, key generation/rotation, or setup/validation of the sp-jwt-auth package (sp-jwt-auth:setup, sp-jwt-auth:validate, sp-jwt-auth:keys, sp-jwt-auth:jwks, sp-jwt-auth:boost). Also activate when the user mentions sp-jwt, JWT auth, bearer tokens, refresh token rotation, HasJwtTokens, SP_JWT_REFRESH_HASH_KEY, or config/sp-jwt-auth.php. Do NOT activate for Laravel Fortify (session auth), Passport (OAuth2 tokens), Sanctum (personal access tokens), or Socialite (OAuth social login).'
license: MIT
metadata:
  author: sopheak
---

# sp-jwt-auth

First-party JWT access and rotating refresh token authentication for Laravel applications (Laravel 12/13, PHP 8.3+).

## Documentation

- Client installation guide: `vendor/sopheak/sp-jwt-auth/docs/ai/client-install.md`
- Agent manifest: `vendor/sopheak/sp-jwt-auth/boot.json`
- Agent guidelines: `vendor/sopheak/sp-jwt-auth/guidelines/sp-jwt-auth.md`
- Full docs: `vendor/sopheak/sp-jwt-auth/docs/` (or the published docs site referenced in the package README)

## Key concepts

- `sp-jwt` auth guard registered in `config/auth.php` (patched automatically by `sp-jwt-auth:setup`).
- Access tokens are short-lived (default 15 min) RS256 JWTs; refresh tokens are opaque, HMAC-hashed, rotated per use with reuse detection.
- Tokens are scoped; use `$user->tokenCan('scope')` in authorization checks.
- The `User` model must use `Sopheak\JwtAuth\Traits\HasJwtTokens`.

## Setup workflows

### Fresh install

```
- [ ] composer require sopheak/sp-jwt-auth:^0.1
- [ ] php artisan sp-jwt-auth:setup --keys
- [ ] php artisan migrate
- [ ] php artisan sp-jwt-auth:validate --json  (expect exit 0, {"status":"ok"})
- [ ] Add HasJwtTokens trait to the User model
```

### Verify or repair existing install

```
- [ ] php artisan sp-jwt-auth:validate --json
- [ ] On failure: php artisan sp-jwt-auth:validate --fix, then re-validate
- [ ] Missing keys: php artisan sp-jwt-auth:keys --generate
- [ ] Inspect public keys: php artisan sp-jwt-auth:jwks --pretty
```

### Rotate signing keys

```
- [ ] php artisan sp-jwt-auth:keys --generate --kid=<new-kid>  (keeps previous key active for grace period)
- [ ] Set SP_JWT_ACTIVE_KID to the new kid (or follow the package's documented rotation steps)
- [ ] php artisan sp-jwt-auth:validate --json
```

### Wire AI tooling (Laravel Boost)

```
- [ ] php artisan sp-jwt-auth:boost  (installs guidelines + skill, registers boost.json skill and .mcp.json MCP server)
- [ ] php artisan boost:install  (regenerates agent guidelines)
- [ ] Use the sp-jwt-auth MCP tools: validate, jwks, config
```

## Key configuration

- `config/sp-jwt-auth.php` — all settings; published by setup.
- `.env` — `SP_JWT_REFRESH_HASH_KEY` (required secret, 64 hex chars); optional `SP_JWT_ACTIVE_KID`, `SP_JWT_GUARD`, TTLs.
- `auth.guards.api.driver` must be `sp-jwt`.

## Commands

| Command | Purpose |
|---|---|
| `php artisan sp-jwt-auth:setup --keys` | Publish config/migrations, patch guard, generate keys, set hash key env |
| `php artisan sp-jwt-auth:install --keys` | Publish config/migrations only |
| `php artisan sp-jwt-auth:validate [--fix] [--json]` | Validate setup; machine-readable JSON report |
| `php artisan sp-jwt-auth:keys --generate [--pem]` | Generate/rotate signing keys |
| `php artisan sp-jwt-auth:jwks [--pretty]` | Print public JWKS |
| `php artisan sp-jwt-auth:prune` | Prune expired/revoked tokens |
| `php artisan sp-jwt-auth:boost` | Install Boost guidelines/skill + MCP registration |
| `php artisan sp-jwt-auth:mcp` | MCP stdio server (validate/jwks/config tools) |

## Security rules

- Never log, print, or commit tokens, secrets, or private keys.
- Never use `APP_KEY` as a JWT signing key.
- Never expose private key material or hash keys — use the redacted `config` MCP tool or `validate --json` instead.
- `SP_JWT_REFRESH_HASH_KEY` must stay secret; rotating it invalidates outstanding refresh tokens.
