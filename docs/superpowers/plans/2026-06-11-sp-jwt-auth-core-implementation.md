# Core JWT Auth Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build the v1.0 `sopheak/sp-jwt-auth` Core JWT module: Laravel guard, JWT access tokens, rotating opaque refresh tokens, revocation, scopes, claims, token context, key rotation, JWKS, lifecycle events, developer hooks, and core maintenance commands.

**Architecture:** This package is a Laravel package with a small service provider, publishable config and migrations, Eloquent token models, DTO/value objects for public API boundaries, and services for signing, hashing, issuing, validating, refreshing, and revoking tokens. Optional modules from the spec remain disabled and absent from the default install until separate release plans implement them.

**Tech Stack:** PHP `^8.3|^8.4|^8.5`, Laravel `^12.0|^13.0`, `firebase/php-jwt:^7.0`, Orchestra Testbench `^10|^11`, PHPUnit `^10|^11`, PHPStan/Larastan, Rector.

---

## Scope Boundary

This plan implements the required-by-default `v1.0` Core JWT module only.

Included:

- Package identity cleanup from copied `sp-jwt-auth` metadata.
- Composer namespace `Sopheak\JwtAuth`.
- Config `config/sp-jwt-auth.php`.
- Migrations and models for `sp_jwt_access_tokens` and `sp_jwt_refresh_tokens`.
- `JwtTokenService`, `JwtGuard`, `HasJwtTokens`, scope middleware, token DTOs, hook registry, lifecycle events.
- JWT signing key lookup, active/previous/compromised `kid` validation, JWKS response, HMAC hash-key support for refresh tokens.
- Artisan commands: `sp-jwt-auth:install`, `sp-jwt-auth:keys`, `sp-jwt-auth:jwks`, `sp-jwt-auth:prune`.
- Documentation for install, guard setup, issue/refresh/revoke, key rotation, Passport migration, hooks, and security notes.

Excluded from this plan:

- v1.1 Account Security: MFA challenge broker, OTP, email verification, password reset, mail templates.
- v1.2 SaaS Integrations: API keys, tenant resolver contracts, session/device management UI helpers, structured audit payloads beyond core events.
- v2.0 External Identity: Socialite, generic OIDC, passkeys/WebAuthn extension contracts.
- v2.1 OAuth2 Server: OAuth clients, consent, authorization code + PKCE, client credentials, OAuth resource guard.

Create separate plans after this one:

- `docs/superpowers/plans/2026-06-11-sp-jwt-auth-account-security.md`
- `docs/superpowers/plans/2026-06-11-sp-jwt-auth-api-keys.md`
- `docs/superpowers/plans/2026-06-11-sp-jwt-auth-external-identity.md`
- `docs/superpowers/plans/2026-06-11-sp-jwt-auth-oauth-server.md`

## Current Baseline

