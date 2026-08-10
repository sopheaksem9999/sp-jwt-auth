# Auth Modules & Fixes — Phased Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver three client-review items in dependency order: (1) fix the `parseRefreshToken()` PostgreSQL crash on non-UUID ids, (2) add the optional config-gated first-factor OTP flow (`request` / `resend` / `verify`, resolve-or-create), (3) add native JWT refresh/revoke HTTP endpoints.

**Architecture:** Phase 1 is a one-line guard in `JwtTokenService::parseRefreshToken()` (UUID check → generic 401). Phase 2 adds a `FirstFactorOtpBroker` decoupled from `MfaChallenge`, backed by a new `sp_jwt_first_factor_otp_codes` table (digest-only storage via the existing `SecretHasher`), with user resolve-or-create delegated to a new `FirstFactorUserResolver` contract and HTTP endpoints in `routes/otp.php` behind `sp-jwt-auth.first_factor_otp.enabled` using Laravel's built-in `RateLimiter`. Phase 3 adds `routes/token.php` (`POST /auth/token/refresh`, `POST /auth/token/revoke`) that call the existing `JwtTokenService::rotateRefreshToken()` / `revokeSession()` behind a config toggle.

**Tech Stack:** PHP 8.3+, Laravel 12/13, `firebase/php-jwt`, Orchestra Testbench, PHPUnit. No new runtime dependencies.

**Scope decision (approved):** The OTP feature is the documented exception to the package's "no password login / registration" non-goal — it is auth-method infrastructure; the app owns actual user creation via `FirstFactorUserResolver`.

---

## Phases

| Phase | Item | Rationale | Tasks |
|---|---|---|---|
| **1** | Bug fix: non-UUID refresh token → PDOException (PostgreSQL) | Tiny, high-impact; unblocks correct 401 behavior everywhere | 1 |
| **2** | Feature: first-factor OTP sign-in/sign-up | Large feature, independent of Phase 1 | 2-10 |
| **3** | Feature: native refresh/revoke HTTP endpoints | Small feature; depends on Phase 1 (refresh endpoint must 401 cleanly on malformed tokens) | 11-12 |

## File Structure

| File | Responsibility | Phase |
|---|---|---|
| `src/Services/JwtTokenService.php` | UUID guard in `parseRefreshToken()` | 1 |
| `config/sp-jwt-auth.php` | Add `first_factor_otp` + `token_endpoints` config sections | 2, 3 |
| `database/migrations/2026_08_10_000001_create_sp_jwt_first_factor_otp_codes_table.php` | New table (digest-only storage) | 2 |
| `src/Models/FirstFactorOtpCode.php` | Eloquent model for the table | 2 |
| `src/Contracts/FirstFactorUserResolver.php` | App-owned resolve-or-create contract | 2 |
| `src/DTO/FirstFactorVerification.php` | `{user, TokenPair}` result of a successful verify | 2 |
| `src/Support/OtpMessageFormatter.php` | Config-driven `{code}`/`{ttl}` message templates | 2 |
| `src/Events/FirstFactorOtpVerified.php` | Fired on successful verify (carries `FirstFactorOtpCode`) | 2 |
| `src/Events/FirstFactorOtpFailed.php` | Fired on wrong code | 2 |
| `src/Events/FirstFactorOtpLocked.php` | Fired when attempts exhausted | 2 |
| `src/Events/FirstFactorOtpExpired.php` | Fired on expiry / prior-challenge invalidation | 2 |
| `src/Services/FirstFactorOtpBroker.php` | `request()` / `resend()` / `verify()` core logic | 2 |
| `routes/otp.php` | OTP HTTP endpoints behind the toggle | 2 |
| `src/CoreSpJwtAuthServiceProvider.php` | Rate limiter definitions + route loading | 2, 3 |
| `routes/token.php` | JWT refresh/revoke HTTP endpoints | 3 |
| `boot.json` | Add `first-factor-otp` optional module entry | 2 |
| `docs/ai/client-install.md`, `docs/ai/commands.md`, `docs/ai/project-context.md`, `AGENTS.md`, `CHANGELOG.md` | Documentation | 2, 3 |

**Events reused as-is (no changes):** `OtpCodeCreated`, `OtpCodeSent`, `OtpCodeResent` (payload: `OtpDispatch`). The existing `OtpCodeVerified/Failed/Locked/Expired` events carry `MfaOtpCode`, so the first-factor flow gets its own four events above.

---

## Phase 1: Bug fix — non-UUID refresh-token id crashes with PDOException

**Reported:** `parseRefreshToken()` (src/Services/JwtTokenService.php:300-309) only checks both parts are non-empty; a non-UUID id passes through and the subsequent `whereKey()` query on the `uuid` `id` column throws `PDOException` on PostgreSQL (SQLite tolerates it — invisible in default tests). Fix: reject non-UUID ids with the same generic 401.

### Task 1: UUID guard in parseRefreshToken()

**Files:**
- Modify: `src/Services/JwtTokenService.php:300-309`
- Test: `tests/Feature/JwtTokenRotationTest.php` (or the existing rotation test file — check `tests/Feature/` for an existing refresh-rotation test class first)

- [ ] **Step 1: Write the failing test**

```php
    public function test_rotating_refresh_token_with_non_uuid_id_returns_401_not_500(): void
    {
        $this->expectException(AuthenticationException::class);

        $this->app->make(JwtTokenService::class)->rotateRefreshToken('not-a-real-token.at-all');
    }
```

(Place inside the existing refresh-rotation test class; import `JwtTokenService` and `AuthenticationException` as needed.)

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter 'rotation|RefreshToken'`
Expected: on SQLite the PDOException may not surface — if the test PASSES on SQLite, force the failure path by temporarily asserting on the internal call, or run against Postgres if configured. Confirm the current behavior throws a non-`AuthenticationException` before moving on.

- [ ] **Step 3: Write minimal implementation**

In `parseRefreshToken()` (src/Services/JwtTokenService.php:302), add the UUID guard after the count check:

```php
    private function parseRefreshToken(string $refreshToken): array
    {
        $parts = explode('.', $refreshToken, 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '' || ! Str::isUuid($parts[0])) {
            throw new AuthenticationException('Unauthenticated.');
        }

        return [$parts[0], $parts[1]];
    }
```

Add the import if missing at the top of `JwtTokenService.php`:

```php
use Illuminate\Support\Str;
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit --filter 'Rotation|RefreshToken'`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add src/Services/JwtTokenService.php tests/Feature/
git commit -m "fix: reject non-UUID refresh-token ids with a generic 401"
```

---

## Phase 2: Feature — first-factor OTP authentication

### Task 2: Config section + boot.json module entry

**Files:**
- Modify: `config/sp-jwt-auth.php`
- Modify: `boot.json`

- [ ] **Step 1: Add the `first_factor_otp` config section**

Append after the `mfa` section in `config/sp-jwt-auth.php`:

