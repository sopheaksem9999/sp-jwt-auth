# SP JWT SaaS Convenience Functions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add low-risk built-in convenience APIs that make `sp-jwt-auth` easier to use in multi-tenant SaaS applications without adding app-specific database columns or breaking the generic package model.

**Architecture:** Keep the core storage model generic: `subject_type` / `subject_id` for indexed active context, `claims` JSON for app-readable context, and existing token rows for revocation. Add fluent DTO helpers, read helpers, response extension hooks, session/device service helpers, testing utilities, and docs around these existing primitives.

**Tech Stack:** PHP 8.3+, Laravel 12/13 package, Eloquent models, Orchestra Testbench, PHPUnit, PHPStan, Rector.

---

## Scope Decisions

- Do not add `company_id`, `tenant_id`, or `impersonated` columns to package migrations.
- Do not store raw JWT strings in `sp_jwt_access_tokens`.
- Do not decode JWT payloads from the token model; claims already persist in `sp_jwt_access_tokens.claims`.
- Do add convenience functions that read/write the existing `subject`, `claims`, `device`, and `session` fields.

---

## File Structure

- Modify `src/DTO/TokenContext.php`: add claim, metadata, company, tenant, and impersonation fluent helpers.
- Modify `src/Models/JwtAccessToken.php`: add read helpers for common SaaS claims and context.
- Modify `src/Support/TokenResponse.php`: add global response transformer registration and reset support for tests.
- Modify `src/Services/JwtTokenService.php`: add `exceptSessionId`, device revocation, and active-session listing helpers.
- Modify `src/DTO/ApiKeyContext.php`: add `forCompany()` factory for service-to-service integrations.
- Create `src/Testing/JwtTokenTestHelper.php`: helper for issuing test tokens with scopes/claims.
- Modify `tests/Unit/TokenContextTest.php`: cover context helper behavior.
- Create `tests/Unit/JwtAccessTokenHelperTest.php`: cover token read helpers without DB.
- Modify `tests/Feature/CommandTest.php`: cover response extension behavior.
- Modify `tests/Feature/RevokeTokensTest.php`: cover all-user except-session, device revocation, and session listing.
- Modify `tests/Feature/ApiKeyTest.php`: cover `ApiKeyContext::forCompany()`.
- Create `tests/Feature/JwtTokenTestHelperTest.php`: cover test helper token creation and authenticated requests.
- Modify docs:
  - `README.md`
  - `docs/core-concepts/token-context-scopes-claims.md`
  - `docs/core-concepts/refresh-rotation-revocation.md`
  - `docs/tutorials/tenant-isolation.md`
  - `docs/tutorials/api-key-client-usage.md`
  - `docs/tutorials/testing.md`
  - `docs/advanced/api-reference.md`

---

### Task 1: TokenContext SaaS Helpers

**Files:**
- Modify: `src/DTO/TokenContext.php`
- Modify: `tests/Unit/TokenContextTest.php`

- [ ] **Step 1: Write failing tests for company helpers**

Add this test to `tests/Unit/TokenContextTest.php`:

```php
public function test_context_builds_company_claims_subject_and_impersonation(): void
{
    $context = TokenContext::make()
        ->companyId(42)
        ->companyIds([42, '84'])
        ->impersonated();

    self::assertEquals(new TokenSubject('company', '42'), $context->subjectValue());
    self::assertSame(42, $context->claims['company_id']);
    self::assertSame([42, '84'], $context->claims['company_ids']);
    self::assertTrue($context->claims['impersonated']);
}
```

- [ ] **Step 2: Write failing tests for tenant helpers and generic claim helper**

Add this test to `tests/Unit/TokenContextTest.php`:

