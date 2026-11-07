# Project Governance & Maintainer Guidelines

This document outlines the governance structure, decision-making processes, contributor ladder, and continuity protocols for **`sopheak/sp-jwt-auth`**.

---

## 1. Project Leadership & Roles

### Lead Maintainer / Project Lead
* **Lead Maintainer:** Sem Sopheak ([@sopheaksem9999](https://github.com/sopheaksem9999))
* **Responsibilities:**
  - Setting overall architectural direction, feature scope, and roadmap.
  - Final sign-off on pull requests touching core security primitives (`JwtGuard`, `JwtTokenService`, `DeviceService`, `FirstFactorOtpBroker`).
  - GPG signing and tagging of official releases (`vX.Y.Z`).
  - Administering repository security settings, branch protection rules, and GitHub Actions permissions.

---

## 2. Contributor Ladder

`sp-jwt-auth` welcomes community participation through a meritocratic contributor pathway:

| Role | Responsibilities | Requirements & Path |
|---|---|---|
| **Contributor** | Submits bug reports, documentation updates, or pull requests. | Anyone opening an issue or PR adhering to the code of conduct and contribution guidelines. |
| **Triager** | Verifies bug reproductions, labels issues, closes stale or duplicate tickets, assists in community discussions. | Demonstrated familiarity with package internals; nominated by an existing maintainer after sustained contributions. |
| **Reviewer** | Reviews incoming pull requests, audits test coverage, verifies compliance with `composer quality` and strict typing standards. | History of high-quality PRs, deep understanding of Laravel authentication architecture, and commitment to backward compatibility. |
| **Co-Maintainer** | Approves PRs, manages branches, assists in vulnerability remediations, participates in release planning. | Extended track record of stewardship; nominated and appointed by the Project Lead. |

---

## 3. Decision-Making Process

1. **Consensus-Driven:** Architectural changes, new feature proposals, and configuration deprecations begin as open discussions in GitHub Issues or PR design specifications (`docs/superpowers/specs/`).
2. **Review Threshold:** Code changes require at least one approving review and full passage of automated CI (`main.yml`) and Security (`security.yml`) checks.
3. **Tie-Breaking:** When consensus cannot be reached, the Lead Maintainer makes the final determination with documented technical justification.

---

## 4. Code Quality & Security Standards

All contributions must satisfy the package quality gates before merging:
1. **Quality Gate:** `composer quality` must pass without warnings:
   - Format check: Rector rules with strict types (`declare(strict_types=1);`).
   - Static analysis: PHPStan level 0+ with zero errors.
   - Test suite: PHPUnit tests passing 100% across supported PHP versions (PHP 8.3, 8.4, and 8.5).
2. **Supply-Chain Integrity:**
   - All third-party GitHub Actions must be pinned to full 40-character commit SHAs.
   - Zero untrusted installation lifecycle scripts (`pre-install-cmd` / `post-install-cmd`).
   - Mandatory secret scanning (`gitleaks`) and dependency audit (`composer audit`).
3. **Fail-Closed Security:** All authentication, authorization, and cryptographic token operations must fail closed.

---

## 5. Security Triage & Vulnerability Management

* Security issues must be reported confidentially following the procedures in [`SECURITY.md`](SECURITY.md) (not via public issues).
* **Initial Response SLA:** The maintainers commit to an initial assessment within **48 hours**.
* **Remediation & Advisory:** Valid vulnerabilities will be addressed in a private fork, verified against the test suite, assigned a CVE where appropriate, and released with a security advisory and coordinated disclosure timeline.

---

## 6. Emergency Continuity & Succession Protocol

To guarantee continuity of maintenance and prevent single-point-of-failure risks:
1. **Repository Access Backup:** Project administrative access, repository ownership, and Packagist publication credentials are maintained with emergency succession protocols.
2. **Co-Maintainer Escalation:** If the Lead Maintainer is unavailable for an extended period (>60 days) without prior delegation, active Reviewers and trusted organizational contributors may request administrative transition through documented project channels to maintain security updates and dependency compatibility.
3. **Open Source Guarantee:** The codebase is permanently licensed under the permissive [MIT License](LICENSE). In accordance with open-source freedoms, the community retains the perpetual right to fork and maintain the package if required.
