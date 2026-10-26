---
title: "Refresh cookie transport: CSRF is 403, config defaults live in code, tests need withCredentials"
author: Sem
date: 2026-10-26
branch: develop
cost: { minutes: 12, tokens: 128437 }
id: l-0c113684
scope: repo
status: active
touches:
  - CHANGELOG.md
  - config/sp-jwt-auth.php
  - docs/features/token-endpoints.md
  - docs/guide/token-endpoints.md
  - routes/otp.php
  - routes/token.php
  - src/Services/JwtTokenService.php
  - src/Support/RefreshCookie.php
  - src/Support/TokenResponse.php
  - tests/Feature/FirstFactorOtpHttpTest.php
---

## Decided
- Cookie-mode refresh/revoke with a missing or wrong double-submit CSRF header returns 403, never 401: the JS client treats 401 as "session ended" and would log the user out.
- Every `token_endpoints.*` cookie/CSRF setting has a default in `Support/RefreshCookie.php`, not only in `config/`. `mergeConfigFrom` is shallow, so an app with a published config replaces the whole `token_endpoints` array and would otherwise lose the new keys.
- `/auth/token/revoke` has no `auth` middleware: it accepts the bearer token, or the refresh token from body/cookie, so logout works after the access token expired. CSRF is only required when the cookie is the credential.

## Tried and ruled out
- Reading the refresh token from the body in `cookie` mode (kept only in `both`): defeats the point of keeping it out of JavaScript.

## Watch out
- Laravel's `postJson()` does not send cookies unless `withCredentials()` is set; cookie tests also need `withUnencryptedCookies()`. Package routes sit outside the `web` group, so cookies are not encrypted.
- This sandbox's tooling may stage extra files on `git commit`; use `git commit -o -- <paths>` for split commits (and a zsh array, not an unquoted string, for the path list).
