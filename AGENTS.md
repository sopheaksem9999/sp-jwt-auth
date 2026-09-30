# AGENTS.md — sp-jwt-auth

## Identity

- **Package:** `sopheak/sp-jwt-auth` (composer.json), namespace `Sopheak\JwtAuth\`, provider `CoreSpJwtAuthServiceProvider`
- **Stack:** PHP 8.3+, Laravel 12+/13+, `firebase/php-jwt`, Orchestra Testbench 10+/11+
- **Database:** SQLite, MySQL, PostgreSQL — use Laravel schema builder, avoid DB-specific SQL

## State

- `src/` has real code (Console, Contracts, DTO, Events, Guards, Http, Models, Security, Services, Signing, Support, Testing, Traits)
- `tests/` has Unit + Feature tests + TestCase base class; `tests/Fixtures/keys/*.pem` are committed test-only keys
- Active branch is `develop`; `main` is the release branch (CI runs `composer quality` on both, PHP 8.3/8.4)
- Version is bumped in lockstep across `VERSION` and `CHANGELOG.md` (currently 0.1.21); `composer.json` has no `version` field — Packagist derives it from git tags, and `composer validate --strict` rejects the field
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

Package Artisan commands: `sp-jwt-auth:install --keys`, `sp-jwt-auth:setup --keys`, `sp-jwt-auth:keys`, `sp-jwt-auth:jwks`, `sp-jwt-auth:prune`, `sp-jwt-auth:validate [--fix] [--json]`, `sp-jwt-auth:boost`, `sp-jwt-auth:agent [--force|--skill|--rules|--mcp]`, `sp-jwt-auth:mcp`.

## Testing

- Orchestra Testbench — not a full Laravel app. No `.env` needed (phpunit.xml.dist sets SQLite `:memory:` + SP_JWT env vars).
- `tests/TestCase.php` is the base; extend it in tests.
- Run one class: `composer test -- --filter TokenIssueValidateTest`.
- `tests/Fixtures/keys/*.pem` are committed **test-only** RSA keys; real `*.key`/`*.pem` files are gitignored — never commit real keys.

## Key Conventions

- Never use `APP_KEY` as JWT signing key.
- Never log tokens, secrets, or private keys.
- Never put tokens, secrets, or client data in task-progress artifacts (todo items, session summaries, compaction summaries, commit messages) — same rule as logs; `share` is disabled in `opencode.json` for this reason.
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

- `boot.json` — machine-readable install/setup/verify steps for Laravel Boot and other agents scaffolding client apps. No skill/agent assets are auto-added on install.
- `guidelines/sp-jwt-auth.md` — agent guidelines; installs into client `.agents/rules/` via `sp-jwt-auth:agent`.
- `skills/sp-jwt-auth/SKILL.md` — agentskills.io-format skill; installs into client `.agents/skills/` via `sp-jwt-auth:agent`.
- `docs/client-install.md` — step-by-step client installation guide for agents (publish, configure, migrate, validate, User model trait, optional modules).
- `sp-jwt-auth:boost` — merges the MCP server into the client's `.mcp.json` and runs setup validation (Laravel Boost integration).
- `sp-jwt-auth:agent` — on-demand install of the agent skill, rules, and MCP entry into the client (`.agents/skills/`, `.agents/rules/`, `.mcp.json`).
- `sp-jwt-auth:mcp` — MCP stdio server (read-only `validate`, `jwks`, `config` tools; secrets never exposed).
- Optional `first_factor_otp` module — `FirstFactorOtpBroker` + `FirstFactorUserResolver` contract + `routes/otp.php` (config-gated).
- Optional `token_endpoints` module — `routes/token.php` (`POST /auth/token/refresh`, `POST /auth/token/revoke`, config-gated).

## Memory

- Scope: `sp-jwt-auth`

<!-- graft:start -->
## Graft — repo context graph

This repo is indexed in `graft/`: small linked markdown nodes that explain each
system and carry exact file:line spans, kept in sync with the code through git.

For ANY task here — understanding how something works, finding where code lives,
or scoping a change — get context from the graph before grepping or opening
source files. Re-ask freely (it's cheap) and reuse literal identifiers you
already have (symbol, error string, file name) as the query. New to this repo?
Run `graft map` first — a token-budgeted orientation (dir clusters, hubs,
hotspots), no LLM, no key.

- Run `graft ask "<your question>" --source` → ranked nodes with the relevant
  code spans inlined (each hit's ≤8-line crux by default; `--full` for whole
  definitions when the crux isn't enough). Match the tool to the task shape:
  for understanding or editing, the top node IS the answer — cite its
  `covers:` file:line spans and edit straight from `--source`. For
  exhaustive tasks ("every occurrence / every caller of this pattern"), ranked
  results are top-N, not complete — run `graft grep "<literal>"` instead
  (exhaustive over indexed files, grouped by enclosing symbol), falling back
  to raw `grep -rn` only for unindexed files.
- `graft skeleton <file>` → every definition's signature + span, ~10× cheaper
  than reading the file; use it to skim an API surface.
- `graft callers <symbol>` gives precomputed, exact edges — who calls this.
  Add `--direction out` for what it calls, or `--depth N` to walk
  transitively for the full blast radius. For structural questions, skip
  ranking and use this directly.
- Or browse: `graft/INDEX.md` lists every node; follow the links.
- Monorepos and folders of multiple repos rank fairly across sub-projects —
  hits carry `[scope/]` labels naming which one they're from. Narrow with
  `graft ask "<task>" --in <scope>/` once you know where you're working.

If a returned span is truncated ("+N more lines"), open the file at that exact
range before finalizing. Only open source files when a node genuinely lacks a
needed detail, and then at the exact file:line the node points to — never
re-read whole files.

After big code changes, refresh the graph with `graft build` (deterministic,
no API key, $0).
<!-- graft:end -->
