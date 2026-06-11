# GitHub Actions Workflows

Three workflows guard every push and pull request to `main` and `develop`.
**CI and Security must both pass before Release is allowed to tag.**

```
push to main / develop
        │
        ▼
 ┌─────────────┐       ┌─────────────┐
 │   CI        │       │  Security   │
 │ main.yml    │       │ security.yml│
 └──────┬──────┘       └──────┬──────┘
        │ CI passed?          │ Security passed?
        └──────────────┬──────┘
                       ▼
               release.yml tags
```

---

## main.yml

**Trigger:** every `push` and `pull_request` to `main` or `develop`
**Permission:** `contents: read`

Runs the package quality gate on supported PHP versions.

### Jobs

#### `quality` — PHP quality gate

Matrix:

- PHP 8.3
- PHP 8.4

Steps:

1. Validates `composer.json`.
2. Resolves dependencies with Composer.
3. Runs `composer quality`, which includes Rector dry-run, PHPStan, and PHPUnit.

---

## security.yml

**Trigger:** every `push` and `pull_request` to `main` or `develop`
**Permission:** `contents: read` (read-only, cannot modify the repo)

All four jobs run in parallel. Every job must pass for the Security workflow to be marked successful.

### Jobs

#### `scan-secrets` — Secret Scanning (gitleaks)

Scans the full git history of the push for accidentally committed credentials.

| Detects |
|---|
| GitHub Personal Access Tokens (`ghp_`, `ghs_`, `github_pat_`) |
| AWS access keys (`AKIA...`) |
| PEM private keys (`-----BEGIN RSA/EC/OPENSSH PRIVATE KEY`) |
| Generic API tokens and passwords matched by gitleaks rule set |

**Tool:** [gitleaks/gitleaks-action@v2](https://github.com/gitleaks/gitleaks-action)
**Setup required:** none — uses `GITHUB_TOKEN` automatically.

---

#### `scan-dependencies` — Dependency CVE Check (composer audit)

Resolves Composer dependencies in CI and runs Composer's audit command.

- The repo can keep ignoring `composer.lock` as a library package.
- The workflow still audits the resolved dependency graph.

**Tool:** `composer audit`
**Setup required:** none.

---

#### `scan-sast` — Static Application Security Testing (Semgrep)

Analyses PHP source code for security vulnerabilities without running it.

| Ruleset | Catches |
|---|---|
| `p/php` | PHP-specific unsafe functions, type juggling, open redirects |
| `p/laravel` | Mass assignment, unguarded routes, raw query usage |
| `p/secrets` | Hardcoded passwords, tokens, and keys inside code logic |
| `p/owasp-top-ten` | SQL injection, XSS, path traversal, insecure deserialization |

**Tool:** [semgrep/semgrep-action@v1](https://github.com/semgrep/semgrep-action)
**Setup required:**

1. Create a free account at [semgrep.dev](https://semgrep.dev).
2. Copy your token from **Settings → Tokens**.
3. Add it as a GitHub repository secret named `SEMGREP_APP_TOKEN`.

> Without the token Semgrep still scans and fails the build on findings, but results will not appear in the Semgrep dashboard.

---

#### `scan-files` — File Audit (suspicious files)

Inspects committed files for patterns associated with PHP webshells and malicious scripts.

| Check | What it blocks |
|---|---|
| PHP webshell signatures | `eval(base64_decode(`, `eval(gzinflate(`, `eval(str_rot13(`, `preg_replace /e`, `assert($_POST`, `system($_GET`, `shell_exec($_REQUEST`, `exec($_COOKIE`, `error_reporting(0)` and similar |
| Suspicious PHP filenames | PHP files whose names are hex or hash strings (e.g. `d1337.php`, `a3f9bc12.php`) |
| PHP in upload directories | Any `.php` file inside `upload/`, `uploads/`, `tmp/`, `temp/`, or `cache/` |
| Pipe-to-shell in scripts | `curl ... \| bash` or `wget ... \| sh` inside `.sh` files |

**Tool:** built-in `git grep` and `git ls-files` — no external dependency.

---

## release.yml

**Trigger:** `workflow_run` — fires after the CI workflow completes on `main` or `develop`
**Permission:** `contents: write` and `actions: read`

Release jobs are skipped entirely if:
- CI failed or was cancelled (`conclusion != 'success'`),
- The workflow event was not a `push`,
- The branch is not `main` or `develop`, or
- Security did not pass for the exact same commit.

The checkout always uses `head_sha` from the CI run. Release then queries GitHub Actions to verify the Security workflow also passed for that same SHA, preventing a race condition where a different commit could be tagged.

### Jobs

#### `release-stable`

Runs when CI passed on `main` and Security also passed for the same commit.

1. Reads the version from `VERSION` or falls back to `composer.json` (`version` field).
2. Validates strict semver format `X.Y.Z` — rejects any string with shell metacharacters.
3. Skips silently if the tag already exists (idempotent).
4. Creates a git tag and pushes it.
5. Creates a GitHub Release with auto-generated release notes.

---

#### `release-beta`

Runs when CI passed on `develop` and Security also passed for the same commit.

Same version-reading and validation steps as stable, then:

1. Builds a tag in the format `{version}-beta.{run_number}` (e.g. `0.4.32-beta.17`).
2. Creates a git tag and pushes it.
3. Creates a GitHub Pre-Release with auto-generated release notes.

---

## Required GitHub repository secrets

| Secret | Used by | How to obtain |
|---|---|---|
| `GITHUB_TOKEN` | gitleaks (auto-provided) | Automatic — no action needed |
| `SEMGREP_APP_TOKEN` | Semgrep SAST | [semgrep.dev](https://semgrep.dev) → Settings → Tokens |

---

## Recommended GitHub repository settings

These settings are configured in **Settings → Code security** and **Settings → Branches**, not in the workflow files.

| Setting | Location | Why |
|---|---|---|
| **Secret scanning** (GitHub native) | Settings → Code security | Runs in parallel with gitleaks as a second layer |
| **Dependabot alerts** | Settings → Code security | Alerts you when a new CVE affects the package dependency graph |
| **Branch protection on `main`** | Settings → Branches | Require CI and Security workflows to pass before any merge is allowed |
| **Branch protection on `develop`** | Settings → Branches | Same as above for develop |
| **Require pull request reviews** | Settings → Branches | No direct push to `main` or `develop` without a review |