- The repository has no `src/`, `database/`, `config/`, `routes/`, or `tests/` directories.
- `composer.json` still autoloads `Sopheak\Core\` and registers `Sopheak\Core\CoreSpLaravelApiProvider`.
- `README.md`, `CHANGELOG.md`, and `docs/guide/**` are copied from `sp-jwt-auth`.
- The spec lives at `docs/superpowers/specs/2026-06-11-sp-jwt-auth-package-spec.md`.

## File Structure

Create:

- `config/sp-jwt-auth.php` - default Core JWT config and disabled optional module flags.
- `database/migrations/2026_06_11_000001_create_sp_jwt_access_tokens_table.php`
- `database/migrations/2026_06_11_000002_create_sp_jwt_refresh_tokens_table.php`
- `src/SpJwtAuthServiceProvider.php`
- `src/Contracts/TokenContextValidator.php`
- `src/DTO/TokenContext.php`
- `src/DTO/TokenPair.php`
- `src/DTO/TokenSubject.php`
- `src/Events/TokenIssued.php`
- `src/Events/TokenRefreshed.php`
- `src/Events/TokenRevoked.php`
- `src/Events/SessionRevoked.php`
- `src/Events/AllUserTokensRevoked.php`
- `src/Events/RefreshTokenReuseDetected.php`
- `src/Guards/JwtGuard.php`
- `src/Http/Middleware/RequireJwtScope.php`
- `src/Http/Middleware/RequireAnyJwtScope.php`
- `src/Models/JwtAccessToken.php`
- `src/Models/JwtRefreshToken.php`
- `src/Security/HashKey.php`
- `src/Security/HashKeyRepository.php`
- `src/Security/SecretHasher.php`
- `src/Signing/SigningKey.php`
- `src/Signing/SigningKeyRepository.php`
- `src/Signing/ConfigSigningKeyRepository.php`
- `src/Signing/JwksFormatter.php`
- `src/Services/JwtTokenService.php`
- `src/Support/HookRegistry.php`
- `src/Support/SpJwtAuth.php`
- `src/Support/TokenResponse.php`
- `src/Traits/HasJwtTokens.php`
- `src/Console/InstallCommand.php`
- `src/Console/KeysCommand.php`
- `src/Console/JwksCommand.php`
- `src/Console/PruneCommand.php`
- `routes/jwks.php`
- `tests/TestCase.php`
- `tests/Fixtures/User.php`
- `tests/Feature/GuardTest.php`
- `tests/Feature/PackageBootTest.php`
- `tests/Feature/RefreshRotationTest.php`
- `tests/Feature/RevokeTokensTest.php`
- `tests/Feature/ScopeMiddlewareTest.php`
- `tests/Feature/TokenIssueValidateTest.php`
- `tests/Feature/JwksTest.php`
- `tests/Unit/TokenContextTest.php`
- `tests/Unit/SecretHasherTest.php`

Modify:

- `composer.json` - package identity, namespace, dependencies, Laravel provider.
- `README.md` - replace copied `sp-jwt-auth` content with Core JWT install/usage docs.
- `CHANGELOG.md` - replace copied package name.
- `docs/ai/project-context.md` - replace copied project context.
- `docs/ai/commands.md` - correct commands for this package.
- `docs/ai/architecture.md` - document Core JWT boundaries.
- `rector.php` - keep `src` and `tests` paths.

Delete:

- `docs/guide/**` copied `sp-jwt-auth` guides after replacing the README and `docs/ai/**` with `sp-jwt-auth` documentation. The spec already says this package must not depend on `sopheak/sp-jwt-auth`, and leaving copied guides makes implementation error-prone.

---

### Task 1: Package Identity, Dependencies, and Test Harness

**Files:**
- Modify: `composer.json`
- Create: `phpunit.xml.dist`
- Create: `tests/Fixtures/keys/private.pem`
- Create: `tests/Fixtures/keys/public.pem`
- Create: `tests/TestCase.php`
- Create: `tests/Fixtures/User.php`
- Create: `tests/Feature/PackageBootTest.php`

- [ ] **Step 1: Replace Composer metadata**

Use this exact package shape, preserving the existing scripts where names still apply:

```json
{
  "name": "sopheak/sp-jwt-auth",
  "description": "First-party JWT access and rotating refresh token authentication for Laravel apps.",
  "type": "library",
  "license": "proprietary",
  "version": "0.1.10",
  "require": {
    "php": "^8.3|^8.4|^8.5",
    "firebase/php-jwt": "^7.0",
    "laravel/framework": "^12.0|^13.0",
    "nesbot/carbon": "^2.0|^3.0"
  },
  "require-dev": {
    "friendsofphp/php-cs-fixer": "^3.92",
    "larastan/larastan": "^3.0",
    "phpstan/phpstan": "2.1.32",
    "orchestra/testbench": "^10.0|^11.0",
    "phpunit/phpunit": "^10.0|^11.0",
    "rector/rector": "^2.0"
  },
  "autoload": {
    "psr-4": {
      "Sopheak\\JwtAuth\\": "src/"
    }
  },
  "autoload-dev": {
    "psr-4": {
      "Sopheak\\JwtAuth\\Tests\\": "tests/"
    }
  },
  "scripts": {
    "test": "vendor/bin/phpunit",
    "test-coverage": "vendor/bin/phpunit --coverage-html coverage",
    "analyse": "vendor/bin/phpstan analyse src tests",
    "format": "@rector:fix",
    "format-check": "@rector:check",
    "rector": "vendor/bin/rector process --dry-run",
    "rector:fix": "vendor/bin/rector process",
    "rector:check": "vendor/bin/rector process --dry-run --no-progress-bar",
    "quality": [
      "@format-check",
      "@analyse",
      "@test"
    ]
  },
  "suggest": {
    "laravel/socialite": "Required for the optional Socialite external identity module.",
    "socialiteproviders/manager": "Required for optional community Socialite providers.",
    "league/oauth2-client": "Required for the optional generic OAuth2/OIDC adapter module.",
    "league/oauth2-server": "Required for the optional OAuth2 authorization server module."
  },
  "extra": {
    "laravel": {
      "providers": [
        "Sopheak\\JwtAuth\\SpJwtAuthServiceProvider"
      ]
    }
  },
  "minimum-stability": "stable",
  "prefer-stable": true,
  "config": {
    "sort-packages": true,
    "allow-plugins": {
      "php-http/discovery": true
    }
  }
}
```

- [ ] **Step 2: Install dependencies and refresh autoload**

Run:

```bash
composer update --with-all-dependencies
composer dump-autoload
```

Expected:

```text
Generating autoload files
```

- [ ] **Step 3: Add PHPUnit config**

Create `phpunit.xml.dist`:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="vendor/autoload.php" colors="true" failOnRisky="true" failOnWarning="true">
    <testsuites>
        <testsuite name="sp-jwt-auth">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
    <php>
        <env name="APP_ENV" value="testing"/>
        <env name="DB_CONNECTION" value="sqlite"/>
        <env name="DB_DATABASE" value=":memory:"/>
        <env name="SP_JWT_ISSUER" value="https://jwt-auth.test"/>
        <env name="SP_JWT_AUDIENCE" value="sp-jwt-auth-tests"/>
    </php>
</phpunit>
```

- [ ] **Step 4: Generate test RSA keys**

Create the fixture keys before booting Testbench because `tests/TestCase.php` loads them during application setup:

```bash
mkdir -p tests/Fixtures/keys
openssl genrsa -out tests/Fixtures/keys/private.pem 2048
openssl rsa -in tests/Fixtures/keys/private.pem -pubout -out tests/Fixtures/keys/public.pem
```

- [ ] **Step 5: Create Testbench base case**

Create `tests/TestCase.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;
use Sopheak\JwtAuth\SpJwtAuthServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [SpJwtAuthServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('auth.defaults.guard', 'api');
        $app['config']->set('auth.guards.api', [
            'driver' => 'sp-jwt',
            'provider' => 'users',
        ]);
        $app['config']->set('auth.providers.users', [
            'driver' => 'eloquent',
            'model' => Fixtures\User::class,
        ]);
        $app['config']->set('sp-jwt-auth.issuer', 'https://jwt-auth.test');
        $app['config']->set('sp-jwt-auth.audience', 'sp-jwt-auth-tests');
        $app['config']->set('sp-jwt-auth.algorithm', 'RS256');
        $app['config']->set('sp-jwt-auth.keys.active_kid', 'test-active');
        $app['config']->set('sp-jwt-auth.keys.items.test-active', [
            'state' => 'active',
            'private_key' => self::privateKey(),
            'public_key' => self::publicKey(),
        ]);
        $app['config']->set('sp-jwt-auth.hash_keys.active_id', 'test-hash');
        $app['config']->set('sp-jwt-auth.hash_keys.items.test-hash', [
            'state' => 'active',
            'key' => '0123456789abcdef0123456789abcdef',
        ]);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->app['db']->connection()->getSchemaBuilder()->create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('username')->nullable()->unique();
            $table->string('password');
            $table->timestamps();
        });
    }

    protected function createUser(array $attributes = []): Fixtures\User
    {
        return Fixtures\User::query()->create(array_merge([
            'name' => 'Test User',
            'email' => 'user@example.com',
            'username' => 'testuser',
            'password' => bcrypt('password'),
        ], $attributes));
    }

    protected static function privateKey(): string
    {
        return (string) file_get_contents(__DIR__ . '/Fixtures/keys/private.pem');
    }

    protected static function publicKey(): string
    {
        return (string) file_get_contents(__DIR__ . '/Fixtures/keys/public.pem');
    }
}
```

- [ ] **Step 6: Create test user fixture**

Create `tests/Fixtures/User.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Sopheak\JwtAuth\Traits\HasJwtTokens;

final class User extends Authenticatable
{
    use HasJwtTokens;

    protected $guarded = [];

    protected $hidden = ['password'];
}
```

- [ ] **Step 7: Add provider boot smoke test**

Create `tests/Feature/PackageBootTest.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Sopheak\JwtAuth\Services\JwtTokenService;
use Sopheak\JwtAuth\Tests\TestCase;

final class PackageBootTest extends TestCase
{
    public function test_package_registers_config_guard_and_token_service(): void
    {
        self::assertSame('sp-jwt', config('sp-jwt-auth.driver'));
        self::assertTrue($this->app->bound(JwtTokenService::class));
        self::assertNotNull($this->app->make(AuthFactory::class)->guard('api'));
    }
}
```

- [ ] **Step 8: Run the smoke test and confirm the expected failure**

Run:

```bash
composer test -- --filter PackageBootTest
```

Expected: FAIL because `Sopheak\JwtAuth\SpJwtAuthServiceProvider` and `HasJwtTokens` do not exist yet.

- [ ] **Step 9: Commit**

```bash
git add composer.json phpunit.xml.dist tests
git commit -m "chore: set up sp jwt auth package identity"
```

---

### Task 2: Config, Service Provider, and Laravel Registration

**Files:**
- Create: `config/sp-jwt-auth.php`
- Create: `src/SpJwtAuthServiceProvider.php`
- Create: `src/Support/SpJwtAuth.php`
- Create: `routes/jwks.php`
- Modify: `tests/Feature/PackageBootTest.php`

- [ ] **Step 1: Create default config**

Create `config/sp-jwt-auth.php` with the Core JWT defaults and disabled optional module switches:

```php
<?php

declare(strict_types=1);

return [
    'guard' => env('SP_JWT_GUARD', 'api'),
    'driver' => env('SP_JWT_DRIVER', 'sp-jwt'),
    'user_provider' => env('SP_JWT_USER_PROVIDER', 'users'),
    'issuer' => env('SP_JWT_ISSUER', env('APP_URL')),
    'audience' => env('SP_JWT_AUDIENCE'),
    'algorithm' => env('SP_JWT_ALGORITHM', 'RS256'),
    'access_ttl_minutes' => (int) env('SP_JWT_ACCESS_TTL_MINUTES', 15),
    'refresh_ttl_days' => (int) env('SP_JWT_REFRESH_TTL_DAYS', 60),
    'clock_skew_seconds' => (int) env('SP_JWT_CLOCK_SKEW_SECONDS', 60),
    'rotate_refresh_tokens' => true,
    'reuse_detection' => env('SP_JWT_REUSE_DETECTION', 'revoke_session'),
    'keys' => [
        'active_kid' => env('SP_JWT_ACTIVE_KID', env('SP_JWT_KEY_ID')),
        'previous_kids' => array_values(array_filter(explode(',', (string) env('SP_JWT_PREVIOUS_KIDS', '')))),
        'compromised_kids' => array_values(array_filter(explode(',', (string) env('SP_JWT_COMPROMISED_KIDS', '')))),
        'jwks_enabled' => filter_var(env('SP_JWT_JWKS_ENABLED', true), FILTER_VALIDATE_BOOL),
        'jwks_route' => env('SP_JWT_JWKS_ROUTE', '/.well-known/sp-jwt-auth/jwks.json'),
        'rotation_grace_days' => (int) env('SP_JWT_KEY_ROTATION_GRACE_DAYS', 30),
        'items' => [],
    ],
    'hash_keys' => [
        'active_id' => env('SP_JWT_HASH_KEY_ID', 'default'),
        'items' => [
            'default' => [
                'state' => 'active',
                'key' => env('SP_JWT_REFRESH_HASH_KEY'),
            ],
        ],
    ],
    'optional_modules' => [
        'account_security' => false,
        'api_keys' => false,
        'external_identity' => false,
        'oauth_server' => false,
    ],
];
```

- [ ] **Step 2: Create service provider**

Create `src/SpJwtAuthServiceProvider.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Sopheak\JwtAuth\Console\InstallCommand;
use Sopheak\JwtAuth\Console\JwksCommand;
use Sopheak\JwtAuth\Console\KeysCommand;
use Sopheak\JwtAuth\Console\PruneCommand;
use Sopheak\JwtAuth\Guards\JwtGuard;
use Sopheak\JwtAuth\Security\HashKeyRepository;
use Sopheak\JwtAuth\Security\SecretHasher;
use Sopheak\JwtAuth\Services\JwtTokenService;
use Sopheak\JwtAuth\Signing\ConfigSigningKeyRepository;
use Sopheak\JwtAuth\Signing\SigningKeyRepository;
use Sopheak\JwtAuth\Support\HookRegistry;

final class SpJwtAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/sp-jwt-auth.php', 'sp-jwt-auth');

        $this->app->singleton(HookRegistry::class);
        $this->app->singleton(SigningKeyRepository::class, ConfigSigningKeyRepository::class);
        $this->app->singleton(HashKeyRepository::class);
        $this->app->singleton(SecretHasher::class);
        $this->app->singleton(JwtTokenService::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/sp-jwt-auth.php' => config_path('sp-jwt-auth.php'),
        ], 'sp-jwt-auth-config');

        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'sp-jwt-auth-migrations');

        Auth::extend('sp-jwt', function (Application $app, string $name, array $config): JwtGuard {
            $providerName = $config['provider'] ?? config('sp-jwt-auth.user_provider', 'users');

            return new JwtGuard(
                $app->make(JwtTokenService::class),
                $app['auth']->createUserProvider($providerName),
                $app['request'],
            );
        });

        $router = $this->app['router'];
        $router->aliasMiddleware('sp.jwt.scope', \Sopheak\JwtAuth\Http\Middleware\RequireJwtScope::class);
        $router->aliasMiddleware('sp.jwt.any_scope', \Sopheak\JwtAuth\Http\Middleware\RequireAnyJwtScope::class);

        if ((bool) config('sp-jwt-auth.keys.jwks_enabled', true)) {
            Route::group([], __DIR__ . '/../routes/jwks.php');
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                KeysCommand::class,
                JwksCommand::class,
                PruneCommand::class,
            ]);
        }
    }
}
```

- [ ] **Step 3: Create facade-style hook entrypoint**

Create `src/Support/SpJwtAuth.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Support;

final class SpJwtAuth
{
    public static function hooks(): HookRegistry
    {
        return app(HookRegistry::class);
    }
}
```

- [ ] **Step 4: Create JWKS route**

Create `routes/jwks.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Sopheak\JwtAuth\Signing\JwksFormatter;
use Sopheak\JwtAuth\Signing\SigningKeyRepository;

Route::get(config('sp-jwt-auth.keys.jwks_route', '/.well-known/sp-jwt-auth/jwks.json'), function (
    SigningKeyRepository $keys,
    JwksFormatter $formatter,
) {
    return response()->json($formatter->format($keys->publicKeys(activeOnly: false)));
})->name('sp-jwt-auth.jwks');
```

- [ ] **Step 5: Run the smoke test**

Run:

```bash
composer test -- --filter PackageBootTest
```

Expected: FAIL on missing command, guard, middleware, signing, and service classes that are created in later tasks.

- [ ] **Step 6: Commit**

```bash
git add config src/SpJwtAuthServiceProvider.php src/Support/SpJwtAuth.php routes tests/Feature/PackageBootTest.php
git commit -m "feat: register sp jwt auth package services"
```

---

### Task 3: Token Tables and Eloquent Models

**Files:**
- Create: `database/migrations/2026_06_11_000001_create_sp_jwt_access_tokens_table.php`
- Create: `database/migrations/2026_06_11_000002_create_sp_jwt_refresh_tokens_table.php`
- Create: `src/Models/JwtAccessToken.php`
- Create: `src/Models/JwtRefreshToken.php`
- Create: `tests/Feature/MigrationTest.php`

- [ ] **Step 1: Write migration test**

Create `tests/Feature/MigrationTest.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Sopheak\JwtAuth\Tests\TestCase;

final class MigrationTest extends TestCase
{
    public function test_core_token_tables_are_created(): void
    {
        self::assertTrue(Schema::hasTable('sp_jwt_access_tokens'));
        self::assertTrue(Schema::hasTable('sp_jwt_refresh_tokens'));
        self::assertTrue(Schema::hasColumns('sp_jwt_access_tokens', [
            'id', 'user_type', 'user_id', 'session_id', 'scopes', 'claims', 'issuer',
            'audience', 'last_used_at', 'revoked_at', 'expires_at',
        ]));
        self::assertTrue(Schema::hasColumns('sp_jwt_refresh_tokens', [
            'id', 'access_token_id', 'user_type', 'user_id', 'session_id', 'secret_hash',
            'hash_key_id', 'scopes', 'claims', 'replaced_by_id', 'revoked_at', 'expires_at',
        ]));
    }
}
```

- [ ] **Step 2: Run the migration test and confirm failure**

Run:

```bash
composer test -- --filter MigrationTest
```

Expected: FAIL because token migrations do not exist.

- [ ] **Step 3: Create access token migration**

Create `database/migrations/2026_06_11_000001_create_sp_jwt_access_tokens_table.php` with string-compatible user ids and database-agnostic JSON columns:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sp_jwt_access_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('user_type');
            $table->string('user_id', 64);
            $table->uuid('session_id')->index('sp_jwt_access_tokens_session_index');
            $table->string('device_id')->nullable()->index('sp_jwt_access_tokens_device_index');
            $table->string('device_name')->nullable();
            $table->string('subject_type')->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->json('scopes');
            $table->json('claims');
            $table->string('issuer');
            $table->string('audience')->nullable();
            $table->string('key_id', 100)->nullable()->index('sp_jwt_access_tokens_key_index');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('last_used_at')->nullable()->index('sp_jwt_access_tokens_last_used_index');
            $table->timestamp('revoked_at')->nullable()->index('sp_jwt_access_tokens_revoked_index');
            $table->timestamp('expires_at')->index('sp_jwt_access_tokens_expiry_index');
            $table->timestamps();

            $table->index(['user_type', 'user_id'], 'sp_jwt_access_tokens_user_index');
            $table->index(['subject_type', 'subject_id'], 'sp_jwt_access_tokens_subject_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sp_jwt_access_tokens');
    }
};
```

- [ ] **Step 4: Create refresh token migration**

Create `database/migrations/2026_06_11_000002_create_sp_jwt_refresh_tokens_table.php`:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sp_jwt_refresh_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('access_token_id');
            $table->string('user_type');
            $table->string('user_id', 64);
            $table->uuid('session_id')->index('sp_jwt_refresh_tokens_session_index');
            $table->string('secret_hash', 128);
            $table->string('hash_key_id', 100)->nullable()->index('sp_jwt_refresh_tokens_hash_key_index');
            $table->json('scopes');
            $table->json('claims');
            $table->uuid('replaced_by_id')->nullable()->index('sp_jwt_refresh_tokens_replaced_index');
            $table->timestamp('revoked_at')->nullable()->index('sp_jwt_refresh_tokens_revoked_index');
            $table->timestamp('expires_at')->index('sp_jwt_refresh_tokens_expiry_index');
            $table->timestamps();

            $table->index('access_token_id', 'sp_jwt_refresh_tokens_access_index');
            $table->index(['user_type', 'user_id'], 'sp_jwt_refresh_tokens_user_index');
            $table->foreign('access_token_id')
                ->references('id')
                ->on('sp_jwt_access_tokens')
                ->cascadeOnDelete();
            $table->foreign('replaced_by_id')
                ->references('id')
                ->on('sp_jwt_refresh_tokens')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sp_jwt_refresh_tokens');
    }
};
```

- [ ] **Step 5: Create token models**

Create `src/Models/JwtAccessToken.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Models;

use Illuminate\Database\Eloquent\Model;

final class JwtAccessToken extends Model
{
    protected $table = 'sp_jwt_access_tokens';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'scopes' => 'array',
        'claims' => 'array',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
```

Create `src/Models/JwtRefreshToken.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Models;

use Illuminate\Database\Eloquent\Model;

final class JwtRefreshToken extends Model
{
    protected $table = 'sp_jwt_refresh_tokens';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'scopes' => 'array',
        'claims' => 'array',
        'revoked_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
```

- [ ] **Step 6: Run migration tests**

Run:

```bash
composer test -- --filter MigrationTest
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add database src/Models tests/Feature/MigrationTest.php
git commit -m "feat: add core jwt token storage"
```

---

### Task 4: DTOs, Token Context, and Passport-Compatible User Trait

**Files:**
- Create: `src/DTO/TokenSubject.php`
- Create: `src/DTO/TokenContext.php`
- Create: `src/DTO/TokenPair.php`
- Create: `src/Traits/HasJwtTokens.php`
- Create: `tests/Unit/TokenContextTest.php`

- [ ] **Step 1: Write DTO tests**

Create `tests/Unit/TokenContextTest.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\DTO\TokenSubject;

final class TokenContextTest extends TestCase
{
    public function test_context_builds_subject_scopes_and_claims(): void
    {
        $context = TokenContext::make()
            ->sessionId('session-1')
            ->subject('tenant', '42')
            ->scopes(['client', 'tenant:42'])
            ->claims(['tenant_id' => 42])
            ->replaceClaim('tenant_role', 'owner');

        self::assertSame('session-1', $context->sessionId);
        self::assertEquals(new TokenSubject('tenant', '42'), $context->subjectValue());
        self::assertSame(['client', 'tenant:42'], $context->scopes);
        self::assertSame('owner', $context->claims['tenant_role']);
    }

    public function test_reserved_claim_names_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TokenContext::make()->claims(['exp' => 123]);
    }
}
```

- [ ] **Step 2: Run DTO tests and confirm failure**

Run:

```bash
composer test -- --filter TokenContextTest
```

Expected: FAIL because DTO classes do not exist.

- [ ] **Step 3: Create DTOs**

Create `src/DTO/TokenSubject.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\DTO;

final readonly class TokenSubject
{
    public function __construct(
        public string $type,
        public string $id,
    ) {
        if ($type === '' || $id === '') {
            throw new \InvalidArgumentException('Token subject type and id are required.');
        }
    }

    public function toArray(): array
    {
        return ['type' => $this->type, 'id' => $this->id];
    }
}
```

Create `src/DTO/TokenContext.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\DTO;

use Illuminate\Support\Str;

final readonly class TokenContext
{
    private const RESERVED_CLAIMS = [
        'iss', 'sub', 'aud', 'exp', 'nbf', 'iat', 'jti', 'sid', 'scopes', 'subject',
    ];

    public function __construct(
        public array $scopes = [],
        public array $claims = [],
        public ?string $subjectType = null,
        public ?string $subjectId = null,
        public ?string $audience = null,
        public ?string $deviceId = null,
        public ?string $deviceName = null,
        public ?string $sessionId = null,
        public array $metadata = [],
    ) {
        self::assertSafeClaims($claims);
    }

    public static function make(): self
    {
        return new self(sessionId: (string) Str::uuid());
    }

    public function scopes(array $scopes): self
    {
        return new self(array_values($scopes), $this->claims, $this->subjectType, $this->subjectId, $this->audience, $this->deviceId, $this->deviceName, $this->sessionId, $this->metadata);
    }

    public function replaceScopes(array $scopes): self
    {
        return $this->scopes($scopes);
    }

    public function claims(array $claims): self
    {
        self::assertSafeClaims($claims);

        return new self($this->scopes, $claims, $this->subjectType, $this->subjectId, $this->audience, $this->deviceId, $this->deviceName, $this->sessionId, $this->metadata);
    }

    public function replaceClaim(string $key, mixed $value): self
    {
        return $this->claims(array_merge($this->claims, [$key => $value]));
    }

    public function subject(string $type, string $id): self
    {
        return new self($this->scopes, $this->claims, $type, $id, $this->audience, $this->deviceId, $this->deviceName, $this->sessionId, $this->metadata);
    }

    public function sessionId(string $sessionId): self
    {
        return new self($this->scopes, $this->claims, $this->subjectType, $this->subjectId, $this->audience, $this->deviceId, $this->deviceName, $sessionId, $this->metadata);
    }

    public function subjectValue(): ?TokenSubject
    {
        return $this->subjectType !== null && $this->subjectId !== null
            ? new TokenSubject($this->subjectType, $this->subjectId)
            : null;
    }

    private static function assertSafeClaims(array $claims): void
    {
        foreach ($claims as $key => $value) {
            if (! is_string($key)) {
                throw new \InvalidArgumentException('Custom claim keys must be strings.');
            }

            if (in_array($key, self::RESERVED_CLAIMS, true)) {
                throw new \InvalidArgumentException("Custom claim [$key] is reserved.");
            }

            json_encode($value, JSON_THROW_ON_ERROR);
        }
    }
}
```

Create `src/DTO/TokenPair.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\DTO;

use Carbon\CarbonImmutable;
use Sopheak\JwtAuth\Models\JwtAccessToken;
use Sopheak\JwtAuth\Models\JwtRefreshToken;

final readonly class TokenPair
{
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public CarbonImmutable $accessTokenExpiresAt,
        public CarbonImmutable $refreshTokenExpiresAt,
        public JwtAccessToken $accessTokenRecord,
        public JwtRefreshToken $refreshTokenRecord,
    ) {
    }

    public function expiresIn(): int
    {
        return max(0, now()->diffInSeconds($this->accessTokenExpiresAt, false));
    }
}
```

- [ ] **Step 4: Create Passport-compatible trait**

Create `src/Traits/HasJwtTokens.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Traits;

use Sopheak\JwtAuth\Models\JwtAccessToken;

trait HasJwtTokens
{
    protected ?JwtAccessToken $spJwtAccessToken = null;

    public function withAccessToken(JwtAccessToken $token): static
    {
        $this->spJwtAccessToken = $token;

        return $this;
    }

    public function token(): ?JwtAccessToken
    {
        return $this->spJwtAccessToken;
    }

    public function tokenCan(string $scope): bool
    {
        $token = $this->token();

        if ($token === null) {
            return false;
        }

        return in_array('*', $token->scopes ?? [], true) || in_array($scope, $token->scopes ?? [], true);
    }
}
```

- [ ] **Step 5: Run DTO tests**

Run:

```bash
composer test -- --filter TokenContextTest
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/DTO src/Traits tests/Unit/TokenContextTest.php
git commit -m "feat: add token context value objects"
```

---

### Task 5: Signing Keys, Hash Keys, and JWKS

**Files:**
- Create: `src/Signing/SigningKey.php`
- Create: `src/Signing/SigningKeyRepository.php`
- Create: `src/Signing/ConfigSigningKeyRepository.php`
- Create: `src/Signing/JwksFormatter.php`
- Create: `src/Security/HashKey.php`
- Create: `src/Security/HashKeyRepository.php`
- Create: `src/Security/SecretHasher.php`
- Create: `tests/Unit/SecretHasherTest.php`
- Create: `tests/Feature/JwksTest.php`

- [ ] **Step 1: Write hashing tests**

Create `tests/Unit/SecretHasherTest.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Unit;

use Sopheak\JwtAuth\Security\HashKeyRepository;
use Sopheak\JwtAuth\Security\SecretHasher;
use Sopheak\JwtAuth\Tests\TestCase;

final class SecretHasherTest extends TestCase
{
    public function test_hashes_and_verifies_secret_with_active_key(): void
    {
        $hasher = new SecretHasher(new HashKeyRepository());

        $result = $hasher->hash('refresh-secret');

        self::assertSame('test-hash', $result['hash_key_id']);
        self::assertTrue($hasher->verify('refresh-secret', $result['hash'], $result['hash_key_id']));
        self::assertFalse($hasher->verify('wrong-secret', $result['hash'], $result['hash_key_id']));
    }
}
```

- [ ] **Step 2: Write JWKS test**

Create `tests/Feature/JwksTest.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Sopheak\JwtAuth\Tests\TestCase;

final class JwksTest extends TestCase
{
    public function test_jwks_exposes_public_key_only(): void
    {
        $response = $this->getJson('/.well-known/sp-jwt-auth/jwks.json');

        $response->assertOk()
            ->assertJsonPath('keys.0.kid', 'test-active')
            ->assertJsonMissing(['private_key']);
    }
}
```

- [ ] **Step 3: Create signing and hashing classes**

Implement these public contracts:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Signing;

final readonly class SigningKey
{
    public function __construct(
        public string $kid,
        public string $algorithm,
        public ?string $privateKey,
        public string $publicKey,
        public string $state,
    ) {
    }

    public function canSign(): bool
    {
        return $this->state === 'active' && $this->privateKey !== null;
    }

    public function canVerify(): bool
    {
        return in_array($this->state, ['active', 'previous'], true);
    }
}
```

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Signing;

interface SigningKeyRepository
{
    public function active(): SigningKey;

    public function forVerification(string $kid): SigningKey;

    /** @return list<SigningKey> */
    public function publicKeys(bool $activeOnly = false): array;
}
```

`ConfigSigningKeyRepository` must load `config('sp-jwt-auth.keys.items')`, reject missing active keys, reject `compromised` keys, and allow only `active` and `previous` for verification. `JwksFormatter` must convert RSA public keys to `kty=RSA`, `use=sig`, `kid`, `alg`, `n`, and `e`; it must never include private key material.

`HashKeyRepository` must expose active and lookup-by-id hash keys from `sp-jwt-auth.hash_keys`. `SecretHasher` must use `hash_hmac('sha256', $secret, $key)` and `hash_equals()`.

- [ ] **Step 4: Run hashing and JWKS tests**

Run:

```bash
composer test -- --filter "SecretHasherTest|JwksTest"
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Signing src/Security tests/Unit/SecretHasherTest.php tests/Feature/JwksTest.php
git commit -m "feat: add jwt signing and secret hashing"
```

---

### Task 6: Issue and Validate JWT Token Pairs

**Files:**
- Create: `src/Contracts/TokenContextValidator.php`
- Create: `src/Events/TokenIssued.php`
- Create: `src/Services/JwtTokenService.php`
- Create: `src/Support/HookRegistry.php`
- Create: `tests/Feature/TokenIssueValidateTest.php`

- [ ] **Step 1: Write issue and validate tests**

Create `tests/Feature/TokenIssueValidateTest.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Events\TokenIssued;
use Sopheak\JwtAuth\Models\JwtAccessToken;
use Sopheak\JwtAuth\Models\JwtRefreshToken;
use Sopheak\JwtAuth\Services\JwtTokenService;
use Sopheak\JwtAuth\Tests\TestCase;

