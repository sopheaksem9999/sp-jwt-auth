# Client Installation Guide (for agents and Boot)

Target audience: an AI agent (Laravel Boot, Claude Code, opencode, etc.) installing `sopheak/sp-jwt-auth` into a fresh Laravel 12/13 client application.

## Requirements

- PHP 8.3+, Laravel 12/13, `composer` available.

## Install steps (in order)

1. **Require the package**

   ```bash
   composer require sopheak/sp-jwt-auth:^0.1
   ```

2. **Publish + configure**

   ```bash
   php artisan sp-jwt-auth:setup --keys
   ```

   This publishes `config/sp-jwt-auth.php` and the package migrations, adds the `sp-jwt` API guard to `config/auth.php` (idempotent, safe to run again), generates local PEM signing keys via `sp-jwt-auth:keys --generate --pem`, and ensures `SP_JWT_REFRESH_HASH_KEY` exists in `.env`.

   Flags:
   - `--force` — overwrite published files and regenerate keys (use only on fresh apps).
   - `--skip-migrations` — publish config only; migrations will not be copied.
   - `--skip-auth-guard` — do not patch `config/auth.php`.

   Alternative minimal publish (no guard patching, no keys):

   ```bash
   php artisan sp-jwt-auth:install --keys
   ```

3. **Migrate**

   ```bash
   php artisan migrate
   ```

   > If the app's `users` table uses a **UUID primary key** instead of an integer id, set `SP_JWT_ID_TYPE=uuid` in `.env` **before the first `migrate`** — the package's `user_id` columns are then created as `uuid` instead of `unsignedBigInteger` (default). Changing it later does not alter already-migrated tables.

4. **Verify**

   ```bash
   php artisan sp-jwt-auth:validate --json
   ```

   Exit code 0 + `{"status":"ok"}` means the client is ready. Exit code 1 prints a JSON `errors` list; run with `--fix` to attempt safe repairs (publish missing scaffolding, patch `config/auth.php`).

## Expected client changes

- `config/sp-jwt-auth.php` — published; `keys.items.<kid>.private_key_path`/`public_key_path` point at the generated PEM files (default `storage/app/sp-jwt-auth/`).
- `config/auth.php` — `guards.api` uses `driver => sp-jwt` (added by `setup` unless `--skip-auth-guard`).
- `.env` — `SP_JWT_REFRESH_HASH_KEY` (random 64-hex secret) added by `setup`.
- `database/migrations/` — `2026_06_11_*.php` package tables.

## User model integration (required for auth)

Add the package trait to the app's `User` model:

```php
use Sopheak\JwtAuth\Traits\HasJwtTokens;

class User extends Authenticatable
{
    use HasJwtTokens;
}
```

The trait provides `withAccessToken(JwtAccessToken)`, `token(): ?JwtAccessToken`, and `tokenCan(string $scope): bool` for the authenticated request.

## Optional modules (do not enable unless requested)

- **API keys** — `AuthenticateApiKey` middleware; `sp-jwt-auth.api_keys.enabled`.
- **OAuth server** — requires `league/oauth2-server`; `routes/oauth.php`, `AuthenticateOAuthToken` middleware.
- **Socialite external identities** — requires `laravel/socialite`; `ExternalIdentityStore`.
- **MFA / OTP / email verification / password reset** — toggles under `sp-jwt-auth.mfa.*`, `sp-jwt-auth.otp.*`, `sp-jwt-auth.email_verification.*`; brokers in `Sopheak\JwtAuth\Services`.

## Optional module: first-factor OTP

Sign-in / sign-up via phone (SMS) or email OTP. Enabled with `SP_JWT_FFOTP_ENABLED=true`.

1. Enable the toggle and set limits in `config/sp-jwt-auth.php` (`first_factor_otp` section).
2. Bind a `FirstFactorUserResolver` implementation in the app's service provider (resolve an existing user by destination, or create one; return null to reject) — or use the built-in default resolver (below) and skip this step.
3. Bind an `OtpChannelSender` to deliver codes (use `OtpMessageFormatter::format()` with the config `message_template` for byte-exact SMS copy).
4. Optionally configure `purposes`, `requested_types`, `test_mode` + `test_code` (dev/staging only).
5. Endpoints: `POST /otp/request`, `POST /otp/resend`, `POST /otp/resend-by-destination`, `POST /otp/verify` (429 with `Retry-After` when limits hit).

### One-time configuration (default resolver)

To resolve-or-create users without writing any resolver code, configure `user_model` (and optionally the column mapping):

```php
'user_model' => App\Models\User::class,          // SP_JWT_FFOTP_USER_MODEL
'destination_columns' => ['phone' => 'phone', 'email' => 'email'],
'requested_type_column' => 'type',               // SP_JWT_FFOTP_REQUESTED_TYPE_COLUMN
'response_envelope' => 'laravel',                // SP_JWT_FFOTP_RESPONSE_ENVELOPE: raw|laravel|CustomEnvelopeClass
'user_resource' => App\Http\Resources\UserResource::class, // optional invokable; default: model toArray()
```

The default resolver finds users by the destination column (`email`/`phone`, lowercased / E.164 normalized) and creates missing users with `requested_type` written to the configured column (duplicate-key races re-resolve the existing row). Other NOT NULL user columns without defaults must be nullable or have DB defaults. Bind your own `FirstFactorUserResolver` implementation to override it entirely.

## Optional module: JWT token endpoints

Enable `SP_JWT_TOKEN_ENDPOINTS_ENABLED=true` to expose `POST /auth/token/refresh` (unauthenticated; rotates a JWT-native refresh token pair, generic 401 on invalid/reused tokens) and `POST /auth/token/revoke` (authenticated; revokes the current access token's whole session). No OAuth client required — these operate on the JWT-native `sp_jwt_access_tokens` / `sp_jwt_refresh_tokens` tables. Response wrapping is configurable via `token_endpoints.response_envelope` (`SP_JWT_TOKEN_ENDPOINTS_RESPONSE_ENVELOPE`: `raw` | `laravel` | custom class).

## Security rules for agents

- Never log tokens, secrets, or private keys.
- Never use `APP_KEY` as a JWT signing key.
- Do not change `hash_keys` material after tokens are issued (refresh validation breaks).
- `SP_JWT_REFRESH_HASH_KEY` must be 64 hex characters and kept secret.