```php
    'first_factor_otp' => [
        'enabled' => filter_var(env('SP_JWT_FFOTP_ENABLED', false), FILTER_VALIDATE_BOOL),
        'route_prefix' => env('SP_JWT_FFOTP_ROUTE_PREFIX', 'otp'),
        'digits' => (int) env('SP_JWT_FFOTP_DIGITS', 6),
        'ttl_minutes' => (int) env('SP_JWT_FFOTP_TTL_MINUTES', 5),
        'max_attempts' => (int) env('SP_JWT_FFOTP_MAX_ATTEMPTS', 5),
        'resend_cooldown_seconds' => (int) env('SP_JWT_FFOTP_RESEND_COOLDOWN_SECONDS', 60),
        'default_scopes' => ['*'],
        'purposes' => [],
        'requested_types' => [],
        'test_mode' => filter_var(env('SP_JWT_FFOTP_TEST_MODE', false), FILTER_VALIDATE_BOOL),
        'test_code' => env('SP_JWT_FFOTP_TEST_CODE'),
        'limits' => [
            'request_per_destination' => (int) env('SP_JWT_FFOTP_LIMIT_REQUEST_DESTINATION', 5),
            'request_per_ip' => (int) env('SP_JWT_FFOTP_LIMIT_REQUEST_IP', 20),
            'verify_per_ip' => (int) env('SP_JWT_FFOTP_LIMIT_VERIFY_IP', 30),
            'decay_minutes' => (int) env('SP_JWT_FFOTP_LIMIT_DECAY_MINUTES', 60),
        ],
        'message_template' => [
            'sms' => env('SP_JWT_FFOTP_SMS_TEMPLATE', 'Your {app} verification code is {code}. Valid for {ttl} minutes.'),
            'email' => env('SP_JWT_FFOTP_EMAIL_TEMPLATE', 'Your {app} verification code is {code}. Valid for {ttl} minutes.'),
        ],
    ],
```

- [ ] **Step 2: Add the optional-module entry to `boot.json`**

In the `optional_modules` array (alphabetical order, after `api-keys`):

```json
        {
            "name": "first-factor-otp",
            "note": "First-factor OTP sign-in/sign-up via SMS or email. Enable sp-jwt-auth.first_factor_otp.enabled and implement the FirstFactorUserResolver contract."
        },
```

- [ ] **Step 3: Verify config loads**

Run: `vendor/bin/testbench config:show sp-jwt-auth.first_factor_otp`
Expected: the section prints with defaults (enabled=false).

- [ ] **Step 4: Commit**

```bash
git add config/sp-jwt-auth.php boot.json
git commit -m "feat: add first_factor_otp configuration section"
```

---

### Task 3: Migration + FirstFactorOtpCode model

**Files:**
- Create: `database/migrations/2026_08_10_000001_create_sp_jwt_first_factor_otp_codes_table.php`
- Create: `src/Models/FirstFactorOtpCode.php`

- [ ] **Step 1: Write the migration** (mirror `2026_06_11_000003_create_sp_jwt_account_security_tables.php` style)

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sp_jwt_first_factor_otp_codes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('channel', 30)->index('sp_jwt_ffotp_channel_index');
            $table->string('destination_hash', 128)->index('sp_jwt_ffotp_destination_index');
            $table->string('destination_masked');
            $table->string('purpose', 50)->index('sp_jwt_ffotp_purpose_index');
            $table->string('requested_type', 50)->nullable();
            $table->string('code_hash', 128);
            $table->string('hash_key_id', 100);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('max_attempts')->default(5);
            $table->timestamp('last_sent_at');
            $table->timestamp('expires_at')->index('sp_jwt_ffotp_expiry_index');
            $table->timestamp('verified_at')->nullable()->index('sp_jwt_ffotp_verified_index');
            $table->timestamps();
            $table->index(['destination_hash', 'purpose', 'expires_at'], 'sp_jwt_ffotp_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sp_jwt_first_factor_otp_codes');
    }
};
```

- [ ] **Step 2: Write the model** (mirror `MfaOtpCode` exactly — `src/Models/MfaOtpCode.php`)

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Models;

use Illuminate\Database\Eloquent\Model;

final class FirstFactorOtpCode extends Model
{
    protected $table = 'sp_jwt_first_factor_otp_codes';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'last_sent_at' => 'datetime',
        'verified_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
```

- [ ] **Step 3: Run existing tests to confirm no regression**

Run: `vendor/bin/phpunit --filter 'SetupCommandTest|TokenIssueValidateTest'`
Expected: PASS (the new migration is auto-loaded by `tests/TestCase.php` `loadMigrationsFrom`; `RefreshDatabase` handles it)

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_08_10_000001_create_sp_jwt_first_factor_otp_codes_table.php src/Models/FirstFactorOtpCode.php
git commit -m "feat: add first-factor OTP codes table and model"
```

---

### Task 4: OtpMessageFormatter + FirstFactorUserResolver contract + FirstFactorVerification DTO

**Files:**
- Create: `src/Support/OtpMessageFormatter.php`
- Create: `src/Contracts/FirstFactorUserResolver.php`
- Create: `src/DTO/FirstFactorVerification.php`
- Test: `tests/Unit/OtpMessageFormatterTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sopheak\JwtAuth\Support\OtpMessageFormatter;

final class OtpMessageFormatterTest extends TestCase
{
    public function test_formats_template_with_code_and_ttl(): void
    {
        $message = OtpMessageFormatter::format('Your {app} code is {code}. Valid {ttl} min.', [
            'code' => '123456',
            'ttl' => 5,
            'app' => 'MyApp',
        ]);

        $this->assertSame('Your MyApp code is 123456. Valid 5 min.', $message);
    }

