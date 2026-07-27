<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Sopheak\JwtAuth\Support\EnvFile;
use Sopheak\JwtAuth\Support\HookRegistry;
use Sopheak\JwtAuth\Support\SpJwtAuth;
use Sopheak\JwtAuth\Tests\TestCase;

final class SupportTest extends TestCase
{
    public function test_env_file_creates_and_updates_keys(): void
    {
        $tmpEnv = sys_get_temp_dir() . '/test_env_' . uniqid() . '.env';

        // 1. Create file and add key
        self::assertTrue(EnvFile::put($tmpEnv, 'TEST_KEY', 'value1'));
        self::assertStringContainsString('TEST_KEY=value1', (string) file_get_contents($tmpEnv));

        // 2. Do not overwrite existing key
        self::assertFalse(EnvFile::put($tmpEnv, 'TEST_KEY', 'value2', overwrite: false));
        self::assertStringContainsString('TEST_KEY=value1', (string) file_get_contents($tmpEnv));

        // 3. Overwrite existing key
        self::assertTrue(EnvFile::put($tmpEnv, 'TEST_KEY', 'value3', overwrite: true));
        self::assertStringContainsString('TEST_KEY=value3', (string) file_get_contents($tmpEnv));

        // 4. Append new key
        self::assertTrue(EnvFile::put($tmpEnv, 'NEW_KEY', 'val'));
        $content = (string) file_get_contents($tmpEnv);
        self::assertStringContainsString('TEST_KEY=value3', $content);
        self::assertStringContainsString('NEW_KEY=val', $content);

        @unlink($tmpEnv);
    }

    public function test_hook_registry_registers_and_retrieves_hooks(): void
    {
        $registry = new HookRegistry();

        $beforeHook = static fn (): string => 'before';
        $validateHook = static fn (): true => true;
        $afterHook = static fn (): string => 'after';

        $registry->beforeTokenIssue($beforeHook)
            ->validateTokenContext($validateHook)
            ->afterTokenIssue($afterHook);

        self::assertCount(1, $registry->beforeTokenIssueHooks());
        self::assertCount(1, $registry->validateTokenContextHooks());
        self::assertCount(1, $registry->afterTokenIssueHooks());

        self::assertSame($beforeHook, $registry->beforeTokenIssueHooks()[0]);
    }

    public function test_sp_jwt_auth_facade_provides_hooks_registry(): void
    {
        $registry = SpJwtAuth::hooks();

        self::assertInstanceOf(HookRegistry::class, $registry);
        self::assertSame($registry, app(HookRegistry::class));
    }
}