final class TokenIssueValidateTest extends TestCase
{
    public function test_issue_token_pair_persists_rows_and_validates_access_token(): void
    {
        $user = $this->createUser();
        $service = app(JwtTokenService::class);

        $pair = $service->issueTokenPair($user, TokenContext::make()
            ->subject('tenant', '42')
            ->scopes(['client', 'tenant:42'])
            ->claims(['tenant_id' => 42]));

        self::assertNotSame('', $pair->accessToken);
        self::assertMatchesRegularExpression('/^[^.]+\\.[^.]+$/', $pair->refreshToken);
        self::assertSame(1, JwtAccessToken::query()->count());
        self::assertSame(1, JwtRefreshToken::query()->count());
        self::assertDatabaseMissing('sp_jwt_refresh_tokens', ['secret_hash' => explode('.', $pair->refreshToken, 2)[1]]);

        $accessToken = $service->validateAccessToken($pair->accessToken);

        self::assertSame($pair->accessTokenRecord->id, $accessToken->id);
        self::assertSame(['client', 'tenant:42'], $accessToken->scopes);
        self::assertSame(42, $accessToken->claims['tenant_id']);
    }
}
```

- [ ] **Step 2: Run issue/validate test and confirm failure**

Run:

```bash
composer test -- --filter TokenIssueValidateTest
```

Expected: FAIL because `JwtTokenService` does not exist.

- [ ] **Step 3: Create validator contract and hook registry**

Create `src/Contracts/TokenContextValidator.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Sopheak\JwtAuth\DTO\TokenContext;

