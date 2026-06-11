# Coding Standards

## General
- Keep changes minimal and consistent with Laravel package conventions.
- Prefer small, focused classes with clear names.
- Avoid breaking public APIs without discussion.
- Never add real secrets, private production keys, or provider tokens.
- Follow PSR-12 and the repository Rector rules.

## Package Conventions
- Namespace package code under `Sopheak\JwtAuth`.
- Use Laravel contracts and services instead of application-specific classes.
- Keep token infrastructure generic; applications own login, registration, tenants, roles, UI, and response shape.
- Use DTOs for package service boundaries instead of forcing JSON response helpers.
- Store user ownership as `user_type` and `user_id` for provider/model compatibility.

## Security
- Never use `APP_KEY` as a JWT signing key.
- Never log access tokens, refresh tokens, plaintext secrets, or private keys.
- Hash refresh token secrets with HMAC and a stored `hash_key_id`.
- Validate JWTs against configured algorithms and key ids only.
- Use timing-safe comparisons for token secret hashes.
- Run refresh rotation inside a database transaction.

## Database Compatibility
- Support SQLite, MySQL, and PostgreSQL through Laravel schema builder.
- Use string-compatible polymorphic ids for app-owned models.
- Avoid partial indexes and database-specific SQL in package migrations.

## Testing
- New behavior should include PHPUnit/Testbench coverage.
- Follow red-green-refactor for behavior changes.
- Prefer testing public package behavior through services, guards, middleware, and commands.
- Run `composer test`, `composer analyse`, and `composer format-check` before claiming completion.
