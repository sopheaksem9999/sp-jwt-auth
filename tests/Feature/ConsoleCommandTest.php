<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Sopheak\JwtAuth\Tests\TestCase;

final class ConsoleCommandTest extends TestCase
{
    public function test_install_command_executes_successfully(): void
    {
        $this->artisan('sp-jwt-auth:install')
            ->assertExitCode(0);
    }

    public function test_keys_command_fails_without_action_option(): void
    {
        $this->artisan('sp-jwt-auth:keys')
            ->expectsOutputToContain('Choose --generate, --rotate, --retire, or --revoke.')
            ->assertExitCode(1);
    }

    public function test_keys_command_generates_keys_with_no_write_env(): void
    {
        $relPath = 'storage/framework/testing/test_keys_' . uniqid();
        $targetDir = base_path($relPath);

        $this->artisan('sp-jwt-auth:keys', [
            '--generate' => true,
            '--kid' => 'test-key-1',
            '--path' => $relPath,
            '--no-write-env' => true,
            '--pem' => true,
        ])->assertExitCode(0);

        self::assertFileExists($targetDir . '/jwt-private-test-key-1.pem');
        self::assertFileExists($targetDir . '/jwt-public-test-key-1.pem');

        // Clean up
        @unlink($targetDir . '/jwt-private-test-key-1.pem');
        @unlink($targetDir . '/jwt-public-test-key-1.pem');
        @rmdir($targetDir);
    }

    public function test_keys_command_warns_if_keys_exist_without_force(): void
    {
        $relPath = 'storage/framework/testing/test_keys_' . uniqid();
        $targetDir = base_path($relPath);
        mkdir($targetDir, 0755, true);
        file_put_contents($targetDir . '/jwt-private-existing.key', 'dummy');

        $this->artisan('sp-jwt-auth:keys', [
            '--generate' => true,
            '--kid' => 'existing',
            '--path' => $relPath,
            '--no-write-env' => true,
        ])->expectsOutputToContain('Key files already exist. Use --force to overwrite.')
          ->assertExitCode(1);

        @unlink($targetDir . '/jwt-private-existing.key');
        @rmdir($targetDir);
    }

    public function test_keys_command_retire_and_revoke_options(): void
    {
        $this->artisan('sp-jwt-auth:keys', ['--retire' => true, '--kid' => 'k1'])
            ->expectsOutputToContain('Remove [k1] from active keys')
            ->assertExitCode(0);

        $this->artisan('sp-jwt-auth:keys', ['--revoke' => true, '--kid' => 'k2'])
            ->expectsOutputToContain('Add [k2] to SP_JWT_REVOKED_KIDS immediately.')
            ->assertExitCode(0);

        $this->artisan('sp-jwt-auth:keys', ['--revoke' => true, '--compromised' => true, '--kid' => 'k3'])
            ->expectsOutputToContain('Add [k3] to SP_JWT_COMPROMISED_KIDS immediately.')
            ->assertExitCode(0);
    }

    public function test_jwks_command_outputs_json(): void
    {
        $this->artisan('sp-jwt-auth:jwks')
            ->assertExitCode(0);
    }

    public function test_jwks_command_writes_to_file(): void
    {
        $tmpFile = sys_get_temp_dir() . '/jwks_test_' . uniqid() . '.json';

        $this->artisan('sp-jwt-auth:jwks', [
            '--output' => $tmpFile,
            '--pretty' => true,
            '--active-only' => true,
        ])->assertExitCode(0);

        self::assertFileExists($tmpFile);
        $json = json_decode((string) file_get_contents($tmpFile), true);
        self::assertIsArray($json);
        self::assertArrayHasKey('keys', $json);

        @unlink($tmpFile);
    }

    public function test_validate_command_fails_when_active_kid_is_missing(): void
    {
        config()->set('sp-jwt-auth.keys.active_kid', '');

        $this->artisan('sp-jwt-auth:validate')
            ->expectsOutputToContain('sp-jwt-auth.keys.active_kid must be configured.')
            ->assertExitCode(1);
    }

    public function test_validate_command_fails_when_active_hash_key_is_missing(): void
    {
        config()->set('sp-jwt-auth.hash_keys.active_id', '');

        $this->artisan('sp-jwt-auth:validate')
            ->expectsOutputToContain('sp-jwt-auth.hash_keys.active_id must be configured.')
            ->assertExitCode(1);
    }

    public function test_validate_command_fails_when_hash_key_secret_is_empty(): void
    {
        config()->set('sp-jwt-auth.hash_keys.items.test-hash.key', '');

        $this->artisan('sp-jwt-auth:validate')
            ->expectsOutputToContain('sp-jwt-auth.hash_keys.items.test-hash.key must be a non-empty secret.')
            ->assertExitCode(1);
    }
}
