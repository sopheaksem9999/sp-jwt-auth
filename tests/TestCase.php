<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests;

use Sopheak\JwtAuth\Tests\Fixtures\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Orchestra\Testbench\TestCase as Orchestra;
use Sopheak\JwtAuth\CoreSpJwtAuthServiceProvider;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [CoreSpJwtAuthServiceProvider::class];
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
            'model' => User::class,
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

    protected function createUser(array $attributes = []): User
    {
        return User::query()->create(array_merge([
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