    public function test_leaves_unknown_placeholders_untouched(): void
    {
        $message = OtpMessageFormatter::format('Code {code} {unknown}.', ['code' => '42']);

        $this->assertSame('Code 42 {unknown}.', $message);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter OtpMessageFormatterTest`
Expected: FAIL with `Class "Sopheak\JwtAuth\Support\OtpMessageFormatter" not found`

- [ ] **Step 3: Write minimal implementation**

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Support;

final class OtpMessageFormatter
{
    /**
     * @param array<string, string|int> $values
     */
    public static function format(string $template, array $values): string
    {
        $search = array_map(static fn (string $key): string => sprintf('{%s}', $key), array_keys($values));

        return str_replace($search, array_values($values), $template);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `vendor/bin/phpunit --filter OtpMessageFormatterTest`
Expected: PASS (2 tests)

- [ ] **Step 5: Write the resolver contract** (interface — no test)

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Sopheak\JwtAuth\DTO\OtpDestination;

interface FirstFactorUserResolver
{
    /**
     * Return the existing user for the destination, or null if none exists.
     */
    public function resolve(OtpDestination $destination, string $purpose): ?Authenticatable;

    /**
     * Create a user for the destination. Return null to reject sign-up.
     *
     * @param string|null $requestedType Account type requested by the caller (e.g. "driver", "merchant").
     */
    public function create(OtpDestination $destination, string $purpose, ?string $requestedType): ?Authenticatable;
}
```

- [ ] **Step 6: Write the verification DTO**

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\DTO;

use Illuminate\Contracts\Auth\Authenticatable;

final readonly class FirstFactorVerification
{
    public function __construct(
        public Authenticatable $user,
        public TokenPair $pair,
    ) {
    }
}
```

- [ ] **Step 7: Commit**

```bash
git add tests/Unit/OtpMessageFormatterTest.php src/Support/OtpMessageFormatter.php src/Contracts/FirstFactorUserResolver.php src/DTO/FirstFactorVerification.php
git commit -m "feat: add OTP message formatter, user resolver contract, and verification DTO"
```

---

### Task 5: First-factor OTP events

**Files:**
- Create: `src/Events/FirstFactorOtpVerified.php`
- Create: `src/Events/FirstFactorOtpFailed.php`
- Create: `src/Events/FirstFactorOtpLocked.php`
- Create: `src/Events/FirstFactorOtpExpired.php`

- [ ] **Step 1: Create the four events** (mirror `OtpCodeVerified`, but carry `FirstFactorOtpCode`)

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Events;

use Sopheak\JwtAuth\Models\FirstFactorOtpCode;

final readonly class FirstFactorOtpVerified
{
    public function __construct(public FirstFactorOtpCode $otp)
    {
    }
}
```

Repeat identically for `FirstFactorOtpFailed` and `FirstFactorOtpLocked` (class name swapped only). For `FirstFactorOtpExpired`, accept a string id (mirror `OtpCodeExpired`):

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Events;

final readonly class FirstFactorOtpExpired
{
    public function __construct(public string $otpId)
    {
    }
}
```

- [ ] **Step 2: Verify no regressions**

Run: `vendor/bin/phpunit --filter 'SetupCommandTest|McpServerTest'`
Expected: PASS

- [ ] **Step 3: Commit**

```bash
git add src/Events/FirstFactorOtpVerified.php src/Events/FirstFactorOtpFailed.php src/Events/FirstFactorOtpLocked.php src/Events/FirstFactorOtpExpired.php
git commit -m "feat: add first-factor OTP lifecycle events"
```

---


### Task 6: FirstFactorOtpBroker — request()

**Files:**
- Create: `src/Services/FirstFactorOtpBroker.php`
- Test: `tests/Feature/FirstFactorOtpBrokerTest.php`

- [ ] **Step 1: Write the failing tests** (request behavior only)

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Exceptions\TooManyRequestsException;
use Sopheak\JwtAuth\Contracts\FirstFactorUserResolver;
use Sopheak\JwtAuth\DTO\OtpDestination;
use Sopheak\JwtAuth\Models\FirstFactorOtpCode;
use Sopheak\JwtAuth\Services\FirstFactorOtpBroker;
use Sopheak\JwtAuth\Tests\Fixtures\User;
use Sopheak\JwtAuth\Tests\TestCase;

final class FirstFactorOtpBrokerTest extends TestCase
{
    private function broker(): FirstFactorOtpBroker
    {
        return $this->app->make(FirstFactorOtpBroker::class);
    }

    private function bindResolver(?User $existing = null, ?\Closure $creator = null): void
    {
        $this->app->instance(FirstFactorUserResolver::class, new class ($existing, $creator) implements FirstFactorUserResolver {
            public function __construct(private ?User $existing, private ?\Closure $creator)
            {
            }

            public function resolve(OtpDestination $destination, string $purpose): ?Authenticatable
            {
                return $this->existing;
            }

            public function create(OtpDestination $destination, string $purpose, ?string $requestedType): ?Authenticatable
            {
                return $this->creator ? ($this->creator)($destination, $purpose, $requestedType) : null;
            }
        });
    }

    public function test_request_creates_hashed_code_without_plaintext_in_database(): void
    {
        $dispatch = $this->broker()->request(OtpDestination::email('User@Example.com'), 'login');

        $otp = FirstFactorOtpCode::query()->findOrFail($dispatch->otpId);

        $this->assertSame('u***@example.com', $otp->destination_masked);
        $this->assertNotSame($dispatch->plaintextCode, $otp->code_hash);
        $this->assertNotNull($otp->hash_key_id);
        $this->assertNull($otp->verified_at);
    }

    public function test_request_invalidates_prior_active_challenge_for_same_destination_and_purpose(): void
    {
        $first = $this->broker()->request(OtpDestination::phone('+85512345678'), 'login');

        $this->broker()->request(OtpDestination::phone('+85512345678'), 'login');

        $this->assertTrue(FirstFactorOtpCode::query()->findOrFail($first->otpId)->expires_at->isPast());
    }

    public function test_request_does_not_invalidate_other_purpose_or_destination(): void
    {
        $first = $this->broker()->request(OtpDestination::phone('+85512345678'), 'login');
        $this->broker()->request(OtpDestination::phone('+85587654321'), 'login');
        $this->broker()->request(OtpDestination::phone('+85512345678'), 'register');

        $this->assertFalse(FirstFactorOtpCode::query()->findOrFail($first->otpId)->expires_at->isPast());
    }

    public function test_request_respects_resend_cooldown(): void
    {
        $this->broker()->request(OtpDestination::phone('+85512345678'), 'login');

        try {
            $this->broker()->request(OtpDestination::phone('+85512345678'), 'login');
            $this->fail('Expected TooManyRequestsException');
        } catch (TooManyRequestsException $e) {
            $this->assertGreaterThan(0, $e->retryAfter);
        }
    }

    public function test_request_uses_test_code_when_test_mode_enabled(): void
    {
        config()->set('sp-jwt-auth.first_factor_otp.test_mode', true);
        config()->set('sp-jwt-auth.first_factor_otp.test_code', '424242');

        $dispatch = $this->broker()->request(OtpDestination::email('a@b.com'), 'login');

        $this->assertSame('424242', $dispatch->plaintextCode);
    }

    public function test_request_with_unknown_purpose_is_rejected(): void
    {
        config()->set('sp-jwt-auth.first_factor_otp.purposes', ['login']);

        $this->expectException(\InvalidArgumentException::class);

        $this->broker()->request(OtpDestination::email('a@b.com'), 'register');
    }
}
```

Verify `OtpDispatch` field names before writing the test — open `src/DTO/OtpDispatch.php`; adjust `otpId`/`plaintextCode` if the actual names differ.

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit --filter FirstFactorOtpBrokerTest`
Expected: FAIL with `Class "Sopheak\JwtAuth\Services\FirstFactorOtpBroker" not found`

- [ ] **Step 3: Write minimal implementation** (request part only; `resend`/`verify` come in Tasks 7-8)

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Services;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Exceptions\TooManyRequestsException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Sopheak\JwtAuth\Contracts\FirstFactorUserResolver;
use Sopheak\JwtAuth\Contracts\OtpChannelSender;
use Sopheak\JwtAuth\DTO\FirstFactorVerification;
use Sopheak\JwtAuth\DTO\OtpDestination;
use Sopheak\JwtAuth\DTO\OtpDispatch;
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Events\FirstFactorOtpExpired;
use Sopheak\JwtAuth\Events\FirstFactorOtpFailed;
use Sopheak\JwtAuth\Events\FirstFactorOtpLocked;
use Sopheak\JwtAuth\Events\FirstFactorOtpVerified;
use Sopheak\JwtAuth\Events\OtpCodeCreated;
use Sopheak\JwtAuth\Events\OtpCodeResent;
use Sopheak\JwtAuth\Events\OtpCodeSent;
use Sopheak\JwtAuth\Models\FirstFactorOtpCode;
use Sopheak\JwtAuth\Security\SecretHasher;

final readonly class FirstFactorOtpBroker
{
    public function __construct(
        private SecretHasher $hasher,
        private FirstFactorUserResolver $resolver,
        private JwtTokenService $jwt,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function request(OtpDestination $destination, string $purpose, ?string $requestedType = null, array $options = []): OtpDispatch
    {
        $this->assertPurposeAllowed($purpose);
        $this->assertRequestedTypeAllowed($requestedType);

        $otp = $this->latestActive($destination, $purpose);

        if ($otp instanceof FirstFactorOtpCode && ! $otp->last_sent_at->addSeconds($this->cooldownSeconds())->isPast()) {
            $retryAfter = (int) $otp->last_sent_at->addSeconds($this->cooldownSeconds())->diffInSeconds(now(), false);

            throw new TooManyRequestsException(null, null, max(1, $retryAfter));
        }

        $this->invalidatePrior($destination, $purpose);

        $plaintext = $this->generateCode((int) config('sp-jwt-auth.first_factor_otp.digits', 6));
        $hash = $this->hasher->hash($plaintext);
        $destinationHash = $this->hasher->hash($destination->normalizedDestination);

        $otp = FirstFactorOtpCode::query()->create([
            'id' => (string) Str::uuid(),
            'channel' => $destination->channel,
            'destination_hash' => $destinationHash['hash'],
            'destination_masked' => $destination->maskedDestination,
            'purpose' => $purpose,
            'requested_type' => $requestedType,
            'code_hash' => $hash['hash'],
            'hash_key_id' => $hash['hash_key_id'],
            'max_attempts' => (int) config('sp-jwt-auth.first_factor_otp.max_attempts', 5),
            'last_sent_at' => now(),
            'expires_at' => now()->addMinutes((int) config('sp-jwt-auth.first_factor_otp.ttl_minutes', 5)),
        ]);

        $dispatch = new OtpDispatch($otp->id, $otp->id, $plaintext, $destination, $otp);

        if (app()->bound(OtpChannelSender::class)) {
            app(OtpChannelSender::class)->send($dispatch);
            Event::dispatch(new OtpCodeSent($dispatch));
        }

        Event::dispatch(new OtpCodeCreated($dispatch));

        return $dispatch;
    }
```

Private helpers (place at the end of the class):

```php
    private function assertPurposeAllowed(string $purpose): void
    {
        $allowed = config('sp-jwt-auth.first_factor_otp.purposes', []);

        if ($allowed !== [] && ! in_array($purpose, $allowed, true)) {
            throw new InvalidArgumentException(sprintf('Purpose [%s] is not allowed.', $purpose));
        }
    }

    private function assertRequestedTypeAllowed(?string $requestedType): void
    {
        $allowed = config('sp-jwt-auth.first_factor_otp.requested_types', []);

        if ($requestedType !== null && $allowed !== [] && ! in_array($requestedType, $allowed, true)) {
            throw new InvalidArgumentException(sprintf('Requested type [%s] is not allowed.', $requestedType));
        }
    }

    private function latestActive(OtpDestination $destination, string $purpose): ?FirstFactorOtpCode
    {
        $destinationHash = $this->hasher->hash($destination->normalizedDestination);

        return FirstFactorOtpCode::query()
            ->where('destination_hash', $destinationHash['hash'])
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();
    }

    private function invalidatePrior(OtpDestination $destination, string $purpose): void
    {
        $destinationHash = $this->hasher->hash($destination->normalizedDestination);

        FirstFactorOtpCode::query()
            ->where('destination_hash', $destinationHash['hash'])
            ->where('purpose', $purpose)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);
    }

    private function generateCode(int $digits): string
    {
        $testCode = config('sp-jwt-auth.first_factor_otp.test_mode')
            ? config('sp-jwt-auth.first_factor_otp.test_code')
            : null;

        if (is_string($testCode) && $testCode !== '') {
            return $testCode;
        }

        $min = 10 ** max(1, $digits - 1);
        $max = (10 ** $digits) - 1;

        return (string) random_int($min, $max);
    }

    private function cooldownSeconds(): int
    {
        return (int) config('sp-jwt-auth.first_factor_otp.resend_cooldown_seconds', 60);
    }
}
```

Note: `FirstFactorOtpBroker` needs the container to resolve its constructor deps. The `FirstFactorUserResolver` interface must be bound by the consuming app; for tests, `bindResolver()` uses `app()->instance()`. `JwtTokenService` is already singleton-bound in the provider.

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit --filter FirstFactorOtpBrokerTest`
Expected: 6 tests PASS

- [ ] **Step 5: Commit**

```bash
git add src/Services/FirstFactorOtpBroker.php tests/Feature/FirstFactorOtpBrokerTest.php
git commit -m "feat: add first-factor OTP request with cooldown and prior-challenge invalidation"
```

---

### Task 7: FirstFactorOtpBroker — resend()

**Files:**
- Modify: `src/Services/FirstFactorOtpBroker.php`
- Modify: `tests/Feature/FirstFactorOtpBrokerTest.php`

- [ ] **Step 1: Write the failing tests** (append to `FirstFactorOtpBrokerTest`)

```php
    public function test_resend_creates_new_code_and_expires_previous(): void
    {
        $first = $this->broker()->request(OtpDestination::phone('+85512345678'), 'login');

        $resent = $this->broker()->resend($first->otpId, OtpDestination::phone('+85512345678'));

        $this->assertNotSame($first->otpId, $resent->otpId);
        $this->assertTrue(FirstFactorOtpCode::query()->findOrFail($first->otpId)->expires_at->isPast());
    }

    public function test_resend_respects_cooldown(): void
    {
        $first = $this->broker()->request(OtpDestination::phone('+85512345678'), 'login');

        try {
            $this->broker()->resend($first->otpId, OtpDestination::phone('+85512345678'));
            $this->fail('Expected TooManyRequestsException');
        } catch (TooManyRequestsException $e) {
            $this->assertGreaterThan(0, $e->retryAfter);
        }
    }

    public function test_resend_rejects_mismatched_destination(): void
    {
        $first = $this->broker()->request(OtpDestination::phone('+85512345678'), 'login');

        $this->expectException(\InvalidArgumentException::class);

        $this->broker()->resend($first->otpId, OtpDestination::phone('+85599999999'));
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit --filter FirstFactorOtpBrokerTest`
Expected: FAIL with `Call to undefined method FirstFactorOtpBroker::resend()`

- [ ] **Step 3: Write minimal implementation** (add to `FirstFactorOtpBroker`)

```php
    public function resend(string $otpId, OtpDestination $destination): OtpDispatch
    {
        $otp = FirstFactorOtpCode::query()->findOrFail($otpId);

        $destinationHash = $this->hasher->hash($destination->normalizedDestination);

        if (! hash_equals($otp->destination_hash, $destinationHash['hash'])) {
            throw new InvalidArgumentException('Destination does not match the challenge.');
        }

        if (! $otp->last_sent_at->addSeconds($this->cooldownSeconds())->isPast()) {
            $retryAfter = (int) $otp->last_sent_at->addSeconds($this->cooldownSeconds())->diffInSeconds(now(), false);

            throw new TooManyRequestsException(null, null, max(1, $retryAfter));
        }

        $dispatch = $this->request($destination, $otp->purpose, $otp->requested_type);

        Event::dispatch(new OtpCodeResent($dispatch));

        return $dispatch;
    }
```

Note: `request()` re-invalidates the prior active challenge (the one being resent) — covered by `test_resend_creates_new_code_and_expires_previous`.

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit --filter FirstFactorOtpBrokerTest`
Expected: 9 tests PASS

- [ ] **Step 5: Commit**

```bash
git add src/Services/FirstFactorOtpBroker.php tests/Feature/FirstFactorOtpBrokerTest.php
git commit -m "feat: add first-factor OTP resend with cooldown and destination check"
```

---

### Task 8: FirstFactorOtpBroker — verify() (atomic consume + resolve-or-create)

**Files:**
- Modify: `src/Services/FirstFactorOtpBroker.php`
- Modify: `tests/Feature/FirstFactorOtpBrokerTest.php`
- Modify: `tests/TestCase.php` (add `email_verified_at` column to users table)
- Modify: `tests/Fixtures/User.php` (add `email_verified_at` to fillable — check the file first)

- [ ] **Step 1: Write the failing tests** (append to `FirstFactorOtpBrokerTest`)

```php
    public function test_verify_signs_in_existing_user_and_issues_token_pair(): void
    {
        $user = $this->createUser();
        $this->bindResolver(existing: $user);

        $dispatch = $this->broker()->request(OtpDestination::email('a@b.com'), 'login');

        $verification = $this->broker()->verify($dispatch->otpId, $dispatch->plaintextCode);

        $this->assertSame($user->getAuthIdentifier(), $verification->user->getAuthIdentifier());
        $this->assertNotEmpty($verification->pair->accessToken);
        $this->assertNotEmpty($verification->pair->refreshToken);
        $this->assertNotNull(FirstFactorOtpCode::query()->findOrFail($dispatch->otpId)->verified_at);
    }

    public function test_verify_creates_user_when_none_exists_and_marks_email_verified(): void
    {
        $this->bindResolver(creator: function (OtpDestination $destination): User {
            $user = new User();
            $user->forceFill([
                'name' => 'New User',
                'email' => $destination->normalizedDestination,
                'password' => bcrypt('unused'),
            ])->save();

            return $user;
        });

        $dispatch = $this->broker()->request(OtpDestination::email('new@example.com'), 'login', 'driver');

        $verification = $this->broker()->verify($dispatch->otpId, $dispatch->plaintextCode);

        $this->assertSame('new@example.com', $verification->user->getAttribute('email'));
        $this->assertNotNull($verification->user->getAttribute('email_verified_at'));
        $this->assertNotEmpty($verification->pair->accessToken);
    }

    public function test_verify_wrong_code_increments_attempts_and_fails(): void
    {
        $this->bindResolver(existing: $this->createUser());

        $dispatch = $this->broker()->request(OtpDestination::email('a@b.com'), 'login');

        try {
            $this->broker()->verify($dispatch->otpId, '000000');
            $this->fail('Expected AuthenticationException');
        } catch (AuthenticationException) {
        }

        $this->assertSame(1, FirstFactorOtpCode::query()->findOrFail($dispatch->otpId)->attempts);
        $this->assertNull(FirstFactorOtpCode::query()->findOrFail($dispatch->otpId)->verified_at);
    }

    public function test_verify_locks_after_max_attempts(): void
    {
        $this->bindResolver(existing: $this->createUser());

        $dispatch = $this->broker()->request(OtpDestination::email('a@b.com'), 'login');

        for ($i = 0; $i < 5; $i++) {
            try {
                $this->broker()->verify($dispatch->otpId, '000000');
            } catch (AuthenticationException) {
            }
        }

        $this->expectException(AuthenticationException::class);

        $this->broker()->verify($dispatch->otpId, $dispatch->plaintextCode);
    }

    public function test_verify_expired_code_fails(): void
    {
        $this->bindResolver(existing: $this->createUser());

        $dispatch = $this->broker()->request(OtpDestination::email('a@b.com'), 'login');

        FirstFactorOtpCode::query()->whereKey($dispatch->otpId)->update(['expires_at' => now()->subMinute()]);

        $this->expectException(AuthenticationException::class);

        $this->broker()->verify($dispatch->otpId, $dispatch->plaintextCode);
    }

    public function test_verify_second_verification_against_same_challenge_fails(): void
    {
        $this->bindResolver(existing: $this->createUser());

        $dispatch = $this->broker()->request(OtpDestination::email('a@b.com'), 'login');

        $this->broker()->verify($dispatch->otpId, $dispatch->plaintextCode);

        $this->expectException(AuthenticationException::class);

        $this->broker()->verify($dispatch->otpId, $dispatch->plaintextCode);
    }

    public function test_verify_rejects_when_resolver_returns_no_user(): void
    {
        $this->bindResolver();

        $dispatch = $this->broker()->request(OtpDestination::email('a@b.com'), 'login');

        $this->expectException(AuthenticationException::class);

        $this->broker()->verify($dispatch->otpId, $dispatch->plaintextCode);
    }
```

Also add `email_verified_at` to the test harness users table in `tests/TestCase.php::defineDatabaseMigrations()`:

```php
            $table->timestamp('email_verified_at')->nullable();
```

and add `'email_verified_at'` to `$fillable` in `tests/Fixtures/User.php` (check the file first; the fixture may already be guarded — use `$guarded = []` if that is the case).

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit --filter FirstFactorOtpBrokerTest`
Expected: FAIL on the new verify tests (`Call to undefined method FirstFactorOtpBroker::verify()`)

- [ ] **Step 3: Write minimal implementation** (add to `FirstFactorOtpBroker`)

```php
    public function verify(string $otpId, string $code): FirstFactorVerification
    {
        $otp = $this->consume($otpId, $code);
        $destination = new OtpDestination($otp->channel, '', $otp->destination_masked);

        $user = $this->resolver->resolve($destination, $otp->purpose)
            ?? $this->resolver->create($destination, $otp->purpose, $otp->requested_type);

        if (! $user instanceof Authenticatable) {
            throw new AuthenticationException('OTP verification failed.');
        }

        $this->markDestinationVerified($user, $otp->channel);

        $context = TokenContext::make()->scopes((array) config('sp-jwt-auth.first_factor_otp.default_scopes', ['*']));

        return new FirstFactorVerification($user, $this->jwt->issueTokenPair($user, $context));
    }

    private function consume(string $otpId, string $code): FirstFactorOtpCode
    {
        return DB::transaction(function () use ($otpId, $code): FirstFactorOtpCode {
            $otp = FirstFactorOtpCode::query()->whereKey($otpId)->lockForUpdate()->first();

            if (! $otp instanceof FirstFactorOtpCode || $otp->verified_at !== null || $otp->expires_at->isPast() || $otp->attempts >= $otp->max_attempts) {
                if ($otp instanceof FirstFactorOtpCode && $otp->attempts >= $otp->max_attempts) {
                    Event::dispatch(new FirstFactorOtpLocked($otp));
                }

                throw new AuthenticationException('OTP is invalid.');
            }

            if (! $this->hasher->verify($code, $otp->code_hash, $otp->hash_key_id)) {
                $otp->increment('attempts');
                $otp->refresh();

                Event::dispatch(new FirstFactorOtpFailed($otp));

                if ($otp->attempts >= $otp->max_attempts) {
                    Event::dispatch(new FirstFactorOtpLocked($otp));
                }

                throw new AuthenticationException('OTP is invalid.');
            }

            $otp->forceFill(['verified_at' => now()])->save();

            Event::dispatch(new FirstFactorOtpVerified($otp));

            return $otp;
        });
    }

    private function markDestinationVerified(Authenticatable $user, string $channel): void
    {
        if (! $user instanceof \Illuminate\Database\Eloquent\Model) {
            return;
        }

        $column = $channel === 'email' ? 'email_verified_at' : 'phone_verified_at';

        if (! Schema::hasColumn($user->getTable(), $column)) {
            return;
        }

        $user->forceFill([$column => now()])->save();
    }
```

The `TokenContext::make()->scopes(...)` chaining exists — verify against `src/DTO/TokenContext.php` (it has `make()`, `scopes()`, `replaceScopes()`).

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit --filter FirstFactorOtpBrokerTest`
Expected: all tests PASS (16 total)

- [ ] **Step 5: Commit**

```bash
git add src/Services/FirstFactorOtpBroker.php tests/Feature/FirstFactorOtpBrokerTest.php tests/TestCase.php tests/Fixtures/User.php
git commit -m "feat: add atomic first-factor OTP verify with resolve-or-create"
```

---

### Task 9: HTTP endpoints + rate limiting

**Files:**
- Create: `routes/otp.php`
- Modify: `src/CoreSpJwtAuthServiceProvider.php`
- Test: `tests/Feature/FirstFactorOtpHttpTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Illuminate\Contracts\Auth\Authenticatable;
use Sopheak\JwtAuth\Contracts\FirstFactorUserResolver;
use Sopheak\JwtAuth\DTO\OtpDestination;
use Sopheak\JwtAuth\Tests\Fixtures\User;
use Sopheak\JwtAuth\Tests\TestCase;

final class FirstFactorOtpHttpTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('sp-jwt-auth.first_factor_otp.enabled', true);
    }

    private function bindResolver(User $user): void
    {
        $this->app->instance(FirstFactorUserResolver::class, new class ($user) implements FirstFactorUserResolver {
            public function __construct(private User $user)
            {
            }

            public function resolve(OtpDestination $destination, string $purpose): ?Authenticatable
            {
                return $this->user;
            }

            public function create(OtpDestination $destination, string $purpose, ?string $requestedType): ?Authenticatable
            {
                return null;
            }
        });
    }

    public function test_request_endpoint_returns_masked_destination(): void
    {
        $this->bindResolver($this->createUser());

        $response = $this->postJson('/otp/request', [
            'destination' => 'user@example.com',
            'channel' => 'email',
            'purpose' => 'login',
        ]);

        $response->assertStatus(202);
        $response->assertJsonPath('destination_masked', 'u***@example.com');
        $response->assertJsonMissing(['code']);
    }

    public function test_request_endpoint_rejects_invalid_channel(): void
    {
        $this->bindResolver($this->createUser());

        $this->postJson('/otp/request', [
            'destination' => 'user@example.com',
            'channel' => 'carrier-pigeon',
            'purpose' => 'login',
        ])->assertStatus(422);
    }

    public function test_verify_endpoint_returns_token_pair(): void
    {
        config()->set('sp-jwt-auth.first_factor_otp.test_mode', true);
        config()->set('sp-jwt-auth.first_factor_otp.test_code', '424242');

        $this->bindResolver($this->createUser());

        $request = $this->postJson('/otp/request', [
            'destination' => 'user@example.com',
            'channel' => 'email',
            'purpose' => 'login',
        ]);

        $response = $this->postJson('/otp/verify', [
            'otp_id' => $request->json('otp_id'),
            'code' => '424242',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['access_token', 'refresh_token', 'token_type', 'expires_in']);
    }

    public function test_request_endpoint_rate_limits_per_destination_with_retry_after(): void
    {
        $this->bindResolver($this->createUser());

        config()->set('sp-jwt-auth.first_factor_otp.limits.request_per_destination', 2);

        $payload = ['destination' => 'user@example.com', 'channel' => 'email', 'purpose' => 'login'];

        $this->postJson('/otp/request', $payload)->assertStatus(202);
        $this->postJson('/otp/request', $payload)->assertStatus(202);

        $this->postJson('/otp/request', $payload)->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_otp_routes_are_disabled_when_toggle_is_off(): void
    {
        config()->set('sp-jwt-auth.first_factor_otp.enabled', false);

        $this->postJson('/otp/request', ['destination' => 'a@b.com', 'channel' => 'email', 'purpose' => 'login'])
            ->assertNotFound();
    }
}
```

Note on the rate-limit test: the per-IP limit (default 20) will also apply during the test — keep the hit count under it (3 requests here) or bump `request_per_ip` in the test if other tests in the same run share the IP cache key.

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit --filter FirstFactorOtpHttpTest`
Expected: FAIL (404 / route not found)

- [ ] **Step 3: Write the routes file**

```php
<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Sopheak\JwtAuth\DTO\OtpDestination;
use Sopheak\JwtAuth\Services\FirstFactorOtpBroker;

Route::prefix((string) config('sp-jwt-auth.first_factor_otp.route_prefix', 'otp'))->group(function (): void {
    Route::post('/request', static function (Request $request, FirstFactorOtpBroker $broker) {
        $data = $request->validate([
            'destination' => ['required', 'string', 'max:255'],
            'channel' => ['required', 'string', 'in:email,sms'],
            'purpose' => ['required', 'string', 'max:50'],
            'requested_type' => ['sometimes', 'string', 'max:50'],
        ]);

        $destination = $data['channel'] === 'email'
            ? OtpDestination::email($data['destination'])
            : OtpDestination::phone($data['destination']);

        $dispatch = $broker->request(
            $destination,
            $data['purpose'],
            is_string($data['requested_type'] ?? null) ? $data['requested_type'] : null,
        );

        return response()->json([
            'otp_id' => $dispatch->otpId,
            'destination_masked' => $destination->maskedDestination,
            'expires_in' => (int) config('sp-jwt-auth.first_factor_otp.ttl_minutes', 5) * 60,
        ], 202);
    })->middleware('throttle:sp-jwt-ffotp-request');

    Route::post('/resend', static function (Request $request, FirstFactorOtpBroker $broker) {
        $data = $request->validate([
            'otp_id' => ['required', 'string'],
            'destination' => ['required', 'string', 'max:255'],
            'channel' => ['required', 'string', 'in:email,sms'],
        ]);

        $destination = $data['channel'] === 'email'
            ? OtpDestination::email($data['destination'])
            : OtpDestination::phone($data['destination']);

        $dispatch = $broker->resend($data['otp_id'], $destination);

        return response()->json([
            'otp_id' => $dispatch->otpId,
            'destination_masked' => $destination->maskedDestination,
            'expires_in' => (int) config('sp-jwt-auth.first_factor_otp.ttl_minutes', 5) * 60,
        ], 202);
    })->middleware('throttle:sp-jwt-ffotp-request');

    Route::post('/verify', static function (Request $request, FirstFactorOtpBroker $broker) {
        $data = $request->validate([
            'otp_id' => ['required', 'string'],
            'code' => ['required', 'string', 'max:10'],
        ]);

        $verification = $broker->verify($data['otp_id'], $data['code']);

        return response()->json([
            'access_token' => $verification->pair->accessToken,
            'refresh_token' => $verification->pair->refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $verification->pair->expiresIn(),
        ]);
    })->middleware('throttle:sp-jwt-ffotp-verify');
});
```

- [ ] **Step 4: Register rate limiters and load routes in the provider**

In `CoreSpJwtAuthServiceProvider::boot()`, after the OAuth route block, add:

```php
        $this->registerFirstFactorOtpRateLimiters();
        $this->registerFirstFactorOtpRoutes();
```

And add these two private methods to the provider class:

```php
    private function registerFirstFactorOtpRateLimiters(): void
    {
        RateLimiter::for('sp-jwt-ffotp-request', function (Request $request): array {
            $decay = (int) config('sp-jwt-auth.first_factor_otp.limits.decay_minutes', 60);

            $destination = strtolower((string) $request->input('destination', 'unknown'));

            return [
                Limit::perMinutes($decay, (int) config('sp-jwt-auth.first_factor_otp.limits.request_per_destination', 5))
                    ->by('sp-jwt-ffotp-destination:' . $destination),
                Limit::perMinutes($decay, (int) config('sp-jwt-auth.first_factor_otp.limits.request_per_ip', 20))
                    ->by('sp-jwt-ffotp-ip:' . $request->ip()),
            ];
        });

        RateLimiter::for('sp-jwt-ffotp-verify', function (Request $request): array {
            $decay = (int) config('sp-jwt-auth.first_factor_otp.limits.decay_minutes', 60);

            return [
                Limit::perMinutes($decay, (int) config('sp-jwt-auth.first_factor_otp.limits.verify_per_ip', 30))
                    ->by('sp-jwt-ffotp-verify-ip:' . $request->ip()),
            ];
        });
    }

    private function registerFirstFactorOtpRoutes(): void
    {
        if (! (bool) config('sp-jwt-auth.first_factor_otp.enabled', false)) {
            return;
        }

        Route::group([], __DIR__ . '/../routes/otp.php');
    }
```

Add these imports to `CoreSpJwtAuthServiceProvider.php`:

```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
```

Note: `Limit::perMinutes($decay, $max)` exists in Laravel 10+ — verify the method name in the installed framework if the tests error.

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/phpunit --filter FirstFactorOtpHttpTest`
Expected: 6 tests PASS

- [ ] **Step 6: Commit**

```bash
git add routes/otp.php src/CoreSpJwtAuthServiceProvider.php tests/Feature/FirstFactorOtpHttpTest.php
git commit -m "feat: add config-gated first-factor OTP HTTP endpoints with rate limiting"
```

---

### Task 10: Documentation

**Files:**
- Modify: `docs/ai/project-context.md`
- Modify: `docs/ai/client-install.md`
- Modify: `docs/ai/commands.md`
- Modify: `AGENTS.md`
- Modify: `CHANGELOG.md` (under `[Unreleased]`)

- [ ] **Step 1: Update `docs/ai/project-context.md`**

Change the non-goals line to document the exception:

```markdown
- Non-goals: Password login, tenant rules, role assignment, UI, provider-specific Social/OIDC controller flows, and app-owned OAuth consent screens. Exception: first-factor OTP sign-in/sign-up (config-gated; user creation delegated to the app via `FirstFactorUserResolver`).
```

- [ ] **Step 2: Update `docs/ai/client-install.md`**

Add an "Optional module: first-factor OTP" section:

```markdown
## Optional module: first-factor OTP

Sign-in / sign-up via phone (SMS) or email OTP. Enabled with `SP_JWT_FFOTP_ENABLED=true`.

1. Enable the toggle and set limits in `config/sp-jwt-auth.php` (`first_factor_otp` section).
2. Bind a `FirstFactorUserResolver` implementation in the app's service provider (resolve an existing user by destination, or create one; return null to reject).
3. Bind an `OtpChannelSender` to deliver codes (use `OtpMessageFormatter::format()` with the config `message_template` for byte-exact SMS copy).
4. Optionally configure `purposes`, `requested_types`, `test_mode` + `test_code` (dev/staging only).
5. Endpoints: `POST /otp/request`, `POST /otp/resend`, `POST /otp/verify` (429 with `Retry-After` when limits hit).
```

- [ ] **Step 3: Update `docs/ai/commands.md`** — no new commands; skip unless the routes list is present there.

- [ ] **Step 4: Update `AGENTS.md`** — add under "Client-side / Boot" a line:

```markdown
- Optional `first_factor_otp` module — `FirstFactorOtpBroker` + `FirstFactorUserResolver` contract + `routes/otp.php` (config-gated).
```

- [ ] **Step 5: Add a CHANGELOG entry under `[Unreleased]`**

```markdown
### Added
- First-factor OTP sign-in/sign-up flow (`FirstFactorOtpBroker`): digest-only codes, prior-challenge invalidation, shared request/resend cooldown, atomic single-use verify, resolve-or-create via `FirstFactorUserResolver`, destination verified-at marking, and token pair issuance via `JwtTokenService`.
- Config-gated HTTP endpoints (`POST /otp/request`, `/otp/resend`, `/otp/verify`) with per-destination + per-IP rate limits and `Retry-After`.
- `OtpMessageFormatter` for config-driven byte-exact message templates.
- `FirstFactorOtpVerified/Failed/Locked/Expired` events.
```

- [ ] **Step 6: Run the full quality gate**

Run: `composer quality`
Expected: format-check OK, PHPStan OK, all tests PASS (124 + new tests)

- [ ] **Step 7: Commit**

```bash
git add docs/ CHANGELOG.md AGENTS.md
git commit -m "docs: document first-factor OTP module"
```

---

---

## Phase 3: Feature — native JWT refresh / revoke HTTP endpoints

### Task 11: Config section + `/auth/token/refresh` endpoint

**Files:**
- Modify: `config/sp-jwt-auth.php`
- Create: `routes/token.php`
- Modify: `src/CoreSpJwtAuthServiceProvider.php`
- Test: `tests/Feature/JwtTokenHttpTest.php`

- [ ] **Step 1: Add the `token_endpoints` config section** (append after `first_factor_otp` in `config/sp-jwt-auth.php`)

```php
    'token_endpoints' => [
        'enabled' => filter_var(env('SP_JWT_TOKEN_ENDPOINTS_ENABLED', false), FILTER_VALIDATE_BOOL),
        'route_prefix' => env('SP_JWT_TOKEN_ENDPOINTS_ROUTE_PREFIX', 'auth'),
    ],
```

- [ ] **Step 2: Write the failing tests**

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Services\JwtTokenService;
use Sopheak\JwtAuth\Tests\TestCase;

final class JwtTokenHttpTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('sp-jwt-auth.token_endpoints.enabled', true);
    }

    private function issuePair(): array
    {
        $user = $this->createUser();
        $pair = $this->app->make(JwtTokenService::class)->issueTokenPair($user, TokenContext::make());

        return [$user, $pair];
    }

    public function test_refresh_endpoint_rotates_valid_pair(): void
    {
        [, $pair] = $this->issuePair();

        $response = $this->postJson('/auth/token/refresh', ['refresh_token' => $pair->refreshToken]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['access_token', 'refresh_token', 'token_type', 'expires_in']);
    }

    public function test_refresh_endpoint_rejects_invalid_token_with_401(): void
    {
        $this->postJson('/auth/token/refresh', ['refresh_token' => 'not-a-real-token.at-all'])
            ->assertStatus(401);
    }

    public function test_refresh_endpoint_rejects_reused_token_with_401(): void
    {
        [, $pair] = $this->issuePair();

        $this->postJson('/auth/token/refresh', ['refresh_token' => $pair->refreshToken])->assertStatus(200);

        $this->postJson('/auth/token/refresh', ['refresh_token' => $pair->refreshToken])
            ->assertStatus(401);
    }

    public function test_token_endpoint_routes_are_disabled_by_default(): void
    {
        config()->set('sp-jwt-auth.token_endpoints.enabled', false);

        $this->postJson('/auth/token/refresh', ['refresh_token' => 'x.y'])
            ->assertNotFound();
    }
}
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `vendor/bin/phpunit --filter JwtTokenHttpTest`
Expected: FAIL (404 / route not found)

- [ ] **Step 4: Write the refresh route** — `routes/token.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Sopheak\JwtAuth\Services\JwtTokenService;

Route::prefix((string) config('sp-jwt-auth.token_endpoints.route_prefix', 'auth'))->group(function (): void {
    Route::post('/token/refresh', static function (Request $request, JwtTokenService $jwt) {
        $data = $request->validate([
            'refresh_token' => ['required', 'string'],
        ]);

        $pair = $jwt->rotateRefreshToken($data['refresh_token']);

        return response()->json([
            'access_token' => $pair->accessToken,
            'refresh_token' => $pair->refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $pair->expiresIn(),
        ]);
    })->middleware('auth.token-refresh');
});
```

`AuthenticationException` from `rotateRefreshToken()` renders as 401 automatically via Laravel's exception handler — no manual mapping needed (the package's JWT guard already relies on this).

