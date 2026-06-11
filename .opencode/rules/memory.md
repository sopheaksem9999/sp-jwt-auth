# Memory Setup for This Project

When using the opencode-memory plugin in this project, follow these rules:

## Scope Convention

Always use scope="sp-laravel-api" for memories specific to this project.
Replace <PROJECT_NAME> with the actual project slug (e.g., "my-api", "web-dashboard").

## When to Store Memories

Proactively remembaer:
- **decision**: Architecture choices, tech stack decisions, naming conventions
- **learning**: Bugs you discovered, workarounds, undocumented behavior
- **preference**: Code style preferences, library choices, linting rules
- **blocker**: Current issues blocking progress, pending PRs, missing deps
- **context**: Project structure, key files, environment setup
- **pattern**: Recurring patterns in the codebase (e.g., "all handlers use this middleware pattern")

## When Starting a New Session

Always recall project memories first:
- `memory_recall(scope="sp-laravel-api")` — get all project context
- `memory_recall(scope="user")` — get user preferences

## Memory Format

Store decisions with enough detail to be useful later:
- Good: "API uses JWT auth with 15min access tokens and 7-day refresh tokens stored in httpOnly cookies"
- Bad: "Uses JWT"

## Cleanup

If a decision changes, use `memory_update` instead of creating a duplicate.
Use `memory_forget` with a clear reason when something is no longer relevant.
