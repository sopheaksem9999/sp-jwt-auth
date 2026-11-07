# v1.0.0 Stabilization Roadmap & Graduation Criteria

This document defines the formal milestone checklist and technical criteria required to graduate **`sopheak/sp-jwt-auth`** from `0.x` SemVer to **`1.0.0` (Production Stable)**.

---

## 1. Why v1.0.0?

Under Semantic Versioning (`SemVer`), `0.x.y` indicates initial development where public APIs may shift at any time. Moving to `1.0.0` provides key guarantees to adopting engineering teams:
* **API Stability Guarantee:** Strict SemVer compliance — no breaking signature, behavioral, or schema changes without incrementing the major version.
* **Supply-Chain & Security Scanner Maturity:** Resolves `version_stability` cautions on [Aikido Intel](https://intel.aikido.dev/packages/packagist/sopheak/sp-jwt-auth) and OpenSSF scorecard evaluations.
* **Enterprise Trust:** Signals production hardening across high-volume production deployments.

---

## 2. Graduation Criteria Checklist

### Milestone 1: Public Contract & API Freeze
- [ ] **Contract Interfaces (`src/Contracts/`):** Ensure every primary service defines an immutable interface:
  - `JwtGuardContract` / `JwtGuard`
  - `JwtTokenServiceContract` / `JwtTokenService`
  - `DeviceServiceContract` / `DeviceService`
  - `FirstFactorOtpBroker` & `FirstFactorUserResolver`
  - `ExternalIdentityStore`
  - `SigningKeyRepository` & `HashKeyRepository`
- [ ] **Method Signatures & Return Types:** Confirm all public methods use strict PHP 8.3+ scalar and union return types.
- [ ] **DTO Immutability:** Verify all data transfer objects (`TokenContext`, `TokenResponse`, `DeviceRegistration`, `OtpDeliveryResult`) use `readonly` properties.

### Milestone 2: Runtime Compatibility & Matrix Coverage
- [ ] **PHP Compatibility:** 100% test pass on PHP 8.3 and PHP 8.4; pre-release validation on PHP 8.5.
- [ ] **Laravel Compatibility:** Full test matrix across Laravel 12.x and Laravel 13.x.
- [ ] **Database Engines:** Comprehensive integration test validation across SQLite (in-memory & file), MySQL 8+, and PostgreSQL 15+.

### Milestone 3: Deprecation Sweep & Clean Migration
- [ ] **Obsolete Configuration Pruning:** Verify all active config values in `config/sp-jwt-auth.php` are functional and documented.
- [ ] **Migration Upgrades:** Ensure all database migrations in `database/migrations/` handle upgrade and rollback scenarios cleanly.
- [ ] **Octane & Serverless Readiness:** Ensure all memoized singletons and key repositories safely isolate state across persistent Octane workers (FrankenPHP, RoadRunner, Swoole).

### Milestone 4: Client SDK Synchronization
- [ ] **Flutter / Dart SDK:** Ensure [`sp_jwt_auth` on pub.dev](https://pub.dev/packages/sp_jwt_auth) is verified against the `v1.0.0` backend API with complete Dio/Http interceptor coverage.
- [ ] **TypeScript / JavaScript SDK:** Ensure [`@sokpheak/sp-jwt-auth` on npm](https://www.npmjs.com/package/@sokpheak/sp-jwt-auth) aligns with token refresh and cookie transport protocols.
- [ ] **API Documentation:** Update all endpoint guides in `docs/` and client installation instructions in `docs/client-install.md`.

---

## 3. Release Sequence for v1.0.0

1. **v1.0.0-RC1 (Release Candidate 1):** Tagged on `develop` for a 2-week public stabilization window.
2. **Community Verification:** Test across internal and external pilot projects with zero breaking bug reports.
3. **v1.0.0 Stable:** Fast-forward merged into `main`, tagged, and published to Packagist.