interface TokenContextValidator
{
    public function validate(Authenticatable $user, TokenContext $context): void;
}
```

Create `src/Support/HookRegistry.php` with ordered core hooks:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Support;

final class HookRegistry
{
    /** @var list<class-string|callable> */
    private array $beforeTokenIssue = [];

    /** @var list<class-string|callable> */
    private array $validateTokenContext = [];

    /** @var list<class-string|callable> */
    private array $afterTokenIssue = [];

    public function beforeTokenIssue(string|callable $hook): self
    {
        $this->beforeTokenIssue[] = $hook;

        return $this;
    }

    public function validateTokenContext(string|callable $hook): self
    {
        $this->validateTokenContext[] = $hook;

        return $this;
    }

    public function afterTokenIssue(string|callable $hook): self
    {
        $this->afterTokenIssue[] = $hook;

        return $this;
    }

    public function beforeTokenIssueHooks(): array
    {
        return $this->beforeTokenIssue;
    }

    public function validateTokenContextHooks(): array
    {
        return $this->validateTokenContext;
    }

    public function afterTokenIssueHooks(): array
    {
        return $this->afterTokenIssue;
    }
}
```

- [ ] **Step 4: Create TokenIssued event**

Create `src/Events/TokenIssued.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Sopheak\JwtAuth\DTO\TokenPair;

final readonly class TokenIssued
{
    public function __construct(
        public Authenticatable $user,
        public TokenPair $pair,
    ) {
    }
}
```