- [ ] **Step 5: Register routes + auth middleware in the provider**

In `CoreSpJwtAuthServiceProvider::boot()`, after the OTP route block:

```php
        if ((bool) config('sp-jwt-auth.token_endpoints.enabled', false)) {
            Route::group([], __DIR__ . '/../routes/token.php');
        }
```

`routes/token.php` must NOT load when the toggle is off — this group handles it. The `auth.token-refresh` middleware alias is app-owned; see Step 6.

- [ ] **Step 6: Decide the refresh-route auth middleware**

The refresh route is unauthenticated by design (it recovers from an expired access token). **Do not** put `auth:api` on it. The `->middleware('auth.token-refresh')` in Step 4 is only valid if the app defines that alias; the package cannot register it for a `throttle`-free endpoint. Simplest correct choice: drop the middleware entirely and rely on token entropy:

```php
    Route::post('/token/refresh', static function (Request $request, JwtTokenService $jwt) {
        // ... same body as Step 4 ...
    });
```

Optional hardening (do only if the client review requests it): add `throttle:10,1` per-IP via a named limiter in the provider.

- [ ] **Step 7: Run tests to verify they pass**

Run: `vendor/bin/phpunit --filter JwtTokenHttpTest`
Expected: 4 tests PASS

- [ ] **Step 8: Commit**

