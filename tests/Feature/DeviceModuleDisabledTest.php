<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Sopheak\JwtAuth\Tests\TestCase;

final class DeviceModuleDisabledTest extends TestCase
{
    public function test_disabled_module_registers_no_routes_or_middleware_aliases(): void
    {
        $this->postJson('/auth/devices', ['platform' => 'web'])->assertNotFound();
        self::assertArrayNotHasKey('sp.jwt.device', Route::getMiddleware());
        self::assertArrayNotHasKey('sp.jwt.any', Route::getMiddleware());
        self::assertArrayHasKey('sp.jwt', Route::getMiddleware());
    }
}