- [ ] **Step 5: Implement issue and validate in `JwtTokenService`**

Create `src/Services/JwtTokenService.php` with these public methods first:

```php
issueTokenPair(Authenticatable $user, TokenContext $context): TokenPair;
validateAccessToken(string $jwt): JwtAccessToken;
```

Implementation requirements:

- Generate access token id with `Str::uuid()->toString()` and use it as JWT `jti`.
- Generate refresh token id with `Str::uuid()->toString()` and secret with `bin2hex(random_bytes(32))`.
- Return refresh token as `id.secret`.
- Store only HMAC `secret_hash`, never the plaintext refresh secret.
- Use `SigningKeyRepository::active()` and include `kid` in the JWT header.
- Use `Firebase\JWT\JWT::encode($payload, $privateKey, $algorithm, $kid)`.
- Set `iss`, `sub`, `jti`, `iat`, `nbf`, `exp`, `scopes`, `sid`, optional `aud`, optional `subject`, and custom claims.
- Persist scopes and claims in the DB row.
- Validation must decode with the configured algorithm for the token `kid`; reject unknown, retired, and compromised kids.
- Validation must check `iss`, configured `aud`, `jti` exists, token row is not revoked, and row `expires_at` is in the future.
- Return the persisted `Sopheak\JwtAuth\Models\JwtAccessToken` model.