```php
public function test_context_builds_tenant_claims_and_metadata(): void
{
    $context = TokenContext::make()
        ->tenantId('tenant-1')
        ->tenantIds(['tenant-1', 'tenant-2'])
        ->claim('role', 'owner')
        ->metadata(['login_ip' => '127.0.0.1']);

    self::assertEquals(new TokenSubject('tenant', 'tenant-1'), $context->subjectValue());
    self::assertSame('tenant-1', $context->claims['tenant_id']);
    self::assertSame(['tenant-1', 'tenant-2'], $context->claims['tenant_ids']);
    self::assertSame('owner', $context->claims['role']);
    self::assertSame(['login_ip' => '127.0.0.1'], $context->metadata);
}
```

- [ ] **Step 3: Run tests and verify failure**

Run:

```bash
composer test -- --filter TokenContextTest
```

Expected: tests fail with errors for undefined methods `companyId`, `companyIds`, `tenantId`, `tenantIds`, `impersonated`, `claim`, and `metadata`.

- [ ] **Step 4: Implement TokenContext helper methods**

Add these methods to `src/DTO/TokenContext.php` after `replaceClaim()`:

```php
public function claim(string $key, mixed $value): self
{
    return $this->replaceClaim($key, $value);
}

public function companyId(int|string $companyId): self
{
    return $this
        ->subject('company', (string) $companyId)
        ->replaceClaim('company_id', $companyId);
}

public function companyIds(array $companyIds): self
{
    return $this->replaceClaim('company_ids', array_values($companyIds));
}

public function tenantId(int|string $tenantId): self
{
    return $this
        ->subject('tenant', (string) $tenantId)
        ->replaceClaim('tenant_id', $tenantId);
}

public function tenantIds(array $tenantIds): self
{
    return $this->replaceClaim('tenant_ids', array_values($tenantIds));
}

public function impersonated(bool $value = true): self
{
    return $this->replaceClaim('impersonated', $value);
}

public function metadata(array $metadata): self
{
    return new self(
        $this->scopes,
        $this->claims,
        $this->subjectType,
        $this->subjectId,
        $this->audience,
        $this->deviceId,
        $this->deviceName,
        $this->sessionId,
        $metadata,
    );
}
```

- [ ] **Step 5: Run focused tests**

Run:

```bash
composer test -- --filter TokenContextTest
```

Expected: `TokenContextTest` passes.

- [ ] **Step 6: Commit**

```bash
git add src/DTO/TokenContext.php tests/Unit/TokenContextTest.php
git commit -m "feat: add token context saas helpers"
```

---

### Task 2: JwtAccessToken Read Helpers

**Files:**
- Modify: `src/Models/JwtAccessToken.php`
- Create: `tests/Unit/JwtAccessTokenHelperTest.php`

- [ ] **Step 1: Write failing token helper tests**

Create `tests/Unit/JwtAccessTokenHelperTest.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sopheak\JwtAuth\Models\JwtAccessToken;
use Sopheak\JwtAuth\DTO\TokenSubject;

final class JwtAccessTokenHelperTest extends TestCase
{
    public function test_token_exposes_common_saas_claims(): void
    {
        $token = new JwtAccessToken([
            'subject_type' => 'company',
            'subject_id' => '42',
            'claims' => [
                'company_id' => 42,
                'company_ids' => [42, 84],
                'tenant_id' => 'tenant-1',
                'tenant_ids' => ['tenant-1', 'tenant-2'],
                'impersonated' => true,
            ],
        ]);

        self::assertSame(42, $token->companyId());
        self::assertSame([42, 84], $token->companyIds());
        self::assertSame('tenant-1', $token->tenantId());
        self::assertSame(['tenant-1', 'tenant-2'], $token->tenantIds());
        self::assertTrue($token->isImpersonated());
        self::assertEquals(new TokenSubject('company', '42'), $token->subject());
    }

    public function test_token_helper_defaults_are_safe(): void
    {
        $token = new JwtAccessToken(['claims' => []]);

        self::assertNull($token->companyId());
        self::assertSame([], $token->companyIds());
        self::assertNull($token->tenantId());
        self::assertSame([], $token->tenantIds());
        self::assertFalse($token->isImpersonated());
    }
}
```

- [ ] **Step 2: Run tests and verify failure**

Run:

```bash
composer test -- --filter JwtAccessTokenHelperTest
```

