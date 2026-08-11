# sp-jwt-auth Guidelines

Guidelines for agents working with applications that use `sopheak/sp-jwt-auth`.

## Identity

- Package: `sopheak/sp-jwt-auth` — first-party JWT access and rotating refresh token authentication for Laravel 12/13 (PHP 8.3+).
- The `sp-jwt` auth guard is registered in `config/auth.php`; tokens are signed with RS256 PEM key pairs.
- Client-facing docs: `vendor/sopheak/sp-jwt-auth/docs/client-install.md` and `vendor/sopheak/sp-jwt-auth/boot.json`.

## Setup and verification

- Install: `composer require sopheak/sp-jwt-auth:^0.1`.
- Configure: `php artisan sp-jwt-auth:setup --keys` (publishes config + migrations, patches `config/auth.php`, generates keys, sets `SP_JWT_REFRESH_HASH_KEY`).
- Migrate: `php artisan migrate`.
- Verify: `php artisan sp-jwt-auth:validate --json` — exit 0 with `{"status":"ok"}` when configured; use `--fix` for safe repairs.
- Wire AI tooling: `php artisan sp-jwt-auth:boost` (installs guidelines + skill, registers the skill in `boost.json` and the MCP server in `.mcp.json`), then `php artisan boost:install`.

## User model

- Add `Sopheak\JwtAuth\Traits\HasJwtTokens` to the `User` model for `$user->token()` / `$user->tokenCan($scope)` / `$user->withAccessToken($token)`.

## Security rules

- Never log, print, or commit tokens, secrets, or private keys.
- Never use `APP_KEY` as a JWT signing key.
- `SP_JWT_REFRESH_HASH_KEY` is a secret (64 hex chars); do not rotate it while refresh tokens are outstanding.
- Do not expose private key material or hash keys via AI tools or responses — use `sp-jwt-auth:validate --json`, the `jwks` MCP tool, or the `config` MCP tool (redacted) instead.