- [ ] **Step 6: Run issue and validate test**

Run:

```bash
composer test -- --filter TokenIssueValidateTest
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add src/Contracts src/Events src/Services src/Support tests/Feature/TokenIssueValidateTest.php
git commit -m "feat: issue and validate jwt token pairs"
```

---

### Task 7: Refresh Rotation, Reuse Detection, and Revocation

**Files:**
- Modify: `src/Services/JwtTokenService.php`
- Create: `src/Events/TokenRefreshed.php`
- Create: `src/Events/TokenRevoked.php`
- Create: `src/Events/SessionRevoked.php`
- Create: `src/Events/AllUserTokensRevoked.php`
- Create: `src/Events/RefreshTokenReuseDetected.php`
- Create: `tests/Feature/RefreshRotationTest.php`
- Create: `tests/Feature/RevokeTokensTest.php`

- [ ] **Step 1: Write refresh rotation tests**

Create `tests/Feature/RefreshRotationTest.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Models\JwtRefreshToken;
use Sopheak\JwtAuth\Services\JwtTokenService;
use Sopheak\JwtAuth\Tests\TestCase;

final class RefreshRotationTest extends TestCase
{
    public function test_refresh_token_rotates_and_preserves_context(): void
    {
        $user = $this->createUser();
        $service = app(JwtTokenService::class);
        $first = $service->issueTokenPair($user, TokenContext::make()
            ->subject('tenant', '42')
            ->scopes(['client'])
            ->claims(['tenant_id' => 42]));

        $second = $service->rotateRefreshToken($first->refreshToken);

        self::assertNotSame($first->accessToken, $second->accessToken);
        self::assertNotSame($first->refreshToken, $second->refreshToken);
        self::assertNotNull(JwtRefreshToken::query()->find($first->refreshTokenRecord->id)->revoked_at);
        self::assertSame($second->refreshTokenRecord->id, JwtRefreshToken::query()->find($first->refreshTokenRecord->id)->replaced_by_id);
        self::assertSame(['client'], $second->accessTokenRecord->scopes);
        self::assertSame('tenant', $second->accessTokenRecord->subject_type);
    }

    public function test_reused_refresh_token_revokes_session_by_default(): void
    {
        $user = $this->createUser();
        $service = app(JwtTokenService::class);
        $first = $service->issueTokenPair($user, TokenContext::make()->scopes(['client']));

        $service->rotateRefreshToken($first->refreshToken);

        $this->expectException(\Illuminate\Auth\AuthenticationException::class);
        $service->rotateRefreshToken($first->refreshToken);
    }
}
```

- [ ] **Step 2: Write revocation tests**

Create `tests/Feature/RevokeTokensTest.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Models\JwtAccessToken;
use Sopheak\JwtAuth\Services\JwtTokenService;
use Sopheak\JwtAuth\Tests\TestCase;

final class RevokeTokensTest extends TestCase
{
    public function test_revoke_access_token_rejects_validation(): void
    {
        $user = $this->createUser();
        $service = app(JwtTokenService::class);
        $pair = $service->issueTokenPair($user, TokenContext::make());

        $service->revokeAccessToken($pair->accessTokenRecord->id);

        $this->expectException(\Illuminate\Auth\AuthenticationException::class);
        $service->validateAccessToken($pair->accessToken);
    }

    public function test_revoke_session_revokes_all_tokens_for_session(): void
    {
        $user = $this->createUser();
        $service = app(JwtTokenService::class);
        $pair = $service->issueTokenPair($user, TokenContext::make());

        $service->revokeSession($pair->accessTokenRecord->session_id);

        self::assertSame(0, JwtAccessToken::query()->whereNull('revoked_at')->count());
    }
}
```

- [ ] **Step 3: Run tests and confirm failure**

Run:

```bash
composer test -- --filter "RefreshRotationTest|RevokeTokensTest"
```

Expected: FAIL because rotation and revocation methods are missing.

- [ ] **Step 4: Implement rotation and revocation**

Add these methods to `JwtTokenService`:

```php
rotateRefreshToken(string $refreshToken, ?TokenContext $override = null): TokenPair;
revokeAccessToken(string $jti): void;
revokeSession(string $sessionId): void;
revokeAllForUser(Authenticatable $user): void;
```

Implementation requirements:

- Parse refresh tokens as exactly two non-empty parts: `id.secret`.
- Use `DB::transaction()` and `lockForUpdate()` on the refresh token row.
- If row is missing, expired, revoked, or the secret hash does not match, throw `AuthenticationException`.
- If row is revoked, emit `RefreshTokenReuseDetected` and apply `reuse_detection`:
  - `reject_only`: throw only.
  - `revoke_session`: revoke access and refresh rows with the same `session_id`.
  - `revoke_user`: revoke all access and refresh rows for `user_type` and `user_id`.
- On valid refresh, revoke the old refresh token and linked access token.
- Issue the next pair with existing scopes, claims, subject, and session id unless an override is supplied.
- Set old `replaced_by_id` to the new refresh token id.
- Emit `TokenRefreshed`, `TokenRevoked`, `SessionRevoked`, and `AllUserTokensRevoked` from the matching methods.

- [ ] **Step 5: Run refresh and revocation tests**

Run:

```bash
composer test -- --filter "RefreshRotationTest|RevokeTokensTest"
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Services src/Events tests/Feature/RefreshRotationTest.php tests/Feature/RevokeTokensTest.php
git commit -m "feat: rotate and revoke jwt refresh tokens"
```

---

### Task 8: Laravel Guard, User Token Helpers, and Scope Middleware

**Files:**
- Create: `src/Guards/JwtGuard.php`
- Create: `src/Http/Middleware/RequireJwtScope.php`
- Create: `src/Http/Middleware/RequireAnyJwtScope.php`
- Modify: `src/Traits/HasJwtTokens.php`
- Create: `tests/Feature/GuardTest.php`
- Create: `tests/Feature/ScopeMiddlewareTest.php`

