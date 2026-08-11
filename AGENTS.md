# AGENTS.md — sp-jwt-auth

## Identity

- **Package:** `sopheak/sp-jwt-auth` (composer.json), namespace `Sopheak\JwtAuth\`, provider `CoreSpJwtAuthServiceProvider`
- **Stack:** PHP 8.3+, Laravel 12+/13+, `firebase/php-jwt`, Orchestra Testbench 10+/11+
- **Database:** SQLite, MySQL, PostgreSQL — use Laravel schema builder, avoid DB-specific SQL

## State

- `src/` has real code (Console, Contracts, DTO, Events, Guards, Http, Models, Security, Services, Signing, Support, Testing, Traits)
- `tests/` has Unit + Feature tests + TestCase base class; `tests/Fixtures/keys/*.pem` are committed test-only keys
- Active branch is `develop`; `main` is the release branch (CI runs `composer quality` on both, PHP 8.3/8.4)
- Version is bumped in lockstep across `composer.json` `version`, `VERSION`, and `CHANGELOG.md` (currently 0.1.20)
- `composer.lock` is gitignored — installs float on latest deps (CI runs `composer update`)

## Commands

| Action | Command | Notes |
|---|---|---|
| Test | `composer test` or `vendor/bin/phpunit` | Use `--filter=<TestClass>` to run one |
| Static analysis | `composer analyse` | Runs `phpstan analyse src tests`; **no phpstan.neon exists — runs at PHPStan level 0 defaults** (larastan installed but not wired up) |
| Format (Rector) | `composer format` | PHP 8.4 sets + import names + `declare(strict_types=1)`; only touches `src/` + `tests/` |
| Format check | `composer format-check` | Rector dry-run; skip when editing docs/config |
| Quality gate | `composer quality` | format-check → analyse → test (that order) |
| PHP-CS-Fixer | `./vendor/bin/php-cs-fixer fix --dry-run --diff` | Config: `@auto` rules; not part of the quality gate |

Package Artisan commands: `sp-jwt-auth:install --keys`, `sp-jwt-auth:setup --keys`, `sp-jwt-auth:keys`, `sp-jwt-auth:jwks`, `sp-jwt-auth:prune`, `sp-jwt-auth:validate [--fix] [--json]`, `sp-jwt-auth:boost [--force]`, `sp-jwt-auth:mcp`.

## Testing

- Orchestra Testbench — not a full Laravel app. No `.env` needed (phpunit.xml.dist sets SQLite `:memory:` + SP_JWT env vars).
- `tests/TestCase.php` is the base; extend it in tests.
- Run one class: `composer test -- --filter TokenIssueValidateTest`.
- `tests/Fixtures/keys/*.pem` are committed **test-only** RSA keys; real `*.key`/`*.pem` files are gitignored — never commit real keys.

## Key Conventions

- Never use `APP_KEY` as JWT signing key.
- Never log tokens, secrets, or private keys.
- Hash refresh tokens with HMAC + stored `hash_key_id`; use timing-safe comparisons.
- Refresh rotation inside a DB transaction (detect reuse).
- User ownership via `user_type` + `user_id` (polymorphic), not foreign keys.
- Use DTOs for service boundaries, not response helpers.
- Use named arguments for type/DTO constructors.

## Instruction Files

These live in `.opencode/rules/` (local, gitignored — not shipped with the package) and are loaded via `opencode.json`:

- `.opencode/rules/coding-standards.md` — security rules, package conventions, testing expectations
- `.opencode/rules/architecture.md` — core flow, storage tables, security boundaries
- `.opencode/rules/project-context.md` — repo map, implemented scope (v1.0 Core JWT → v2.1 OAuth), non-goals
- `.opencode/rules/commands.md` — full command list with examples

## Client-side / Boot

- `boot.json` — machine-readable install/setup/verify steps for Laravel Boot and other agents scaffolding client apps.
- `guidelines/sp-jwt-auth.md` — Boost auto-detect guidelines for agents working with the package in client apps.
- `skills/sp-jwt-auth/SKILL.md` — agentskills.io-format skill; installs into client `.agents/skills/` via `sp-jwt-auth:boost`.
- `docs/client-install.md` — step-by-step client installation guide for agents (publish, configure, migrate, validate, User model trait, optional modules).
- `sp-jwt-auth:boost` — wires guidelines/skill into the client, registers the Boost skill in `boost.json` and the MCP server in `.mcp.json`.
- `sp-jwt-auth:mcp` — MCP stdio server (read-only `validate`, `jwks`, `config` tools; secrets never exposed).
- Optional `first_factor_otp` module — `FirstFactorOtpBroker` + `FirstFactorUserResolver` contract + `routes/otp.php` (config-gated).
- Optional `token_endpoints` module — `routes/token.php` (`POST /auth/token/refresh`, `POST /auth/token/revoke`, config-gated).

## Memory

- Scope: `sp-jwt-auth`
