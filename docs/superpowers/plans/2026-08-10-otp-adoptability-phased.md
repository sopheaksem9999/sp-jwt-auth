# OTP Module Adoptability Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the first-factor OTP module adoptable — config-driven default resolver (opt-in), response envelope + user on verify, `resendByDestination`, and ship everything as a single `0.1.19` release.

**Architecture:** All changes are additive and default-off (hard BC constraint — existing `/otp/*` and `/auth/token/*` response bodies stay byte-identical under default config). A config-driven `DefaultFirstFactorUserResolver` is bound by the provider only when `user_model` is set. A small `ResponseEnvelope` support class wraps route payloads (`raw` | `laravel` | custom class implementing the `ResponseEnvelope` contract). `resendByDestination` reuses the existing `resend()` cooldown/destination path. The release folds all uncommitted WIP into `0.1.19` on `main`.

**Tech Stack:** PHP 8.3+, Laravel 12/13, Orchestra Testbench, PHPUnit. No new runtime dependencies.

**Spec:** `docs/superpowers/specs/2026-08-10-otp-adoptability-design.md`

---

## Phase 0: Commit pre-existing WIP (release prep)

The working tree carries ~35 uncommitted items (Boost/MCP commands, UserIdColumn, Setup/Validate updates, cleanup). These must land as logical commits BEFORE the feature work so the 0.1.19 release can contain everything. **Every WIP commit must keep the test suite green.**

### Task 1: Commit boost/mcp commands WIP

**Files:** `src/Console/BoostCommand.php`, `src/Console/McpCommand.php`, `src/Support/McpServer.php`, `src/Support/StdioTransport.php`, `guidelines/`, `skills/`, `tests/Feature/BoostCommandTest.php`, `tests/Feature/McpCommandTest.php`, `tests/Feature/McpServerTest.php`, plus the Boost/Mcp hunks of `src/CoreSpJwtAuthServiceProvider.php`