- [ ] **Step 1: Write guard tests**

Create `tests/Feature/GuardTest.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Services\JwtTokenService;
use Sopheak\JwtAuth\Tests\TestCase;

final class GuardTest extends TestCase
{
    public function test_auth_api_authenticates_valid_bearer_token(): void
    {
        Route::middleware('auth:api')->get('/guard-user', fn () => [
            'id' => auth('api')->id(),
            'can_client' => auth('api')->user()->tokenCan('client'),
        ]);

        $user = $this->createUser();
        $pair = app(JwtTokenService::class)->issueTokenPair($user, TokenContext::make()->scopes(['client']));

        $this->withToken($pair->accessToken)
            ->getJson('/guard-user')
            ->assertOk()
            ->assertJson(['id' => $user->getAuthIdentifier(), 'can_client' => true]);
    }
}
```

- [ ] **Step 2: Write scope middleware tests**

Create `tests/Feature/ScopeMiddlewareTest.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Services\JwtTokenService;
use Sopheak\JwtAuth\Tests\TestCase;

final class ScopeMiddlewareTest extends TestCase
{
    public function test_required_scope_allows_matching_scope(): void
    {
        Route::middleware(['auth:api', 'sp.jwt.scope:invoices.read'])
            ->get('/scoped', fn () => ['ok' => true]);

        $pair = app(JwtTokenService::class)->issueTokenPair(
            $this->createUser(),
            TokenContext::make()->scopes(['invoices.read']),
        );

        $this->withToken($pair->accessToken)->getJson('/scoped')->assertOk();
    }

    public function test_required_scope_rejects_missing_scope(): void
    {
        Route::middleware(['auth:api', 'sp.jwt.scope:invoices.write'])
            ->get('/scope-denied', fn () => ['ok' => true]);

        $pair = app(JwtTokenService::class)->issueTokenPair(
            $this->createUser(),
            TokenContext::make()->scopes(['invoices.read']),
        );

        $this->withToken($pair->accessToken)->getJson('/scope-denied')->assertForbidden();
    }
}
```

- [ ] **Step 3: Run guard and middleware tests and confirm failure**

Run:

```bash
composer test -- --filter "GuardTest|ScopeMiddlewareTest"
```

Expected: FAIL because guard and middleware are missing.

- [ ] **Step 4: Implement `JwtGuard`**

Create `src/Guards/JwtGuard.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Guards;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Sopheak\JwtAuth\Services\JwtTokenService;

final class JwtGuard implements Guard
{
    private ?Authenticatable $user = null;

    public function __construct(
        private readonly JwtTokenService $tokens,
        private readonly ?UserProvider $provider,
        private Request $request,
    ) {
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return ! $this->check();
    }

    public function user(): ?Authenticatable
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $bearer = $this->request->bearerToken();
        if ($bearer === null || $bearer === '') {
            return null;
        }

        try {
            $token = $this->tokens->validateAccessToken($bearer);
        } catch (\Throwable) {
            return null;
        }

        $user = $this->provider?->retrieveById($token->user_id);
        if ($user === null) {
            return null;
        }

        if (method_exists($user, 'withAccessToken')) {
            $user->withAccessToken($token);
        }

        $token->forceFill(['last_used_at' => now()])->save();

        return $this->user = $user;
    }

    public function id(): mixed
    {
        return $this->user()?->getAuthIdentifier();
    }

    public function validate(array $credentials = []): bool
    {
        return false;
    }

    public function hasUser(): bool
    {
        return $this->user !== null;
    }

    public function setUser(Authenticatable $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function setRequest(Request $request): void
    {
        $this->request = $request;
        $this->user = null;
    }
}
```

- [ ] **Step 5: Implement scope middleware**

Create `src/Http/Middleware/RequireJwtScope.php` and `RequireAnyJwtScope.php` so both read `$request->user()?->tokenCan($scope)` and return `abort(403)` when no required scope matches. `RequireJwtScope` must require all provided scopes. `RequireAnyJwtScope` must require at least one.

- [ ] **Step 6: Run guard and middleware tests**

Run:

```bash
composer test -- --filter "GuardTest|ScopeMiddlewareTest"
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add src/Guards src/Http tests/Feature/GuardTest.php tests/Feature/ScopeMiddlewareTest.php
git commit -m "feat: add sp jwt guard and scope middleware"
```

---

### Task 9: Response Helper and Core Commands

**Files:**
- Create: `src/Support/TokenResponse.php`
- Create: `src/Console/InstallCommand.php`
- Create: `src/Console/KeysCommand.php`
- Create: `src/Console/JwksCommand.php`
- Create: `src/Console/PruneCommand.php`
- Create: `tests/Feature/CommandTest.php`

- [ ] **Step 1: Write command tests**

Create `tests/Feature/CommandTest.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Models\JwtAccessToken;
use Sopheak\JwtAuth\Services\JwtTokenService;
use Sopheak\JwtAuth\Support\TokenResponse;
use Sopheak\JwtAuth\Tests\TestCase;

final class CommandTest extends TestCase
{
    public function test_passport_compatible_token_response(): void
    {
        $pair = app(JwtTokenService::class)->issueTokenPair($this->createUser(), TokenContext::make());

        $response = TokenResponse::passportCompatible($pair, ['company_id' => 1]);

        self::assertSame('Bearer', $response['token_type']);
        self::assertArrayHasKey('access_token', $response);
        self::assertArrayHasKey('refresh_token', $response);
        self::assertSame(1, $response['company_id']);
    }

    public function test_prune_command_deletes_old_expired_tokens(): void
    {
        $pair = app(JwtTokenService::class)->issueTokenPair($this->createUser(), TokenContext::make());
        $pair->accessTokenRecord->forceFill(['expires_at' => now()->subDays(40)])->save();

        $this->artisan('sp-jwt-auth:prune', ['--expired-days' => 30])
            ->assertExitCode(0);

        self::assertSame(0, JwtAccessToken::query()->count());
    }
}
```

- [ ] **Step 2: Create response helper**

Create `src/Support/TokenResponse.php`:

```php
<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Support;

use Sopheak\JwtAuth\DTO\TokenPair;

final class TokenResponse
{
    public static function passportCompatible(TokenPair $pair, array $extra = []): array
    {
        return array_merge([
            'token_type' => 'Bearer',
            'expires_in' => $pair->expiresIn(),
            'access_token' => $pair->accessToken,
            'refresh_token' => $pair->refreshToken,
        ], $extra);
    }
}
```

- [ ] **Step 3: Implement commands**

Create command classes with these signatures:

```php
protected $signature = 'sp-jwt-auth:install {--keys : Generate local key files after publishing config and migrations}';
protected $signature = 'sp-jwt-auth:keys {--generate} {--rotate} {--retire} {--revoke} {--force} {--kid=} {--compromised} {--algorithm=RS256} {--path=storage}';
protected $signature = 'sp-jwt-auth:jwks {--output=} {--pretty} {--active-only}';
protected $signature = 'sp-jwt-auth:prune {--expired-days=30} {--revoked-days=30} {--dry-run}';
```

Behavior requirements:

- `install` publishes `sp-jwt-auth-config` and `sp-jwt-auth-migrations`, then optionally calls `keys --generate`.
- `keys --generate` writes `jwt-private-<kid>.key` and `jwt-public-<kid>.key` under the requested path using `openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA])`.
- `keys --rotate` generates a new key pair and prints the env/config changes the app must apply; it must not expose a private key in logs after the file write.
- `keys --retire` and `keys --revoke --compromised` print exact config changes for `previous_kids` and `compromised_kids`.
- `jwks` prints `JwksFormatter` output or writes to `--output`.
- `prune` deletes expired access tokens older than `expires_at + expired-days`, revoked access tokens older than `revoked_at + revoked-days`, expired refresh tokens older than `expires_at + expired-days`, and revoked refresh tokens older than `revoked_at + revoked-days`.

