# Contributing

Thanks for helping improve `sopheak/sp-jwt-auth`.

## Development Setup

```bash
composer install
composer quality
```

`composer quality` runs Rector dry-run, PHPStan, and PHPUnit. For a smaller loop, run:

```bash
composer test -- --filter=<TestClass>
composer analyse
composer format-check
```

## Pull Requests

- Keep changes focused on one behavior or documentation topic.
- Add or update tests for code changes.
- Update README or docs when public APIs, commands, config, or setup steps change.
- Do not commit real secrets, production signing keys, OAuth credentials, refresh tokens, API keys, or private customer data.
- Use Laravel schema builder for migrations and avoid database-specific SQL.

## Coding Standards

- PHP 8.3+ with `declare(strict_types=1)`.
- Prefer DTOs at service boundaries.
- Never use `APP_KEY` as a JWT signing key.
- Never log access tokens, refresh tokens, plaintext secrets, private keys, OTPs, or provider tokens.
- Keep refresh token rotation inside a database transaction.

## Release Notes

Add user-visible changes to `CHANGELOG.md` under `Unreleased`. Maintainers create release tags after the quality gate passes.