Expected: tests fail with undefined method errors.

- [ ] **Step 3: Implement JwtAccessToken helpers**

Add these methods to `src/Models/JwtAccessToken.php` after `claim()`:

```php
public function companyId(): int|string|null
{
    $value = $this->claim('company_id');

    return is_int($value) || is_string($value) ? $value : null;
}

public function companyIds(): array
{
    $value = $this->claim('company_ids', []);

    return is_array($value) ? array_values($value) : [];
}

public function tenantId(): int|string|null
{
    $value = $this->claim('tenant_id');

    return is_int($value) || is_string($value) ? $value : null;
}

public function tenantIds(): array
{
    $value = $this->claim('tenant_ids', []);

    return is_array($value) ? array_values($value) : [];
}

public function isImpersonated(): bool
{
    return (bool) $this->claim('impersonated', false);
}
```

- [ ] **Step 4: Run focused tests**

Run:

```bash
composer test -- --filter JwtAccessTokenHelperTest
```

Expected: `JwtAccessTokenHelperTest` passes.

- [ ] **Step 5: Commit**

```bash
git add src/Models/JwtAccessToken.php tests/Unit/JwtAccessTokenHelperTest.php
git commit -m "feat: add jwt access token context helpers"
```

---

### Task 3: TokenResponse Extension Hook

**Files:**
- Modify: `src/Support/TokenResponse.php`
- Modify: `tests/Feature/CommandTest.php`

- [ ] **Step 1: Write failing response extension test**

Add this test to `tests/Feature/CommandTest.php`:

```php
public function test_token_response_can_be_extended_globally(): void
{
    TokenResponse::extend(function (array $response, TokenPair $pair): array {
        $response['company_id'] = $pair->accessTokenRecord->companyId();
        $response['impersonated'] = $pair->accessTokenRecord->isImpersonated();

        return $response;
    });

    $pair = app(JwtTokenService::class)->issueTokenPair(
        $this->createUser(),
        TokenContext::make()->companyId(42)->impersonated(),
    );

    $response = TokenResponse::passportCompatible($pair);

    self::assertSame(42, $response['company_id']);
    self::assertTrue($response['impersonated']);

    TokenResponse::flushExtensions();
}
```

Add these imports to `tests/Feature/CommandTest.php`:

```php
use Sopheak\JwtAuth\DTO\TokenPair;
```

- [ ] **Step 2: Run test and verify failure**

Run:

```bash
composer test -- --filter test_token_response_can_be_extended_globally
```

Expected: test fails because `TokenResponse::extend()` is undefined.

- [ ] **Step 3: Implement response extensions**

Replace `src/Support/TokenResponse.php` with:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Support;

use Sopheak\JwtAuth\DTO\TokenPair;

final class TokenResponse
{
    /** @var list<callable(array<string, mixed>, TokenPair): array<string, mixed>> */
    private static array $extensions = [];

    public static function extend(callable $callback): void
    {
        self::$extensions[] = $callback;
    }

    public static function flushExtensions(): void
    {
        self::$extensions = [];
    }

    public static function passportCompatible(TokenPair $pair, array $extra = []): array
    {
        $response = array_merge([
            'token_type' => 'Bearer',
            'expires_in' => $pair->expiresIn(),
            'access_token' => $pair->accessToken,
            'refresh_token' => $pair->refreshToken,
        ], $extra);

        foreach (self::$extensions as $extension) {
            $response = $extension($response, $pair);
        }

        return $response;
    }
}
```

- [ ] **Step 4: Run focused tests**

Run:

```bash
composer test -- --filter "CommandTest|JwtAccessTokenHelperTest|TokenContextTest"
```

Expected: tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/Support/TokenResponse.php tests/Feature/CommandTest.php
git commit -m "feat: allow token response extensions"
```

---

### Task 4: Session and Device Convenience Methods

**Files:**
- Modify: `src/Services/JwtTokenService.php`
- Modify: `tests/Feature/RevokeTokensTest.php`

- [ ] **Step 1: Write failing test for revoke all except current session**