- [ ] **Step 4: Run command tests**

Run:

```bash
composer test -- --filter CommandTest
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add src/Support/TokenResponse.php src/Console tests/Feature/CommandTest.php
git commit -m "feat: add token response helper and core commands"
```

---

### Task 10: Package Documentation Cleanup

**Files:**
- Modify: `README.md`
- Modify: `CHANGELOG.md`
- Modify: `docs/ai/project-context.md`
- Modify: `docs/ai/commands.md`
- Modify: `docs/ai/architecture.md`
- Delete: `docs/guide/**`

- [ ] **Step 1: Replace README**

Rewrite `README.md` with these sections:

```markdown
# SP JWT Auth

`sopheak/sp-jwt-auth` is a Laravel package for first-party API authentication with JWT access tokens and opaque rotating refresh tokens.

## Install

```bash
composer require sopheak/sp-jwt-auth
php artisan sp-jwt-auth:install --keys
php artisan migrate
```

## Configure Guard

```php
'guards' => [
    'api' => [
        'driver' => 'sp-jwt',
        'provider' => 'users',
    ],
],
```

## Issue Tokens

```php
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Services\JwtTokenService;
use Sopheak\JwtAuth\Support\TokenResponse;

$pair = app(JwtTokenService::class)->issueTokenPair(
    $user,
    TokenContext::make()
        ->subject('tenant', '42')
        ->scopes(['client', 'tenant:42'])
        ->claims(['tenant_id' => 42]),
);

return TokenResponse::passportCompatible($pair);
```

## Refresh Tokens

```php
$pair = app(JwtTokenService::class)->rotateRefreshToken($request->input('refresh_token'));
```

## Revoke Tokens

```php
app(JwtTokenService::class)->revokeAccessToken($request->user()->token()->id);
app(JwtTokenService::class)->revokeSession($request->user()->token()->session_id);
app(JwtTokenService::class)->revokeAllForUser($request->user());
```

## Scopes

```php
Route::middleware(['auth:api', 'sp.jwt.scope:invoices.read'])->get('/invoices', Controller::class);
```

## Optional Modules

Account security, API keys, external identity, and OAuth2 server support are separate optional modules and are disabled by default.
```

- [ ] **Step 2: Replace AI docs**

Update `docs/ai/project-context.md`, `docs/ai/commands.md`, and `docs/ai/architecture.md` so all package names, namespaces, commands, and goals describe `sp-jwt-auth`. Include these commands:

```bash
composer install
composer test
composer analyse
composer format-check
composer quality
```

- [ ] **Step 3: Remove copied guide docs**

Delete `docs/guide/**` because the files document dynamic CRUD, MCP, permissions, attachments, and pagination from `sp-jwt-auth`. Keep `docs/superpowers/specs/**` and `docs/superpowers/plans/**`.

- [ ] **Step 4: Search for stale identifiers**

Run:

```bash
rg -n -F "sp-jwt-auth" .
rg -n -F "Sopheak\\Core" .
rg -n -F "CoreSpLaravelApiProvider" .
```

Expected: The only `sp-jwt-auth` hits are in the technical spec where it states this package has no dependency on it.

- [ ] **Step 5: Commit**

```bash
git add README.md CHANGELOG.md docs
git commit -m "docs: replace copied api package documentation"
```

---

### Task 11: Core Security and Integration Coverage

**Files:**
- Modify: `tests/Feature/TokenIssueValidateTest.php`
- Modify: `tests/Feature/JwksTest.php`
- Modify: `tests/Feature/RefreshRotationTest.php`
- Modify: `tests/Feature/GuardTest.php`

- [ ] **Step 1: Add validation rejection cases**

Extend `TokenIssueValidateTest` with tests for:

```php
public function test_validate_rejects_revoked_unknown_and_expired_access_tokens(): void
public function test_validate_rejects_wrong_audience(): void
public function test_validate_rejects_reserved_custom_claims(): void
```

Each test should call `JwtTokenService::validateAccessToken()` and expect `Illuminate\Auth\AuthenticationException`.

- [ ] **Step 2: Add key-state tests**

Extend `JwksTest` with tests for:

```php
public function test_previous_key_can_verify_but_does_not_sign_new_tokens(): void
public function test_compromised_key_is_rejected_immediately(): void
public function test_jwks_does_not_expose_compromised_keys(): void
```

Configure key states in the test with `$this->app['config']->set('sp-jwt-auth.keys.items.<kid>.state', '<state>')`.

- [ ] **Step 3: Add refresh hardening tests**

Extend `RefreshRotationTest` with:

```php
public function test_refresh_rejects_malformed_token(): void
public function test_refresh_rejects_wrong_secret(): void
public function test_refresh_override_changes_context_only_when_supplied(): void
```

Assert old token rows are revoked only for the valid rotation path.

- [ ] **Step 4: Add web guard compatibility test**

Extend `GuardTest` with:

```php
public function test_package_does_not_replace_web_guard(): void
{
    config()->set('auth.guards.web', ['driver' => 'session', 'provider' => 'users']);

    self::assertNotSame('sp-jwt', config('auth.guards.web.driver'));
}
```

- [ ] **Step 5: Run targeted suite**

Run:

```bash
composer test -- --filter "TokenIssueValidateTest|JwksTest|RefreshRotationTest|GuardTest"
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add tests src
git commit -m "test: cover core jwt security behavior"
```

---

### Task 12: Static Analysis, Formatting, and Final Verification

**Files:**
- Modify only files needed to satisfy the commands.

- [ ] **Step 1: Run formatter check**

Run:

```bash
composer format-check
```

Expected: PASS. If it fails, run `composer format`, inspect the diff, then run `composer format-check` again.

- [ ] **Step 2: Run static analysis**

Run:

```bash
composer analyse
```

Expected: PASS with no PHPStan/Larastan errors.

- [ ] **Step 3: Run full tests**

Run:

```bash
composer test
```

Expected: PASS.

- [ ] **Step 4: Run stale dependency and namespace checks**

Run:

```bash
composer validate --strict
rg -n -F "sopheak/sp-jwt-auth" composer.json src config database tests README.md docs/ai
rg -n -F "Sopheak\\Core" composer.json src config database tests README.md docs/ai
```

Expected: `composer validate --strict` passes. Both `rg` commands return no matches in implementation files and docs outside the preserved technical spec.

- [ ] **Step 5: Final commit**

```bash
git add .
git commit -m "feat: implement core sp jwt auth"
```

---

## Self-Review Checklist

- Core JWT install works without optional modules.
- `auth.guards.api.driver = sp-jwt` authenticates bearer JWTs.
- `web` guard remains session-based and untouched.
- Access token validation checks algorithm, signature, `kid`, `iss`, configured `aud`, expiry, persisted `jti`, DB expiry, and revocation.
- Refresh token secrets are never stored plaintext.
- Refresh rotation runs in a transaction, revokes the previous access/refresh pair, sets `replaced_by_id`, and detects reuse.
- `HasJwtTokens` provides `$user->token()` and `$user->tokenCan($scope)`.
- Scope middleware rejects missing scopes with 403.
- JWKS exposes public active/previous keys only.
- Commands exist for install, keys, JWKS, and pruning.
- Docs no longer describe `sp-jwt-auth` as this package.
- Composer has no dependency on `sopheak/sp-jwt-auth`.
- Full suite passes with `composer quality`.
