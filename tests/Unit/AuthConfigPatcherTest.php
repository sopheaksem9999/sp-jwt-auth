<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sopheak\JwtAuth\Support\AuthConfigPatcher;

final class AuthConfigPatcherTest extends TestCase
{
    public function test_it_adds_api_guard_to_default_auth_config(): void
    {
        $config = <<<'PHP'
<?php

return [
    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],
];
PHP;

        $patched = AuthConfigPatcher::patch($config);

        self::assertIsString($patched);
        self::assertStringContainsString("'api' => [", $patched);
        self::assertStringContainsString("'driver' => 'sp-jwt'", $patched);
        self::assertStringContainsString("'provider' => env('SP_JWT_USER_PROVIDER', 'users')", $patched);
    }

    public function test_it_returns_null_when_api_guard_already_exists(): void
    {
        $config = <<<'PHP'
<?php

return [
    'guards' => [
        'api' => [
            'driver' => 'sp-jwt',
            'provider' => 'users',
        ],
    ],
];
PHP;

        self::assertNull(AuthConfigPatcher::patch($config));
    }
}
