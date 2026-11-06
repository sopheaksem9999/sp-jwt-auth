---
title: "This backend does not unquote a scalar like value, so the JS serializer's quoted like matches nothing"
author: Sem
date: 2026-10-30
branch: develop
cost: { minutes: 593, tokens: 6505048 }
id: l-e315a200
scope: repo
status: active
touches:
  - docs/bug-reports/2026-10-26-bug-report-sp-jwt-auth-csrf-token-unreadable-cross-site.md
---

## Decided
Keep the hand-written `like.${text}` filters (user search, reference numbers, report status). A helper over `serializeFilters` was built, reviewed and reverted on 2026-10-30.

## Tried and ruled out
`serializeFilters` / `serializeQuery` with `quoting: true` for a typed `like` value. The package quotes `like."Smith, John (Jr)"`, but `QueryBuilderFiltersUtils` in sp-laravel-api 0.5.08 (`parseOperatorExpression` line 2648, the `like`/`contains` branch lines 992-1001) uses a scalar value raw, so the quoted form searches for the quote characters and returns nothing, with no error. Mocked unit and e2e tests cannot see it.

## Watch out
Only `eq`/`neq`, list items and `or=(…)`/`and=(…)` groups unquote. Redo this only after the backend honours quotes for a scalar `like` (request FR-24 in the backend repo) and a live search for `a,b`, a bracketed name and a reference number with a leading space returns rows.
