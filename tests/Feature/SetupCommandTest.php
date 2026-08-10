<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Sopheak\JwtAuth\Tests\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

final class SetupCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->removePublishedScaffolding();
    }

    protected function tearDown(): void
    {
        $this->removePublishedScaffolding();

        parent::tearDown();
    }

    private function removePublishedScaffolding(): void
    {
        @unlink(config_path('sp-jwt-auth.php'));

        $published = glob(database_path('migrations/2026_06_11_*.php')) ?: [];

        foreach ($published as $file) {
            @unlink($file);
        }
    }

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

    public function test_setup_command_publishes_config_and_migrations_by_default(): void
    {
        $this->artisan('sp-jwt-auth:setup', ['--skip-auth-guard' => true])
            ->assertExitCode(0);

        $this->assertFileExists(config_path('sp-jwt-auth.php'));
        $this->assertFileExists(database_path('migrations/2026_06_11_000001_create_sp_jwt_access_tokens_table.php'));
    }

    public function test_setup_command_with_skip_migrations_does_not_publish_migrations(): void
    {
        $this->artisan('sp-jwt-auth:setup', [
            '--skip-auth-guard' => true,
            '--skip-migrations' => true,
        ])->assertExitCode(0);

        $this->assertFileExists(config_path('sp-jwt-auth.php'));
        $this->assertFileDoesNotExist(database_path('migrations/2026_06_11_000001_create_sp_jwt_access_tokens_table.php'));
    }

    public function test_validate_command_json_output_reports_valid_setup(): void
    {
        $output = new BufferedOutput();
        $exitCode = $this->app->make(Kernel::class)->call('sp-jwt-auth:validate', ['--json' => true], $output);

        $report = json_decode($output->fetch(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame('ok', $report['status']);
        $this->assertSame([], $report['errors']);
        $this->assertSame([], $report['warnings']);
    }

    public function test_validate_command_json_output_reports_errors_when_invalid(): void
    {
        config()->set('auth.guards.api.driver', 'token');

        $output = new BufferedOutput();
        $exitCode = $this->app->make(Kernel::class)->call('sp-jwt-auth:validate', ['--json' => true], $output);

        $report = json_decode($output->fetch(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $exitCode);
        $this->assertSame('error', $report['status']);
        $this->assertContains('auth.guards.api.driver must be [sp-jwt].', $report['errors']);
    }
}
