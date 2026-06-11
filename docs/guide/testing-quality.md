---
title: "Testing and Quality"
description: "Local verification commands for sopheak/sp-jwt-auth."
---

# Testing and Quality

The package uses Orchestra Testbench, PHPUnit, Larastan/PHPStan, and Rector.

## Install Dependencies

```bash
composer install
```

## Run Tests

```bash
composer test
```

Run a focused test:

```bash
composer test -- --filter OAuthServerTest
```

## Static Analysis

```bash
composer analyse
```

## Formatting

```bash
composer format-check
composer format
```

## Full Gate

```bash
composer quality
```

Expected gate:

- Rector dry-run clean.
- PHPStan has no errors.
- PHPUnit passes.
