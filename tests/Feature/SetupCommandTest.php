<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Sopheak\JwtAuth\Tests\TestCase;

final class SetupCommandTest extends TestCase
{
    public function test_validate_command_passes_for_configured_client_app(): void
    {
        $this->artisan('sp-jwt-auth:validate')
            ->expectsOutputToContain('sp-jwt-auth setup looks valid.')
            ->assertExitCode(0);
    }

    public function test_validate_command_fails_when_api_guard_is_not_configured(): void
    {
        config()->set('auth.guards.api.driver', 'token');

        $this->artisan('sp-jwt-auth:validate')
            ->expectsOutputToContain('auth.guards.api.driver must be [sp-jwt]')
            ->assertExitCode(1);
    }

    public function test_validate_command_fails_when_active_key_has_no_key_material(): void
    {
        config()->set('sp-jwt-auth.keys.items.test-active', [
            'state' => 'active',
        ]);

        $this->artisan('sp-jwt-auth:validate')
            ->expectsOutputToContain('sp-jwt-auth.keys.items.test-active must contain signing key material.')
            ->assertExitCode(1);
    }

    public function test_setup_command_is_registered(): void
    {
        $this->artisan('sp-jwt-auth:setup', ['--skip-auth-guard' => true])
            ->expectsOutputToContain('Run php artisan sp-jwt-auth:validate')
            ->assertExitCode(0);
    }
}