- [ ] **Step 1: Inspect** `git diff src/CoreSpJwtAuthServiceProvider.php` — confirm the uncommitted hunks are exactly the BoostCommand/McpCommand imports and `BoostCommand::class, McpCommand::class,` registrations inside `$this->commands([...])` in `boot()`.
- [ ] **Step 2: Stage and commit** (stage ONLY the provider's Boost/Mcp hunks via `git add -p`; the committed OTP/token_endpoints blocks in that file must not change):
```bash
git add src/Console/BoostCommand.php src/Console/McpCommand.php src/Support/McpServer.php src/Support/StdioTransport.php guidelines/ skills/ tests/Feature/BoostCommandTest.php tests/Feature/McpCommandTest.php tests/Feature/McpServerTest.php
git add -p src/CoreSpJwtAuthServiceProvider.php
git commit -m "feat: add boost and mcp commands"
```
- [ ] **Step 3: Verify** `git show HEAD --stat`; `vendor/bin/phpunit --filter 'BoostCommandTest|McpCommandTest|McpServerTest'` passes; `composer format-check` clean (the WIP files are already Rector-clean).

### Task 2: Commit user-id column type WIP

**Files:** `src/Support/UserIdColumn.php`, modified migrations `2026_06_11_000001_create_sp_jwt_access_tokens_table.php`, `2026_06_11_000002_create_sp_jwt_refresh_tokens_table.php`, `2026_06_11_000003_create_sp_jwt_account_security_tables.php`, `2026_06_11_000005_create_sp_jwt_external_identities_table.php`, `2026_06_11_000006_create_sp_oauth_tables.php` (they now use `UserIdColumn::apply`), `tests/Unit/UserIdColumnTest.php`, `tests/Feature/UserIdColumnMigrationTest.php`, `tests/Feature/UuidUserIdColumnMigrationTest.php`, `tests/Feature/ProbeColumnTest.php`

- [ ] **Step 1: Stage and commit**
```bash
git add src/Support/UserIdColumn.php database/migrations/ tests/Unit/UserIdColumnTest.php tests/Feature/UserIdColumnMigrationTest.php tests/Feature/UuidUserIdColumnMigrationTest.php tests/Feature/ProbeColumnTest.php
git commit -m "feat: add configurable user-id column type"
```
- [ ] **Step 2: Verify** `git show HEAD --stat`; `vendor/bin/phpunit --filter 'UserIdColumn|ProbeColumn'` passes; `composer format-check` clean.

### Task 3: Commit broker user-id cast fixes WIP

**Files:** `src/Services/EmailVerificationBroker.php`, `src/Services/PasswordResetBroker.php`, `src/Services/OAuthServerService.php`

- [ ] **Step 1: Inspect** `git diff` each file — the changes are `(string)` casts around `user_id` reads (required by the id_type change). Confirm nothing else changed.
- [ ] **Step 2: Stage and commit**
```bash
git add src/Services/EmailVerificationBroker.php src/Services/PasswordResetBroker.php src/Services/OAuthServerService.php
git commit -m "fix: cast polymorphic user ids to string in services"
```
- [ ] **Step 3: Verify** `vendor/bin/phpunit --filter 'ServiceBrokerTest|OAuthServerTest|AccountSecurityTest'` passes.

### Task 4: Commit setup/validate updates WIP

**Files:** `src/Console/SetupCommand.php`, `src/Console/ValidateCommand.php`, `src/Support/SetupValidator.php`, `tests/Feature/SetupCommandTest.php`, `docs/ai/commands.md`

- [ ] **Step 1: Stage and commit**
```bash
git add src/Console/SetupCommand.php src/Console/ValidateCommand.php src/Support/SetupValidator.php tests/Feature/SetupCommandTest.php docs/ai/commands.md
git commit -m "feat: extend setup and validate commands"
```
- [ ] **Step 2: Verify** `vendor/bin/phpunit --filter SetupCommandTest` passes; `composer format-check` clean.

### Task 5: Commit cleanup WIP (.trae / opencode / gitignore)

**Files:** `opencode.json`, `.gitignore`, deleted `.trae/.ignore`, deleted `.trae/skills/.gitkeep`

- [ ] **Step 1: Stage and commit**
```bash
git add opencode.json .gitignore .trae/.ignore .trae/skills/.gitkeep
git commit -m "chore: drop .trae, refresh opencode config and gitignore"
```
- [ ] **Step 2: Verify** `git status --short` — the ONLY remaining changes must be `M composer.json`, `M VERSION`, `M CHANGELOG.md` (the 0.1.18 version-bump trio, intentionally left uncommitted — the release task folds it into 0.1.19). If other files remain, STOP and report.

---

## Phase 1: Configuration additions

### Task 6: Add config keys for resolver, envelope, and user resource

**Files:** Modify `config/sp-jwt-auth.php`

- [ ] **Step 1: Edit the `first_factor_otp` section** — after the `message_template` block, add:

```php
        'user_model' => env('SP_JWT_FFOTP_USER_MODEL'),
        'destination_columns' => ['phone' => 'phone', 'email' => 'email'],
        'requested_type_column' => env('SP_JWT_FFOTP_REQUESTED_TYPE_COLUMN', 'type'),
        'response_envelope' => env('SP_JWT_FFOTP_RESPONSE_ENVELOPE', 'raw'),
        'user_resource' => env('SP_JWT_FFOTP_USER_RESOURCE'),
```

- [ ] **Step 2: Edit the `token_endpoints` section** — add:

```php
        'response_envelope' => env('SP_JWT_TOKEN_ENDPOINTS_RESPONSE_ENVELOPE', 'raw'),
```

- [ ] **Step 3: Verify** `php -l config/sp-jwt-auth.php`; `vendor/bin/phpunit --filter 'SetupCommandTest|TokenIssueValidateTest'` passes.
- [ ] **Step 4: Commit**
```bash
git add config/sp-jwt-auth.php
git commit -m "feat: add first-factor OTP resolver and envelope configuration"
```

---

## Phase 2: ResponseEnvelope (contract + support)

### Task 7: ResponseEnvelope contract and support class (TDD)

**Files:**
- Create: `src/Contracts/ResponseEnvelope.php`, `src/Support/ResponseEnvelope.php`
- Create tests: `tests/Unit/ResponseEnvelopeTest.php` (plain PHPUnit TestCase — static modes only), `tests/Feature/ResponseEnvelopeFeatureTest.php` (container-dependent cases; MUST be its own filename-matching file, extends package TestCase)
- Create fixtures: `tests/Fixtures/TestEnvelope.php`, `tests/Fixtures/TestUserResource.php`

- [ ] **Step 1: Write the failing tests**

`tests/Unit/ResponseEnvelopeTest.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sopheak\JwtAuth\Support\ResponseEnvelope;

final class ResponseEnvelopeTest extends TestCase
{
    public function test_raw_mode_returns_payload_unchanged(): void
    {
        $payload = ['otp_id' => 'abc', 'destination_masked' => 'a***@b.com'];

        $this->assertSame($payload, ResponseEnvelope::wrap($payload, 'raw'));
    }

    public function test_laravel_mode_wraps_in_data(): void
    {
        $payload = ['otp_id' => 'abc'];

        $this->assertSame(['data' => $payload], ResponseEnvelope::wrap($payload, 'laravel'));
    }

    public function test_empty_mode_is_treated_as_raw(): void
    {
        $payload = ['otp_id' => 'abc'];

        $this->assertSame($payload, ResponseEnvelope::wrap($payload, ''));
    }
}
```

`tests/Fixtures/TestEnvelope.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Fixtures;

use Sopheak\JwtAuth\Contracts\ResponseEnvelope;

final class TestEnvelope implements ResponseEnvelope
{
    public function wrap(array $payload): array
    {
        return ['wrapped' => $payload];
    }
}
```

`tests/Fixtures/TestUserResource.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Fixtures;

use Illuminate\Contracts\Auth\Authenticatable;

final class TestUserResource
{
    public function __invoke(Authenticatable $user): array
    {
        return ['id' => $user->getAuthIdentifier(), 'resource' => true];
    }
}
```

`tests/Feature/ResponseEnvelopeFeatureTest.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;
use Sopheak\JwtAuth\Support\ResponseEnvelope;
use Sopheak\JwtAuth\Tests\Fixtures\TestEnvelope;
use Sopheak\JwtAuth\Tests\Fixtures\TestUserResource;
use Sopheak\JwtAuth\Tests\Fixtures\User;
use Sopheak\JwtAuth\Tests\TestCase;

final class ResponseEnvelopeFeatureTest extends TestCase
{
    public function test_custom_class_mode_resolves_envelope_from_container(): void
    {
        $result = ResponseEnvelope::wrap(['otp_id' => 'abc'], TestEnvelope::class);

        $this->assertSame(['wrapped' => ['otp_id' => 'abc']], $result);
    }

    public function test_custom_class_not_implementing_contract_throws(): void
    {
        $this->expectException(RuntimeException::class);

        ResponseEnvelope::wrap([], User::class);
    }

    public function test_serialize_user_via_resource(): void
    {
        $user = $this->createUser();

        $result = ResponseEnvelope::serializeUser($user, TestUserResource::class);

        $this->assertSame(['id' => $user->getAuthIdentifier(), 'resource' => true], $result);
    }

    public function test_serialize_user_defaults_to_to_array(): void
    {
        $user = $this->createUser();

        $result = ResponseEnvelope::serializeUser($user);

        $this->assertSame($user->getAuthIdentifier(), $result['id']);
        $this->assertArrayNotHasKey('password', $result);
    }

    public function test_serialize_non_model_user_returns_id_only(): void
    {
        $principal = new class implements Authenticatable {
            public function getAuthIdentifierName(): string
            {
                return 'id';
            }

            public function getAuthIdentifier(): mixed
            {
                return 'p1';
            }

            public function getAuthPassword(): ?string
            {
                return null;
            }

            public function getRememberToken(): ?string
            {
                return null;
            }

            public function setRememberToken($value): void
            {
            }

            public function getRememberTokenName(): ?string
            {
                return null;
            }
        };

        $this->assertSame(['id' => 'p1'], ResponseEnvelope::serializeUser($principal));
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit --filter 'ResponseEnvelopeTest|ResponseEnvelopeFeatureTest'`
Expected: FAIL with `Class "Sopheak\JwtAuth\Support\ResponseEnvelope" not found`

- [ ] **Step 3: Write the contract** `src/Contracts/ResponseEnvelope.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Contracts;

interface ResponseEnvelope
{
    public function wrap(array $payload): array;
}
```

- [ ] **Step 4: Write the support class** `src/Support/ResponseEnvelope.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Sopheak\JwtAuth\Contracts\ResponseEnvelope as ResponseEnvelopeContract;

final class ResponseEnvelope
{
    public static function wrap(array $payload, string $mode): array
    {
        if ($mode === 'raw' || $mode === '') {
            return $payload;
        }

        if ($mode === 'laravel') {
            return ['data' => $payload];
        }

        $envelope = app($mode);

        if (! $envelope instanceof ResponseEnvelopeContract) {
            throw new RuntimeException(sprintf('Response envelope [%s] must implement %s.', $mode, ResponseEnvelopeContract::class));
        }

        return $envelope->wrap($payload);
    }

    public static function serializeUser(Authenticatable $user, ?string $resource = null): array
    {
        if ($resource !== null && $resource !== '') {
            $serializer = app($resource);

            if (! is_callable($serializer)) {
                throw new RuntimeException(sprintf('User resource [%s] must be invokable.', $resource));
            }

            $serialized = $serializer($user);

            if (! is_array($serialized)) {
                throw new RuntimeException(sprintf('User resource [%s] must return an array.', $resource));
            }

            return $serialized;
        }

        if ($user instanceof Model) {
            return $user->toArray();
        }

        return ['id' => $user->getAuthIdentifier()];
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/phpunit --filter 'ResponseEnvelopeTest|ResponseEnvelopeFeatureTest'`
Expected: PASS (3 unit + 5 feature tests)

- [ ] **Step 6: Commit**

```bash
git add src/Contracts/ResponseEnvelope.php src/Support/ResponseEnvelope.php tests/Unit/ResponseEnvelopeTest.php tests/Feature/ResponseEnvelopeFeatureTest.php tests/Fixtures/TestEnvelope.php tests/Fixtures/TestUserResource.php
git commit -m "feat: add response envelope contract and support"
```

---

## Phase 3: Default resolver

### Task 8: DefaultFirstFactorUserResolver (TDD)

**Files:**
- Create: `src/Services/DefaultFirstFactorUserResolver.php`
- Modify: `tests/TestCase.php` (add `phone` + `type` columns to the users table)
- Create test: `tests/Feature/DefaultFirstFactorUserResolverTest.php`

- [ ] **Step 1: Extend the test harness users table**

In `tests/TestCase.php::defineDatabaseMigrations()`, after the `email_verified_at` column line, add:

```php
            $table->string('phone')->nullable()->unique();
            $table->string('type')->nullable();
```

- [ ] **Step 2: Write the failing tests** `tests/Feature/DefaultFirstFactorUserResolverTest.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use RuntimeException;
use Sopheak\JwtAuth\DTO\OtpDestination;
use Sopheak\JwtAuth\Services\DefaultFirstFactorUserResolver;
use Sopheak\JwtAuth\Tests\Fixtures\User;
use Sopheak\JwtAuth\Tests\TestCase;

final class DefaultFirstFactorUserResolverTest extends TestCase
{
    private function resolver(): DefaultFirstFactorUserResolver
    {
        return new DefaultFirstFactorUserResolver();
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('sp-jwt-auth.first_factor_otp.user_model', User::class);
    }

    public function test_resolve_finds_user_by_email(): void
    {
        $user = $this->createUser(['email' => 'existing@example.com']);

        $found = $this->resolver()->resolve(OtpDestination::email('existing@example.com'), 'login');

        $this->assertNotNull($found);
        $this->assertSame($user->getAuthIdentifier(), $found->getAuthIdentifier());
    }

    public function test_resolve_finds_user_by_phone(): void
    {
        $user = $this->createUser(['phone' => '+85512345678']);

        $found = $this->resolver()->resolve(OtpDestination::phone('+85512345678'), 'login');

        $this->assertNotNull($found);
        $this->assertSame($user->getAuthIdentifier(), $found->getAuthIdentifier());
    }

    public function test_resolve_returns_null_when_no_match(): void
    {
        $this->assertNull($this->resolver()->resolve(OtpDestination::email('nobody@example.com'), 'login'));
    }

    public function test_resolve_returns_null_when_column_missing(): void
    {
        config()->set('sp-jwt-auth.first_factor_otp.destination_columns', ['phone' => 'phone_col', 'email' => 'email']);

        $this->assertNull($this->resolver()->resolve(OtpDestination::phone('+85512345678'), 'login'));
    }

    public function test_create_sets_destination_and_requested_type(): void
    {
        $user = $this->resolver()->create(OtpDestination::email('new@example.com'), 'register', 'driver');

        $this->assertNotNull($user);
        $this->assertSame('new@example.com', $user->getAttribute('email'));
        $this->assertSame('driver', $user->getAttribute('type'));
    }

    public function test_create_without_requested_type_leaves_column_null(): void
    {
        $user = $this->resolver()->create(OtpDestination::phone('+85512345678'), 'login', null);

        $this->assertNotNull($user);
        $this->assertSame('+85512345678', $user->getAttribute('phone'));
        $this->assertNull($user->getAttribute('type'));
    }

    public function test_create_returns_existing_user_on_unique_race(): void
    {
        $this->createUser(['email' => 'taken@example.com']);

        $user = $this->resolver()->create(OtpDestination::email('taken@example.com'), 'login', null);

        $this->assertNotNull($user);
        $this->assertSame('taken@example.com', $user->getAttribute('email'));
    }

    public function test_missing_model_class_throws(): void
    {
        config()->set('sp-jwt-auth.first_factor_otp.user_model', 'App\\Models\\DoesNotExist');

        $this->expectException(RuntimeException::class);

        $this->resolver()->resolve(OtpDestination::email('a@b.com'), 'login');
    }
}
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `vendor/bin/phpunit --filter DefaultFirstFactorUserResolverTest`
Expected: FAIL with `Class "Sopheak\JwtAuth\Services\DefaultFirstFactorUserResolver" not found`

- [ ] **Step 4: Write the resolver** `src/Services/DefaultFirstFactorUserResolver.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Sopheak\JwtAuth\Contracts\FirstFactorUserResolver;
use Sopheak\JwtAuth\DTO\OtpDestination;

final class DefaultFirstFactorUserResolver implements FirstFactorUserResolver
{
    public function resolve(OtpDestination $destination, string $purpose): ?Authenticatable
    {
        $model = $this->modelClass();
        $column = $this->columnFor($destination->channel, $model);

        if ($column === null || ! Schema::hasColumn((new $model)->getTable(), $column)) {
            return null;
        }

        /** @var Authenticatable|null */
        return $model::query()->where($column, $destination->normalizedDestination)->first();
    }

    public function create(OtpDestination $destination, string $purpose, ?string $requestedType): ?Authenticatable
    {
        $model = $this->modelClass();
        $column = $this->columnFor($destination->channel, $model);

        if ($column === null) {
            return null;
        }

        try {
            $user = new $model();
            $user->forceFill([$column => $destination->normalizedDestination]);

            if ($requestedType !== null) {
                $user->forceFill([$this->requestedTypeColumn() => $requestedType]);
            }

            $user->save();
        } catch (QueryException) {
            return $this->resolve($destination, $purpose);
        }

        return $user;
    }

    /** @return class-string */
    private function modelClass(): string
    {
        $model = config('sp-jwt-auth.first_factor_otp.user_model');

        if (! is_string($model) || $model === '') {
            throw new RuntimeException('sp-jwt-auth.first_factor_otp.user_model is not configured.');
        }

        if (! class_exists($model)) {
            throw new RuntimeException(sprintf('Configured user model [%s] does not exist.', $model));
        }

        return $model;
    }

    /** @param class-string $model */
    private function columnFor(string $channel, string $model): ?string
    {
        $key = $channel === 'email' ? 'email' : 'phone';
        $columns = config('sp-jwt-auth.first_factor_otp.destination_columns', []);

        $column = $columns[$key] ?? null;

        return is_string($column) && $column !== '' ? $column : null;
    }

    private function requestedTypeColumn(): string
    {
        return (string) config('sp-jwt-auth.first_factor_otp.requested_type_column', 'type');
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/phpunit --filter DefaultFirstFactorUserResolverTest`
Expected: PASS (8 tests)

- [ ] **Step 6: Commit**

```bash
git add src/Services/DefaultFirstFactorUserResolver.php tests/Feature/DefaultFirstFactorUserResolverTest.php tests/TestCase.php
git commit -m "feat: add config-driven default first-factor user resolver"
```

### Task 9: Bind the default resolver in the provider (opt-in)

**Files:**
- Modify: `src/CoreSpJwtAuthServiceProvider.php`
- Create test: `tests/Feature/DefaultResolverBindingTest.php`

- [ ] **Step 1: Write the failing tests** `tests/Feature/DefaultResolverBindingTest.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Sopheak\JwtAuth\Contracts\FirstFactorUserResolver;
use Sopheak\JwtAuth\DTO\OtpDestination;
use Sopheak\JwtAuth\Services\DefaultFirstFactorUserResolver;
use Sopheak\JwtAuth\Services\FirstFactorOtpBroker;
use Sopheak\JwtAuth\Tests\Fixtures\User;
use Sopheak\JwtAuth\Tests\TestCase;

final class DefaultResolverBindingTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('sp-jwt-auth.first_factor_otp.user_model', User::class);
    }

    public function test_default_resolver_is_bound_when_user_model_configured(): void
    {
        $this->assertInstanceOf(DefaultFirstFactorUserResolver::class, $this->app->make(FirstFactorUserResolver::class));
    }

    public function test_verify_creates_user_without_consumer_resolver_code(): void
    {
        config()->set('sp-jwt-auth.first_factor_otp.test_mode', true);
        config()->set('sp-jwt-auth.first_factor_otp.test_code', '424242');

        $broker = $this->app->make(FirstFactorOtpBroker::class);
        $dispatch = $broker->request(OtpDestination::email('new@example.com'), 'login');

        $verification = $broker->verify($dispatch->otpId, '424242', OtpDestination::email('new@example.com'));

        $this->assertSame('new@example.com', $verification->user->getAttribute('email'));
        $this->assertNotNull($verification->user->getAttribute('email_verified_at'));
        $this->assertNotEmpty($verification->pair->accessToken);
    }

    public function test_verify_resolves_existing_user_without_consumer_resolver_code(): void
    {
        config()->set('sp-jwt-auth.first_factor_otp.test_mode', true);
        config()->set('sp-jwt-auth.first_factor_otp.test_code', '424242');

        $existing = $this->createUser(['email' => 'existing@example.com']);

        $broker = $this->app->make(FirstFactorOtpBroker::class);
        $dispatch = $broker->request(OtpDestination::email('existing@example.com'), 'login');

        $verification = $broker->verify($dispatch->otpId, '424242', OtpDestination::email('existing@example.com'));

        $this->assertSame($existing->getAuthIdentifier(), $verification->user->getAuthIdentifier());
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit --filter DefaultResolverBindingTest`
Expected: FAIL — `$this->app->make(FirstFactorUserResolver::class)` throws `BindingResolutionException` (no binding yet)

- [ ] **Step 3: Wire the binding** in `src/CoreSpJwtAuthServiceProvider.php`:

Add imports `use Sopheak\JwtAuth\Contracts\FirstFactorUserResolver;` and `use Sopheak\JwtAuth\Services\DefaultFirstFactorUserResolver;`. In `boot()`, after the `publishes` calls and BEFORE the `Auth::extend` block, add:

```php
        if (config('sp-jwt-auth.first_factor_otp.user_model') !== null) {
            $this->app->bind(FirstFactorUserResolver::class, DefaultFirstFactorUserResolver::class);
        }
```

NOTE: check `git status` on the provider first — if it still carries uncommitted WIP hunks, stage ONLY your hunks with `git add -p` when committing.

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit --filter 'DefaultResolverBindingTest|FirstFactorOtpBrokerTest|FirstFactorOtpHttpTest'`
Expected: all PASS — the broker/HTTP tests bind resolver stubs via `app()->instance()`, which takes precedence over the provider `bind()` (proves consumer overrides still win)

- [ ] **Step 5: Commit**

```bash
git add src/CoreSpJwtAuthServiceProvider.php tests/Feature/DefaultResolverBindingTest.php
git commit -m "feat: bind default first-factor user resolver when user_model configured"
```

---

## Phase 4: Envelope wiring in routes

### Task 10: Wrap OTP and token-endpoint responses (TDD, BC-safe)

**Files:**
- Modify: `routes/otp.php`, `routes/token.php`
- Create tests: `tests/Feature/FirstFactorOtpEnvelopeTest.php`, `tests/Feature/JwtTokenEnvelopeTest.php` (both MUST be own filename-matching files)

- [ ] **Step 1: Write the failing tests**

`tests/Feature/FirstFactorOtpEnvelopeTest.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Illuminate\Contracts\Auth\Authenticatable;
use Sopheak\JwtAuth\Contracts\FirstFactorUserResolver;
use Sopheak\JwtAuth\DTO\OtpDestination;
use Sopheak\JwtAuth\Tests\Fixtures\TestEnvelope;
use Sopheak\JwtAuth\Tests\Fixtures\TestUserResource;
use Sopheak\JwtAuth\Tests\Fixtures\User;
use Sopheak\JwtAuth\Tests\TestCase;

final class FirstFactorOtpEnvelopeTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('sp-jwt-auth.first_factor_otp.enabled', true);
        $app['config']->set('sp-jwt-auth.first_factor_otp.resend_cooldown_seconds', 0);
        $app['config']->set('sp-jwt-auth.first_factor_otp.response_envelope', 'laravel');
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

    public function test_request_endpoint_wraps_payload_in_data(): void
    {
        $this->bindResolver($this->createUser());

        $response = $this->postJson('/otp/request', [
            'destination' => 'user@example.com',
            'channel' => 'email',
            'purpose' => 'login',
        ]);

        $response->assertStatus(202);
        $response->assertJsonPath('data.destination_masked', 'u***@example.com');
        $response->assertJsonStructure(['data' => ['otp_id', 'destination_masked', 'expires_in']]);
    }

    public function test_verify_endpoint_includes_user_in_data(): void
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
            'otp_id' => $request->json('data.otp_id'),
            'code' => '424242',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['access_token', 'refresh_token', 'token_type', 'expires_in', 'user']]);
    }

    public function test_verify_endpoint_serializes_user_via_resource(): void
    {
        config()->set('sp-jwt-auth.first_factor_otp.test_mode', true);
        config()->set('sp-jwt-auth.first_factor_otp.test_code', '424242');
        config()->set('sp-jwt-auth.first_factor_otp.user_resource', TestUserResource::class);

        $this->bindResolver($this->createUser());

        $request = $this->postJson('/otp/request', [
            'destination' => 'user@example.com',
            'channel' => 'email',
            'purpose' => 'login',
        ]);

        $response = $this->postJson('/otp/verify', [
            'otp_id' => $request->json('data.otp_id'),
            'code' => '424242',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.user.resource', true);
    }

    public function test_custom_envelope_class_wraps_responses(): void
    {
        config()->set('sp-jwt-auth.first_factor_otp.response_envelope', TestEnvelope::class);

        $this->bindResolver($this->createUser());

        $this->postJson('/otp/request', [
            'destination' => 'user@example.com',
            'channel' => 'email',
            'purpose' => 'login',
        ])->assertStatus(202)->assertJsonStructure(['wrapped' => ['otp_id', 'destination_masked', 'expires_in']]);
    }
}
```

`tests/Feature/JwtTokenEnvelopeTest.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Services\JwtTokenService;
use Sopheak\JwtAuth\Tests\TestCase;

final class JwtTokenEnvelopeTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('sp-jwt-auth.token_endpoints.enabled', true);
        $app['config']->set('sp-jwt-auth.token_endpoints.response_envelope', 'laravel');
    }

    public function test_refresh_endpoint_wraps_pair_in_data(): void
    {
        $user = $this->createUser();
        $pair = $this->app->make(JwtTokenService::class)->issueTokenPair($user, TokenContext::make());

        $this->postJson('/auth/token/refresh', ['refresh_token' => $pair->refreshToken])
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['access_token', 'refresh_token', 'token_type', 'expires_in']]);
    }

    public function test_revoke_endpoint_wraps_empty_data(): void
    {
        $user = $this->createUser();
        $pair = $this->app->make(JwtTokenService::class)->issueTokenPair($user, TokenContext::make());

        $this->withToken($pair->accessToken)
            ->postJson('/auth/token/revoke')
            ->assertStatus(200)
            ->assertJson(['data' => []]);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit --filter 'FirstFactorOtpEnvelopeTest|JwtTokenEnvelopeTest'`
Expected: FAIL — responses are unwrapped (`data` key missing)

- [ ] **Step 3: Modify `routes/otp.php`**

Add import `use Sopheak\JwtAuth\Support\ResponseEnvelope;`. Then:

**`/request` closure** — replace the final return with:

```php
        return response()->json(ResponseEnvelope::wrap([
            'otp_id' => $dispatch->otpId,
            'destination_masked' => $destination->maskedDestination,
            'expires_in' => (int) config('sp-jwt-auth.first_factor_otp.ttl_minutes', 5) * 60,
        ], (string) config('sp-jwt-auth.first_factor_otp.response_envelope', 'raw')), 202);
```

**`/resend` closure** — same replacement shape (uses `$dispatch` from the existing try/catch).

**`/verify` closure** — replace the final return with:

```php
        $payload = [
            'access_token' => $verification->pair->accessToken,
            'refresh_token' => $verification->pair->refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $verification->pair->expiresIn(),
        ];

        $mode = (string) config('sp-jwt-auth.first_factor_otp.response_envelope', 'raw');

        if ($mode !== 'raw') {
            $payload['user'] = ResponseEnvelope::serializeUser(
                $verification->user,
                is_string(config('sp-jwt-auth.first_factor_otp.user_resource')) ? config('sp-jwt-auth.first_factor_otp.user_resource') : null,
            );
        }

        return response()->json(ResponseEnvelope::wrap($payload, $mode));
```

- [ ] **Step 4: Modify `routes/token.php`**

Add import `use Sopheak\JwtAuth\Support\ResponseEnvelope;`. **`/token/refresh` closure** — wrap the pair payload:

```php
        return response()->json(ResponseEnvelope::wrap([
            'access_token' => $pair->accessToken,
            'refresh_token' => $pair->refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $pair->expiresIn(),
        ], (string) config('sp-jwt-auth.token_endpoints.response_envelope', 'raw')));
```

**`/token/revoke` closure** — replace the return with:

```php
        return response()->json(ResponseEnvelope::wrap([], (string) config('sp-jwt-auth.token_endpoints.response_envelope', 'raw')));
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/phpunit --filter 'FirstFactorOtpEnvelopeTest|JwtTokenEnvelopeTest|FirstFactorOtpHttpTest|JwtTokenHttpTest|JwtTokenRoutesDisabledTest|FirstFactorOtpRoutesDisabledTest'`
Expected: all PASS — the existing tests (default `raw` config) prove BC: shapes unchanged

- [ ] **Step 6: Commit**

```bash
git add routes/otp.php routes/token.php tests/Feature/FirstFactorOtpEnvelopeTest.php tests/Feature/JwtTokenEnvelopeTest.php
git commit -m "feat: add configurable response envelope to OTP and token endpoints"
```

---

## Phase 5: resendByDestination

### Task 11: Broker method resendByDestination (TDD)

**Files:**
- Modify: `src/Services/FirstFactorOtpBroker.php`
- Modify: `tests/Feature/FirstFactorOtpBrokerTest.php`

- [ ] **Step 1: Append the failing tests**

```php
    public function test_resend_by_destination_inherits_requested_type(): void
    {
        $dispatch = $this->broker()->request(OtpDestination::email('a@b.com'), 'login', 'driver');

        FirstFactorOtpCode::query()->whereKey($dispatch->otpId)->update(['last_sent_at' => now()->subMinutes(2)]);

        $resent = $this->broker()->resendByDestination(OtpDestination::email('a@b.com'), 'login');

        $this->assertNotSame($dispatch->otpId, $resent->otpId);
        $this->assertSame('driver', FirstFactorOtpCode::query()->findOrFail($resent->otpId)->requested_type);
    }

    public function test_resend_by_destination_shares_cooldown(): void
    {
        $this->broker()->request(OtpDestination::email('a@b.com'), 'login');

        try {
            $this->broker()->resendByDestination(OtpDestination::email('a@b.com'), 'login');
            $this->fail('Expected TooManyRequestsHttpException');
        } catch (TooManyRequestsHttpException $e) {
            $this->assertGreaterThan(0, (int) $e->getHeaders()['Retry-After']);
        }
    }

    public function test_resend_by_destination_without_active_challenge_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->broker()->resendByDestination(OtpDestination::email('nobody@example.com'), 'login');
    }

    public function test_resend_by_destination_ignores_other_purpose(): void
    {
        $this->broker()->request(OtpDestination::email('a@b.com'), 'login');

        $this->expectException(\InvalidArgumentException::class);

        $this->broker()->resendByDestination(OtpDestination::email('a@b.com'), 'register');
    }
```

(The test class already imports `TooManyRequestsHttpException`, `InvalidArgumentException`, `OtpDestination`, `FirstFactorOtpCode`, `FirstFactorOtpBroker`.)

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit --filter FirstFactorOtpBrokerTest`
Expected: FAIL with `Call to undefined method FirstFactorOtpBroker::resendByDestination()`

- [ ] **Step 3: Add the method** to `src/Services/FirstFactorOtpBroker.php` (after `resend()`):

```php
    public function resendByDestination(OtpDestination $destination, string $purpose): OtpDispatch
    {
        $otp = $this->latestActive($destination, $purpose);

        if (! $otp instanceof FirstFactorOtpCode) {
            throw new InvalidArgumentException('No active challenge for destination and purpose.');
        }

        return $this->resend($otp->id, $destination);
    }
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit --filter FirstFactorOtpBrokerTest`
Expected: PASS (21 tests)

- [ ] **Step 5: Commit**

```bash
git add src/Services/FirstFactorOtpBroker.php tests/Feature/FirstFactorOtpBrokerTest.php
git commit -m "feat: add resend-by-destination to first-factor OTP broker"
```

### Task 12: HTTP route /otp/resend-by-destination (TDD)

**Files:**
- Modify: `routes/otp.php`
- Modify: `tests/Feature/FirstFactorOtpHttpTest.php`

- [ ] **Step 1: Append the failing tests** (the class env already sets `resend_cooldown_seconds = 0`, so no rewind is needed):

```php
    public function test_resend_by_destination_endpoint_issues_new_code(): void
    {
        $this->bindResolver($this->createUser());

        $first = $this->postJson('/otp/request', [
            'destination' => 'user@example.com',
            'channel' => 'email',
            'purpose' => 'login',
        ])->assertStatus(202);

        $this->postJson('/otp/resend-by-destination', [
            'destination' => 'user@example.com',
            'channel' => 'email',
            'purpose' => 'login',
        ])->assertStatus(202)->assertJsonStructure(['otp_id', 'destination_masked', 'expires_in']);
    }

    public function test_resend_by_destination_endpoint_rejects_without_active_challenge(): void
    {
        $this->bindResolver($this->createUser());

        $this->postJson('/otp/resend-by-destination', [
            'destination' => 'nobody@example.com',
            'channel' => 'email',
            'purpose' => 'login',
        ])->assertStatus(422);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit --filter FirstFactorOtpHttpTest`
Expected: FAIL (404 route not found)

- [ ] **Step 3: Add the route** to `routes/otp.php` (after `/resend`, inside the same group):

```php
    Route::post('/resend-by-destination', static function (Request $request, FirstFactorOtpBroker $broker) {
        $data = $request->validate([
            'destination' => ['required', 'string', 'max:255'],
            'channel' => ['required', 'string', 'in:email,sms'],
            'purpose' => ['required', 'string', 'max:50'],
        ]);

        $destination = $data['channel'] === 'email'
            ? OtpDestination::email($data['destination'])
            : OtpDestination::phone($data['destination']);

        try {
            $dispatch = $broker->resendByDestination($destination, $data['purpose']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(ResponseEnvelope::wrap([
            'otp_id' => $dispatch->otpId,
            'destination_masked' => $destination->maskedDestination,
            'expires_in' => (int) config('sp-jwt-auth.first_factor_otp.ttl_minutes', 5) * 60,
        ], (string) config('sp-jwt-auth.first_factor_otp.response_envelope', 'raw')), 202);
    })->middleware('throttle:sp-jwt-ffotp-request');
```

(`ResponseEnvelope` import was added in Task 10.)

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit --filter 'FirstFactorOtpHttpTest|FirstFactorOtpEnvelopeTest'`
Expected: all PASS

- [ ] **Step 5: Commit**

```bash
git add routes/otp.php tests/Feature/FirstFactorOtpHttpTest.php
git commit -m "feat: add resend-by-destination OTP endpoint"
```

---

## Phase 6: Documentation

### Task 13: Update docs (client-install, guide pages, AGENTS.md)

**Files:**
- Modify: `docs/ai/client-install.md`
- Modify: `docs/guide/first-factor-otp.md` + `docs/features/first-factor-otp.md` (byte-identical mirrors)
- Modify: `docs/guide/token-endpoints.md` + `docs/features/token-endpoints.md` (byte-identical mirrors)
- Modify: `AGENTS.md` (only if a line needs it — likely just the CHANGELOG in Task 14)

- [ ] **Step 1: `docs/ai/client-install.md`** — extend the "Optional module: first-factor OTP" section with a "One-time configuration (default resolver)" subsection:

```markdown
To use the built-in default resolver (no custom code), add to the app service provider or `.env`:

```php
'user_model' => App\Models\User::class,          // SP_JWT_FFOTP_USER_MODEL
'destination_columns' => ['phone' => 'phone', 'email' => 'email'],
'requested_type_column' => 'type',               // SP_JWT_FFOTP_REQUESTED_TYPE_COLUMN
'response_envelope' => 'laravel',                // SP_JWT_FFOTP_RESPONSE_ENVELOPE: raw|laravel|CustomEnvelopeClass
'user_resource' => App\Http\Resources\UserResource::class, // optional invokable; default: model toArray()
```

The default resolver finds users by the destination column (`email`/`phone`) and creates missing users with `requested_type` written to the configured column (unique-constraint races re-resolve the existing row). Bind your own `FirstFactorUserResolver` implementation to override it entirely.
```

Also update the endpoints line to include `POST /otp/resend-by-destination`.

- [ ] **Step 2: `docs/guide/first-factor-otp.md` + mirror** — add sections for: the default resolver (`user_model`, `destination_columns`, `requested_type_column` — including the "opt-in only, consumer bindings win" note), the response envelope (`raw` | `laravel` | custom class implementing `Sopheak\JwtAuth\Contracts\ResponseEnvelope`, `user_resource` serialization, user included on enveloped verify), and `POST /otp/resend-by-destination`. Keep the existing content; insert new subsections in the natural places.
- [ ] **Step 3: `docs/guide/token-endpoints.md` + mirror** — add `token_endpoints.response_envelope` (raw|laravel|custom) to the configuration table and one sentence on wrapping.
- [ ] **Step 4: Verify** both mirror pairs are byte-identical after editing (`diff -q` the pairs).
- [ ] **Step 5: Commit**
```bash
git add docs/ai/client-install.md docs/guide/first-factor-otp.md docs/features/first-factor-otp.md docs/guide/token-endpoints.md docs/features/token-endpoints.md
git commit -m "docs: document default resolver, response envelope, and resend-by-destination"
```

---

## Phase 7: Release 0.1.19

### Task 14: Bump, gate, merge, tag, push

**Files:** `composer.json` (version), `VERSION`, `CHANGELOG.md`

- [ ] **Step 1: Confirm the tree is clean** `git status --short` — the ONLY changes must be `M composer.json`, `M VERSION`, `M CHANGELOG.md` (the 0.1.18 bump trio from before Task 1). If anything else remains, STOP and report.
- [ ] **Step 2: Bump to 0.1.19** — `composer.json` `"version": "0.1.19"`, `VERSION` → `0.1.19`. In `CHANGELOG.md`: read it first; delete the draft `## [0.1.18] - 2026-08-10` header and merge its bullets into `## [Unreleased]`, then rename `## [Unreleased]` → `## [0.1.19] - 2026-08-10`. Add under the new `[0.1.19]` `### Added`: the default resolver, response envelope + user on verify, resend-by-destination, and token-endpoints envelope bullets (match the existing bullet style).
- [ ] **Step 3: Run the full gate** `composer quality` — format-check OK, PHPStan OK, full test suite PASS (expected ~195 tests).
- [ ] **Step 4: Commit the release bump**
```bash
git add composer.json VERSION CHANGELOG.md
git commit -m "chore: bump package version to 0.1.19"
```
- [ ] **Step 5: Merge to main and gate**
```bash
git checkout main
git merge develop --no-ff -m "Merge develop into main for v0.1.19"
composer quality
```
- [ ] **Step 6: Tag and push**
```bash
git tag -a 0.1.19 -m "v0.1.19: first-factor OTP module, token endpoints, adoptability"
git checkout develop
git push origin develop main 0.1.19
```
If the main push is rejected (branch protection), report and provide the exact commands for the user to run.
- [ ] **Step 7: Verify** `git ls-remote --tags origin | grep 0.1.19`; note: Packagist syncs the tag automatically if the webhook is configured, otherwise the owner confirms on packagist.org.

---

## Acceptance criteria mapping

- `first_factor_otp.user_model` + column mapping resolve and create users without consumer code → Tasks 8-9
- Verify response includes the user when an envelope is enabled; envelope mode configurable per module → Tasks 6-7, 10
- `resendByDestination` exists, inherits `requested_type`, shares the cooldown → Tasks 11-12
- `0.1.19` (or later) tag published → Task 14
- `docs/ai/client-install.md` shows the one-time config → Task 13

## Self-Review Notes

- BC: default `response_envelope = 'raw'` keeps every existing response byte-identical (existing route tests are the regression guard, re-run in Task 10 Step 5).
- Resolver opt-in: no binding when `user_model` unset — existing consumers who bind their own resolver are unaffected (instance bindings win over the provider `bind`).
- `resendByDestination` reuses `resend()` — identical cooldown/destination semantics, no bypass.
- Laravel 13 exception note: use `Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException` (the Illuminate one does not exist in this framework version).
- Test-class rule: every new Feature test class must live in its own filename-matching file (PHPUnit ignores classes not matching the file name).
- The verify route's `user` key appears ONLY when the envelope mode is not `'raw'`.