```bash
git add config/sp-jwt-auth.php routes/token.php src/CoreSpJwtAuthServiceProvider.php tests/Feature/JwtTokenHttpTest.php
git commit -m "feat: add config-gated refresh token HTTP endpoint"
```

---

### Task 12: `/auth/token/revoke` endpoint (authenticated, session-scoped)

**Files:**
- Modify: `routes/token.php`
- Modify: `tests/Feature/JwtTokenHttpTest.php`

- [ ] **Step 1: Write the failing tests** (append to `JwtTokenHttpTest`)

```php
    public function test_revoke_endpoint_revokes_whole_session(): void
    {
        $user = $this->createUser();
        $pair = $this->app->make(JwtTokenService::class)->issueTokenPair($user, TokenContext::make());

        $this->withToken($pair->accessToken)
            ->postJson('/auth/token/revoke')
            ->assertStatus(200);

        $this->postJson('/auth/token/refresh', ['refresh_token' => $pair->refreshToken])
            ->assertStatus(401);
    }

    public function test_revoke_endpoint_requires_authentication(): void
    {
        $this->postJson('/auth/token/revoke')
            ->assertStatus(401);
    }
```

Note: `$this->withToken(...)` sets the `Authorization: Bearer` header; the test harness's `auth.guards.api` uses the `sp-jwt` driver (already configured in `tests/TestCase.php`), so `auth()->user()` resolves and `HasJwtTokens::token()` exposes `session_id`.

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit --filter JwtTokenHttpTest`
Expected: FAIL (404 / route not found)

- [ ] **Step 3: Write the revoke route** (append inside the `token_endpoints` prefix group in `routes/token.php`)

```php
    Route::post('/token/revoke', static function () {
        $user = auth()->user();
        $token = $user?->token();

        if ($token === null || $token->sessionId === null) {
            throw new \Illuminate\Auth\AuthenticationException('Unauthenticated.');
        }

        app(JwtTokenService::class)->revokeSession($token->sessionId);

        return response()->json([], 200);
    })->middleware('auth:api');
