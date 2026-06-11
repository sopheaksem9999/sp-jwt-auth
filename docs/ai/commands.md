# Commands

## Install Dependencies

```bash
composer install
```

## Test

```bash
composer test
vendor/bin/phpunit
```

Run one test class:

```bash
composer test -- --filter TokenIssueValidateTest
```

## Static Analysis

```bash
composer analyse
```

## Format

```bash
composer format-check
composer format
```

## Full Quality Gate

```bash
composer quality
```

## Package Commands

```bash
php artisan sp-jwt-auth:install --keys
php artisan sp-jwt-auth:keys --generate --kid=2026-06-primary
php artisan sp-jwt-auth:jwks --pretty
php artisan sp-jwt-auth:prune --expired-days=30 --revoked-days=30
```
