---
title: "auth.hasScope accepts a wildcard scope and auth.http does not refresh on a 401, so neither replaces the app's own code"
author: Sem
date: 2026-10-30
branch: develop
cost: { minutes: 678, tokens: 8098371 }
id: l-a1c9e241
scope: repo
status: active
touches:
  - docs/bug-reports/2026-10-26-bug-report-sp-jwt-auth-csrf-token-unreadable-cross-site.md
---

## Decided
`src/utils/jwt.ts` keeps `scopes.includes('super-admin')`. Forgot password, reset password and resend verification stay on `sendApiRequestV2`.

## Tried and ruled out
`auth.hasScope('super-admin')`: it also answers true for a `'*'` scope, while `SuperAdminChecker` and `SuperAdminPmsMiddleware` test the exact string. `account(auth)` for those three routes: the framework wraps a function route's plain body into `{ success, data, ... }`, so `unwrap('sp-laravel-api')` returns only `data` and the views lose `success` and `message`; and `auth.http` does no 401 refresh and retry, which the facade does.

## Watch out
The GET `/qb_auth/sync_*` routes queue a job each time, so they must never be retried (`sendApiRequestV2` passes `retry: false` for `/qb_auth/`). The client retry is `{ maxAttempts: 2, networkErrors: false }`: the package counts a timeout as a network error, and retrying it would triple the 60 s wait of a plain GET.