```

Verify the access-token DTO field name — `JwtAccessToken` exposes `session_id` (check `src/Models/JwtAccessToken.php` or the DTO; if the property is `sessionId`, use it; if it is accessed via `$token->session_id`, use that). Adjust the line accordingly.

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit --filter JwtTokenHttpTest`
Expected: 6 tests PASS

- [ ] **Step 5: Commit**

```bash
git add routes/token.php tests/Feature/JwtTokenHttpTest.php
git commit -m "feat: add authenticated session revoke HTTP endpoint"
```

---

## Phase 3 documentation wrap-up (fold into Task 10)

In Task 10 (Documentation), also add to `docs/ai/client-install.md`:

```markdown
## Optional module: JWT token endpoints

Enable `SP_JWT_TOKEN_ENDPOINTS_ENABLED=true` to expose `POST /auth/token/refresh` (unauthenticated; rotates a JWT-native refresh token pair, generic 401 on invalid/reused tokens) and `POST /auth/token/revoke` (authenticated; revokes the current access token's whole session). No OAuth client required — these operate on the JWT-native `sp_jwt_access_tokens` / `sp_jwt_refresh_tokens` tables.
```

---

## Phase 4 (post-review request): configurable user-id column type

**Request:** client apps use either integer or UUID primary keys; add `'id_type' => 'uuid'` config (default `'integer'`) so migrations create `user_id` columns accordingly.

