---
title: "Device Module"
description: "Register devices, issue device-scoped tokens before login, and upgrade them to user sessions."
---

# Device Module

An optional, disabled-by-default module for pre-login flows (onboarding, guest carts, anonymous sessions). A device registers once, gets a server-checked credential, and receives **device-scoped tokens** that work like any other token pair (signing, refresh rotation, reuse detection, revocation) but can never authenticate as a user.

```env
SP_JWT_DEVICES_ENABLED=true
SP_JWT_TOKEN_ENDPOINTS_ENABLED=true   # device refresh/revoke use the core token endpoints
```

The tables (`sp_jwt_devices`, `sp_jwt_device_users`) are always migrated and stay empty until the module is enabled. Routes, the `sp.jwt.device` and `sp.jwt.any` middleware and the `sp-jwt-devices` rate limiter exist only while it is enabled.

## Routes

| Route | Purpose |
|---|---|
| `POST /auth/devices` | Register. Body: `platform` (`web`, `ios`, `android`, `desktop`), optional `name`, `client_version`, `attestation`. Returns `201` with `device_id`, `device_credential` (shown **once**; only its hash is stored), and a device token pair. Throttled per IP (`SP_JWT_DEVICES_RATE_LIMIT`, default 10/min). |
| `POST /auth/devices/token` | Exchange `credential` (`{device_id}.{secret}`) for a fresh device token pair, for example after the refresh token expired. Throttled. |

Refreshing and revoking device sessions use `POST /auth/token/refresh` and `/auth/token/revoke`. The refresh-token cookie transport applies to device tokens too.

## Middleware

- `sp.jwt.device` — a valid, non-revoked device token; the device is available as `$request->attributes->get('sp_device')`.
- `sp.jwt.any` — a user token (configured guard) **or** a device token.
- `sp.jwt` and the `sp-jwt` guard reject device tokens. The guard checks that the token's `user_type` matches the user provider's model.

## Upgrade a guest device to a user

```php
use Sopheak\JwtAuth\Services\DeviceService;

$pair = app(DeviceService::class)->upgradeToUser($device, $user); // after your own login check
```

The device's own guest tokens are revoked (`endGuestSessions: false` keeps them), the user is linked to the device, and the user's token pair carries `device_id` / `device_name`. Other sessions are never touched. Move guest data (a cart, say) in a `DeviceUpgradedToUser` listener.

## Managing devices

```php
$devices = app(DeviceService::class);
$devices->devicesForUser($user);                  // devices the user is signed in on
$devices->revokeForUser($user, $device->public_id); // sign the user out of one device
$devices->revoke($device->public_id);             // revoke the device and every token issued on it
```

Events: `DeviceRegistered`, `DeviceUpgradedToUser`, `DeviceRevoked` (never contain the credential).

## Attestation (optional)

Bind your own `Sopheak\JwtAuth\Contracts\AttestationVerifier` (Apple App Attest, Google Play Integrity). Without one, attestation data is ignored and `attested_at` stays `null`. With `SP_JWT_DEVICES_ATTESTATION_REQUIRED=true` and no verifier bound, registration **fails closed**, and `sp-jwt-auth:validate` reports it. A browser-held `web` credential is only as safe as wherever the browser keeps it.

## Maintenance

```bash
php artisan sp-jwt-auth:prune-devices --inactive-days=180 --chunk=1000 --dry-run
```

Deletes devices that were last seen (or, if never seen, created) before the cutoff, plus revoked devices older than the cutoff, together with their own tokens. User tokens issued on those devices are untouched.
