# AGENTS.md — sp-jwt-auth

## Identity

- **Package:** `sopheak/sp-jwt-auth` (composer.json), namespace `Sopheak\JwtAuth\`, provider `CoreSpJwtAuthServiceProvider`
- **Stack:** PHP 8.3+, Laravel 12+/13+, `firebase/php-jwt`, Orchestra Testbench 10+/11+
- **Database:** SQLite, MySQL, PostgreSQL — use Laravel schema builder, avoid DB-specific SQL

## State

- `src/` has real code (Console, Contracts, DTO, Events, Guards, Http, Models, Security, Services, Signing, Support, Traits)
- `tests/` has Unit + Feature tests + TestCase base class
- Single initial commit on `main`

## Commands

| Action | Command | Notes |
|---|---|---|
| Test | `composer test` or `vendor/bin/phpunit` | Use `--filter=<TestClass>` to run one |
| Static analysis | `composer analyse` | Runs `phpstan analyse src tests` |
| Format (Rector) | `composer format` | PHP 8.4 sets + import names + `declare(strict_types=1)` |
| Format check | `composer format-check` | Rector dry-run; skip when editing docs/config |
| Quality gate | `composer quality` | format-check → analyse → test (that order) |
| PHP-CS-Fixer | `./vendor/bin/php-cs-fixer fix --dry-run --diff` | Config: `@auto` rules |

Package Artisan commands: `sp-jwt-auth:install --keys`, `sp-jwt-auth:keys`, `sp-jwt-auth:jwks`, `sp-jwt-auth:prune`.

## Testing

- Orchestra Testbench — not a full Laravel app. No `.env` needed.
- `tests/TestCase.php` is the base; extend it in tests.

## Key Conventions

- Never use `APP_KEY` as JWT signing key.
- Never log tokens, secrets, or private keys.
- Hash refresh tokens with HMAC + stored `hash_key_id`; use timing-safe comparisons.
- Refresh rotation inside a DB transaction (detect reuse).
- User ownership via `user_type` + `user_id` (polymorphic), not foreign keys.
- Use DTOs for service boundaries, not response helpers.
- Use named arguments for type/DTO constructors.

## Instruction Files

These are loaded via `opencode.json` and provide deeper context:

- `docs/ai/coding-standards.md` — security rules, package conventions, testing expectations
- `docs/ai/architecture.md` — core flow, storage tables, security boundaries
- `docs/ai/project-context.md` — repo map, current scope (v1.0 Core JWT), non-goals
- `docs/ai/commands.md` — full command list with examples

**Note:** `opencode.json` also references `.trae/rules/project_rules.md` which no longer exists — that's stale config, not a missing instruction.

## Memory

- Scope: `sp-jwt-auth`