**Status: implemented directly (not part of the client review).**

- `config/sp-jwt-auth.php` — `'id_type' => env('SP_JWT_ID_TYPE', 'integer')`.
- `src/Support/UserIdColumn.php` — `UserIdColumn::apply(Blueprint $table, string $name = 'user_id', bool $nullable = false)`: `uuid()` when configured, else `unsignedBigInteger()` (unknown values fall back to integer).
- All 10 `user_id` columns across 5 migrations use the helper (`sp_jwt_access_tokens`, `sp_jwt_refresh_tokens`, `sp_jwt_mfa_challenges`, `sp_jwt_email_verification_tokens`, `sp_jwt_password_reset_tokens`, `sp_jwt_external_identities` (nullable), `sp_oauth_consents`, `sp_oauth_auth_codes`, `sp_oauth_access_tokens` (nullable), `sp_oauth_refresh_tokens` (nullable)).
- Tests: `tests/Unit/UserIdColumnTest.php` (4, Blueprint-level, DB-agnostic), `tests/Feature/UserIdColumnMigrationTest.php` (default → `integer`), `tests/Feature/UuidUserIdColumnMigrationTest.php` (uuid mode).
- **Regression fix required by the type change:** services that type-hint `string $userId` now cast `(string)` when reading `user_id` from rows — `EmailVerificationBroker` (3 sites), `PasswordResetBroker` (1), `OAuthServerService` (2: `issueTokenPair` + `OAuthPrincipal`).
- Docs: `docs/ai/client-install.md` (set `SP_JWT_ID_TYPE` before first migrate), CHANGELOG.

