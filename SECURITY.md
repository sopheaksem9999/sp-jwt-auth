# Security Policy

## Supported Versions

Security updates are provided for the latest stable release line and the active development branch.

| Version | Supported |
| --- | --- |
| Latest stable tag | Yes |
| `main` | Yes |
| `develop` | Best effort |
| Older tags | No, unless a fix is explicitly backported |

## Reporting a Vulnerability

Please do not open a public issue for a suspected vulnerability.

Report security issues through GitHub Security Advisories for this repository. If advisories are unavailable, email the package maintainer with:

- A concise description of the issue.
- Affected versions or commits.
- Reproduction steps or proof of concept.
- Expected impact.
- Any known mitigations.

We aim to acknowledge valid reports within 72 hours. Confirmed vulnerabilities are fixed privately first, released as a patched tag, and then disclosed with appropriate credit when possible.

## Security Expectations

- Do not include real production keys, tokens, API keys, OAuth client secrets, OTPs, or private JWT signing keys in issues, pull requests, tests, screenshots, or logs.
- Test fixtures may include non-production keys only when they are clearly scoped to tests.
- Vulnerability reports should use synthetic secrets and disposable test applications.