Add this test to `tests/Feature/RevokeTokensTest.php`:

```php
public function test_revoke_all_for_user_can_keep_current_session(): void
{
    $service = app(JwtTokenService::class);
    $user = $this->createUser();

    $current = $service->issueTokenPair($user, TokenContext::make()->sessionId('11111111-1111-1111-1111-111111111111'));
    $other = $service->issueTokenPair($user, TokenContext::make()->sessionId('22222222-2222-2222-2222-222222222222'));

    $service->revokeAllForUser($user, exceptSessionId: $current->accessTokenRecord->session_id);

    self::assertNull($current->accessTokenRecord->fresh()->revoked_at);
    self::assertNotNull($other->accessTokenRecord->fresh()->revoked_at);
}
```

- [ ] **Step 2: Write failing test for device revocation**

Add this test to `tests/Feature/RevokeTokensTest.php`:

```php
public function test_revoke_device_revokes_matching_device_tokens_for_user(): void
{
    $service = app(JwtTokenService::class);
    $user = $this->createUser();

    $mobile = $service->issueTokenPair($user, new TokenContext(deviceId: 'ios-1'));
    $web = $service->issueTokenPair($user, new TokenContext(deviceId: 'web-1'));

    $service->revokeDevice($user, 'ios-1');

    self::assertNotNull($mobile->accessTokenRecord->fresh()->revoked_at);
    self::assertNull($web->accessTokenRecord->fresh()->revoked_at);
}
```

- [ ] **Step 3: Write failing test for active session listing**

Add this test to `tests/Feature/RevokeTokensTest.php`:

```php
public function test_active_sessions_for_user_returns_active_access_tokens(): void
{
    $service = app(JwtTokenService::class);
    $user = $this->createUser();

    $active = $service->issueTokenPair($user, TokenContext::make()->sessionId('11111111-1111-1111-1111-111111111111'));
    $revoked = $service->issueTokenPair($user, TokenContext::make()->sessionId('22222222-2222-2222-2222-222222222222'));
    $service->revokeSession($revoked->accessTokenRecord->session_id);

    $sessions = $service->activeSessionsForUser($user);

    self::assertTrue($sessions->contains('id', $active->accessTokenRecord->id));
    self::assertFalse($sessions->contains('id', $revoked->accessTokenRecord->id));
}
```

- [ ] **Step 4: Run tests and verify failure**

Run:

```bash
composer test -- --filter RevokeTokensTest
```

Expected: tests fail because method signatures and new service methods do not exist.

- [ ] **Step 5: Implement service helpers**

Modify `src/Services/JwtTokenService.php`:

Add imports:

```php
use Illuminate\Database\Eloquent\Collection;
```

Replace `revokeAllForUser()` with:

```php
public function revokeAllForUser(Authenticatable $user, ?string $exceptSessionId = null): void
{
    $userType = $user::class;
    $userId = (string) $user->getAuthIdentifier();

    $accessQuery = JwtAccessToken::query()
        ->where('user_type', $userType)
        ->where('user_id', $userId)
        ->whereNull('revoked_at');

    $refreshQuery = JwtRefreshToken::query()
        ->where('user_type', $userType)
        ->where('user_id', $userId)
        ->whereNull('revoked_at');

    if ($exceptSessionId !== null) {
        $accessQuery->where('session_id', '!=', $exceptSessionId);
        $refreshQuery->where('session_id', '!=', $exceptSessionId);
    }

    $accessQuery->update(['revoked_at' => now()]);
    $refreshQuery->update(['revoked_at' => now()]);

    Event::dispatch(new AllUserTokensRevoked($user));
}
```

Add these methods after `revokeAllForUser()`:

```php
public function revokeDevice(Authenticatable $user, string $deviceId, ?string $exceptSessionId = null): void
{
    $userType = $user::class;
    $userId = (string) $user->getAuthIdentifier();

    $sessionIds = JwtAccessToken::query()
        ->where('user_type', $userType)
        ->where('user_id', $userId)
        ->where('device_id', $deviceId)
        ->when($exceptSessionId !== null, fn ($query) => $query->where('session_id', '!=', $exceptSessionId))
        ->pluck('session_id')
        ->all();

    if ($sessionIds === []) {
        return;
    }

    JwtAccessToken::query()
        ->where('user_type', $userType)
        ->where('user_id', $userId)
        ->whereIn('session_id', $sessionIds)
        ->whereNull('revoked_at')
        ->update(['revoked_at' => now()]);

    JwtRefreshToken::query()
        ->where('user_type', $userType)
        ->where('user_id', $userId)
        ->whereIn('session_id', $sessionIds)
        ->whereNull('revoked_at')
        ->update(['revoked_at' => now()]);
}

/** @return Collection<int, JwtAccessToken> */
public function activeSessionsForUser(Authenticatable $user): Collection
{
    return JwtAccessToken::query()
        ->where('user_type', $user::class)
        ->where('user_id', (string) $user->getAuthIdentifier())
        ->whereNull('revoked_at')
        ->where('expires_at', '>', now())
        ->orderByDesc('last_used_at')
        ->orderByDesc('created_at')
        ->get();
}
```

- [ ] **Step 6: Run focused tests**

Run:

```bash
composer test -- --filter RevokeTokensTest
```

Expected: `RevokeTokensTest` passes.

- [ ] **Step 7: Commit**

```bash
git add src/Services/JwtTokenService.php tests/Feature/RevokeTokensTest.php
git commit -m "feat: add session and device token helpers"
```

---

### Task 5: ApiKeyContext forCompany Factory

**Files:**
- Modify: `src/DTO/ApiKeyContext.php`
- Modify: `tests/Feature/ApiKeyTest.php`

- [ ] **Step 1: Write failing API key context test**

Add this test to `tests/Feature/ApiKeyTest.php`:

```php
public function test_api_key_context_can_be_created_for_company_integrations(): void
{
    config()->set('sp-jwt-auth.api_keys.enabled', true);

    $context = ApiKeyContext::forCompany(
        companyId: 42,
        name: 'QuickBooks sync',
        scopes: ['qbo.sync'],
        claims: ['environment' => 'production'],
        allowedIps: ['203.0.113.0/24'],
    );

    $result = app(ApiKeyService::class)->createApiKey($context);

    self::assertSame('company', $result->apiKey->owner_type);
    self::assertSame('42', $result->apiKey->owner_id);
    self::assertSame(['environment' => 'production', 'company_id' => 42], $result->apiKey->claims);
    self::assertSame(['qbo.sync'], $result->apiKey->scopes);
}
```

- [ ] **Step 2: Run test and verify failure**

Run:

```bash
composer test -- --filter test_api_key_context_can_be_created_for_company_integrations
```

Expected: test fails because `ApiKeyContext::forCompany()` is undefined.

- [ ] **Step 3: Implement factory**

Add this method to `src/DTO/ApiKeyContext.php`:

```php
public static function forCompany(
    int|string $companyId,
    string $name,
    array $scopes = [],
    array $claims = [],
    ?CarbonInterface $expiresAt = null,
    ?array $allowedIps = null,
    ?string $createdByType = null,
    ?string $createdById = null,
): self {
    return new self(
        ownerType: 'company',
        ownerId: (string) $companyId,
        name: $name,
        scopes: $scopes,
        claims: array_merge($claims, ['company_id' => $companyId]),
        expiresAt: $expiresAt,
        allowedIps: $allowedIps,
        createdByType: $createdByType,
        createdById: $createdById,
    );
}
```

- [ ] **Step 4: Run focused tests**

Run:

```bash
composer test -- --filter ApiKeyTest
```

Expected: `ApiKeyTest` passes.

- [ ] **Step 5: Commit**

```bash
git add src/DTO/ApiKeyContext.php tests/Feature/ApiKeyTest.php
git commit -m "feat: add company api key context factory"
```

---

### Task 6: JwtTokenTestHelper

**Files:**
- Create: `src/Testing/JwtTokenTestHelper.php`
- Create: `tests/Feature/JwtTokenTestHelperTest.php`

