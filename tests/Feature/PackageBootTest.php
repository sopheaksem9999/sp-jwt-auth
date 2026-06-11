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
