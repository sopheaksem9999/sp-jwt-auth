<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Override;
use Sopheak\JwtAuth\DTO\DeviceRegistration;
use Sopheak\JwtAuth\Models\Device;
use Sopheak\JwtAuth\Services\DeviceService;
use Sopheak\JwtAuth\Tests\TestCase;

/** Device keys follow sp-jwt-auth.id_type, because tokens store them in user_id. */
final class DeviceModuleUuidTest extends TestCase
{
    #[Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('sp-jwt-auth.id_type', 'uuid');
        $app['config']->set('sp-jwt-auth.devices.enabled', true);
        $app['config']->set('sp-jwt-auth.token_endpoints.enabled', true);
    }

    public function test_uuid_device_keys_work_for_tokens_refresh_and_middleware(): void
    {
        Route::middleware('sp.jwt.device')->get('/device-uuid', fn (): array => ['id' => request()->attributes->get('sp_device')->getKey()]);

        $result = app(DeviceService::class)->register(new DeviceRegistration(platform: 'web'));

        self::assertTrue(Str::isUuid((string) $result->device->getKey()));
        self::assertNotSame($result->device->getKey(), $result->device->public_id);
        self::assertSame('varchar', Schema::getColumnType('sp_jwt_device_users', 'device_id'));
        $this->withToken($result->tokens->accessToken)->getJson('/device-uuid')->assertOk()->assertJsonPath('id', $result->device->getKey());

        $this->flushHeaders()->postJson('/auth/token/refresh', ['refresh_token' => $result->tokens->refreshToken])->assertOk();
        self::assertSame(1, Device::query()->count());
    }
}