- [ ] **Step 1: Write failing helper test**

Create `tests/Feature/JwtTokenTestHelperTest.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Sopheak\JwtAuth\Testing\JwtTokenTestHelper;
use Sopheak\JwtAuth\Tests\TestCase;

final class JwtTokenTestHelperTest extends TestCase
{
    public function test_helper_creates_token_pair_with_company_claims(): void
    {
        $user = $this->createUser();

        $pair = JwtTokenTestHelper::createToken(
            user: $user,
            scopes: ['client'],
            claims: ['company_id' => 42],
            subjectType: 'company',
            subjectId: '42',
        );

        self::assertSame(['client'], $pair->accessTokenRecord->scopes);
        self::assertSame(42, $pair->accessTokenRecord->claim('company_id'));
        self::assertSame('company', $pair->accessTokenRecord->subject_type);
        self::assertSame('42', $pair->accessTokenRecord->subject_id);
        self::assertNotEmpty($pair->accessToken);
        self::assertNotEmpty($pair->refreshToken);
    }
}
```

- [ ] **Step 2: Run test and verify failure**

Run:

```bash
composer test -- --filter JwtTokenTestHelperTest
```

Expected: test fails because `Sopheak\JwtAuth\Testing\JwtTokenTestHelper` does not exist.

- [ ] **Step 3: Implement helper**

Create `src/Testing/JwtTokenTestHelper.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Testing;

use Illuminate\Contracts\Auth\Authenticatable;
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\DTO\TokenPair;
use Sopheak\JwtAuth\Services\JwtTokenService;

final class JwtTokenTestHelper
{
    public static function createToken(
        Authenticatable $user,
        array $scopes = [],
        array $claims = [],
        ?string $subjectType = null,
        ?string $subjectId = null,
        ?string $deviceId = null,
        ?string $deviceName = null,
        ?string $sessionId = null,
    ): TokenPair {
        $context = new TokenContext(
            scopes: $scopes,
            claims: $claims,
            subjectType: $subjectType,
            subjectId: $subjectId,
            deviceId: $deviceId,
            deviceName: $deviceName,
            sessionId: $sessionId,
        );

        return app(JwtTokenService::class)->issueTokenPair($user, $context);
    }
}
```

- [ ] **Step 4: Run focused tests**

Run:

```bash
composer test -- --filter JwtTokenTestHelperTest
```

Expected: `JwtTokenTestHelperTest` passes.

- [ ] **Step 5: Commit**

```bash
git add src/Testing/JwtTokenTestHelper.php tests/Feature/JwtTokenTestHelperTest.php
git commit -m "feat: add jwt token testing helper"
```

---

### Task 7: Documentation Updates

**Files:**
- Modify: `README.md`
- Modify: `docs/core-concepts/token-context-scopes-claims.md`
- Modify: `docs/core-concepts/refresh-rotation-revocation.md`
- Modify: `docs/tutorials/tenant-isolation.md`
- Modify: `docs/tutorials/api-key-client-usage.md`
- Modify: `docs/tutorials/testing.md`
- Modify: `docs/advanced/api-reference.md`

- [ ] **Step 1: Update TokenContext examples**

In `README.md`, `docs/core-concepts/token-context-scopes-claims.md`, and `docs/tutorials/tenant-isolation.md`, replace manual company examples with:

```php
$context = TokenContext::make()
    ->companyId($activeCompanyId)
    ->companyIds($allowedCompanyIds)
    ->impersonated($isImpersonating)
    ->scopes(['invoices.read', 'invoices.write']);
```

Add this clarification near the first example:

```md
`companyId()` sets `subject('company', ...)` and `claims['company_id']`. `companyIds()` stores app-readable company access in `claims['company_ids']`. The package still keeps the database schema generic.
```

- [ ] **Step 2: Update token response docs**

In `docs/core-concepts/token-context-scopes-claims.md`, add:

```php
TokenResponse::extend(function (array $response, TokenPair $pair): array {
    $response['company_id'] = $pair->accessTokenRecord->companyId();
    $response['impersonated'] = $pair->accessTokenRecord->isImpersonated();

    return $response;
});
```

- [ ] **Step 3: Update API key docs**

In `docs/tutorials/api-key-client-usage.md`, add:

```php
$context = ApiKeyContext::forCompany(
    companyId: 42,
    name: 'QuickBooks sync worker',
    scopes: ['qbo.sync'],
);
```

- [ ] **Step 4: Update revocation docs**

In `docs/core-concepts/refresh-rotation-revocation.md`, add:

```php
app(JwtTokenService::class)->revokeAllForUser($user, exceptSessionId: $currentSessionId);
app(JwtTokenService::class)->revokeDevice($user, $deviceId);
$sessions = app(JwtTokenService::class)->activeSessionsForUser($user);
```

- [ ] **Step 5: Update testing docs**

In `docs/tutorials/testing.md`, add:

```php
$pair = JwtTokenTestHelper::createToken(
    user: $user,
    scopes: ['client'],
    claims: ['company_id' => 42],
    subjectType: 'company',
    subjectId: '42',
);
```

- [ ] **Step 6: Update API reference**

In `docs/advanced/api-reference.md`, add rows for:

```md
| `TokenContext::companyId()` | Set company subject and `company_id` claim |
| `TokenContext::companyIds()` | Set `company_ids` claim |
| `TokenContext::impersonated()` | Set impersonation claim |
| `JwtAccessToken::companyId()` | Read `company_id` claim |
| `TokenResponse::extend()` | Register global token response extension |
| `JwtTokenTestHelper` | Issue test token pairs |
```

- [ ] **Step 7: Commit**

```bash
git add README.md docs/core-concepts/token-context-scopes-claims.md docs/core-concepts/refresh-rotation-revocation.md docs/tutorials/tenant-isolation.md docs/tutorials/api-key-client-usage.md docs/tutorials/testing.md docs/advanced/api-reference.md
git commit -m "docs: document saas convenience helpers"
```

---

### Task 8: Full Verification

**Files:**
- Verify all modified source, tests, and docs.

- [ ] **Step 1: Run targeted tests**

Run:

```bash
composer test -- --filter "TokenContextTest|JwtAccessTokenHelperTest|CommandTest|RevokeTokensTest|ApiKeyTest|JwtTokenTestHelperTest"
```

Expected: all targeted tests pass.

- [ ] **Step 2: Run full quality suite**

Run:

```bash
composer quality
```

Expected: Rector dry run passes, PHPStan passes, PHPUnit passes.

- [ ] **Step 3: Run Composer validation**

Run:

```bash
composer validate --strict --no-check-publish --no-check-lock
```

Expected: valid composer metadata. If this fails because `"version"` exists in `composer.json`, remove `"version"` from `composer.json` in a separate fix because VCS packages should use Git tags for versions.

- [ ] **Step 4: Review docs for APIs that do not exist**

Run:

```bash
rg -n "companyId\\(|companyIds\\(|tenantId\\(|tenantIds\\(|impersonated\\(|TokenResponse::extend|JwtTokenTestHelper|revokeDevice|activeSessionsForUser|forCompany" README.md docs src tests
```

Expected: every documented helper has a matching implementation in `src/` and a matching test in `tests/`.

- [ ] **Step 5: Commit verification fixes if needed**

If formatting or docs references need small corrections after verification:

```bash
git add README.md docs src tests
git commit -m "chore: verify saas convenience helpers"
```

---

## Self-Review

- Spec coverage: This plan covers TokenContext helpers, token read helpers, response extension hook, test helper, API key service-to-service convenience, device/session helpers, and docs.
- Placeholder scan: The plan contains no deferred implementation placeholders. Every task has concrete files, code snippets, commands, and expected results.
- Type consistency: `TokenContext`, `TokenPair`, `JwtAccessToken`, `JwtTokenService`, `ApiKeyContext`, `TokenResponse`, and `JwtTokenTestHelper` names match current package namespaces and planned APIs.