## Self-Review Notes

- **Phase 1 coverage:** non-UUID guard (Task 1) — generic 401, no PDOException.
- **Phase 2 spec coverage:** request/resend/verify (Tasks 6-9), resolve-or-create with requested type in one transaction (Task 8), concurrent single-success via `lockForUpdate` + `verified_at` check (Task 8, `test_verify_second_verification_against_same_challenge_fails`), per-destination + per-IP limits with `Retry-After` (Task 9), verified-at marking + token pair via `JwtTokenService` (Task 8), no plaintext codes / no leakage (Task 6 digest-only + Task 9 masked responses; `config` MCP tool already redacts), message template per channel (Task 2 + Task 4 formatter + Task 10 docs).
- **Phase 3 spec coverage:** `/refresh` rotates a valid pair and rejects reused/invalid tokens with generic 401 (Task 11), `/revoke` revokes the whole session (Task 12), JWT-native only — no OAuthClient (Task 11-12), config toggle disabled by default (Task 11).
- **Type consistency:** `FirstFactorUserResolver` (resolve/create signatures used identically in Task 8), `OtpDispatch` fields (Task 6 note to verify against the DTO), `TokenContext::make()->scopes()` (Task 8 note), `Limit::perMinutes` (Task 9 note), `JwtTokenService::rotateRefreshToken()` / `revokeSession()` signatures (Task 11-12 — verify against src/Services/JwtTokenService.php:166,229).
- **Open verification items flagged inline:** `OtpDispatch` property names, `TokenContext` chain, `Limit::perMinutes` availability, `tests/Fixtures/User.php` fillable/guarded style, anonymous-class PHPUnit annotations (the named-argument anonymous class constructors work on PHP 8.1+).
