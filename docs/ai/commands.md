# Commands

## Install Dependencies

```bash
composer install
```

## Test

```bash
composer test
vendor/bin/phpunit
```

Run one test class:

```bash
composer test -- --filter TokenIssueValidateTest
```

## Static Analysis

```bash
composer analyse
```

## Format

```bash
composer format-check
composer format
```

## Full Quality Gate

```bash
composer quality
```

## Package Commands

```bash
php artisan sp-jwt-auth:install --keys
php artisan sp-jwt-auth:setup --keys
php artisan sp-jwt-auth:keys --generate --kid=2026-06-primary
php artisan sp-jwt-auth:jwks --pretty
php artisan sp-jwt-auth:prune --expired-days=30 --revoked-days=30
php artisan sp-jwt-auth:validate [--fix] [--json]
php artisan sp-jwt-auth:boost [--force]
php artisan sp-jwt-auth:mcp
```

Flags (agent / headless use):

- `sp-jwt-auth:setup --keys --skip-migrations --skip-auth-guard --force` — publish config (and migrations unless skipped), patch `config/auth.php` guard (unless skipped), generate PEM keys, ensure `SP_JWT_REFRESH_HASH_KEY`.
- `sp-jwt-auth:validate --json` — machine-readable report `{"status":"ok"|"error","errors":[...],"warnings":[...]}`; exit 0/1.
- `sp-jwt-auth:boost` — wire the package into the client app for Laravel Boost: copies `guidelines/sp-jwt-auth.md` and `skills/sp-jwt-auth/SKILL.md`, registers the skill in `boost.json`, and registers the `sp-jwt-auth` MCP server in `.mcp.json` (use `--force` to overwrite existing files).
- `sp-jwt-auth:mcp` — MCP stdio server exposing `validate`, `jwks`, and `config` (redacted) tools for AI clients.
