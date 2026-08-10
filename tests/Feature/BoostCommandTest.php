<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Sopheak\JwtAuth\Tests\TestCase;

final class BoostCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->removeBoostScaffolding();
    }

    protected function tearDown(): void
    {
        $this->removeBoostScaffolding();

        parent::tearDown();
    }

    private function removeBoostScaffolding(): void
    {
        @unlink(base_path('guidelines/sp-jwt-auth.md'));
        @rmdir(base_path('guidelines'));
        @unlink(base_path('.agents/skills/sp-jwt-auth/SKILL.md'));
        @rmdir(base_path('.agents/skills/sp-jwt-auth'));
        @rmdir(base_path('.agents/skills'));
        @rmdir(base_path('.agents'));
        @unlink(base_path('boost.json'));
        @unlink(base_path('.mcp.json'));
    }

    public function test_boost_command_copies_guidelines_and_skill(): void
    {
        $this->artisan('sp-jwt-auth:boost')->assertExitCode(0);

        $this->assertFileExists(base_path('guidelines/sp-jwt-auth.md'));
        $this->assertFileExists(base_path('.agents/skills/sp-jwt-auth/SKILL.md'));

        $skill = (string) file_get_contents(base_path('.agents/skills/sp-jwt-auth/SKILL.md'));

        $this->assertStringContainsString('name: sp-jwt-auth', $skill);
    }

    public function test_boost_command_merges_boost_json_skills(): void
    {
        file_put_contents(base_path('boost.json'), json_encode([
            'skills' => ['fortify-development', 'pest-testing'],
        ], JSON_PRETTY_PRINT));

        $this->artisan('sp-jwt-auth:boost')->assertExitCode(0);

        $boost = json_decode((string) file_get_contents(base_path('boost.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertContains('sp-jwt-auth', $boost['skills']);
        $this->assertContains('fortify-development', $boost['skills']);
        $this->assertContains('pest-testing', $boost['skills']);
    }

    public function test_boost_command_is_idempotent(): void
    {
        file_put_contents(base_path('boost.json'), json_encode([
            'skills' => ['sp-jwt-auth', 'pest-testing'],
        ], JSON_PRETTY_PRINT));

        $this->artisan('sp-jwt-auth:boost')->assertExitCode(0);
        $this->artisan('sp-jwt-auth:boost')->assertExitCode(0);

        $boost = json_decode((string) file_get_contents(base_path('boost.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertCount(1, array_filter($boost['skills'], fn (string $skill): bool => $skill === 'sp-jwt-auth'));
        $this->assertContains('pest-testing', $boost['skills']);
    }

    public function test_boost_command_merges_mcp_json(): void
    {
        file_put_contents(base_path('.mcp.json'), json_encode([
            'mcpServers' => [
                'boost' => ['type' => 'stdio', 'command' => 'php', 'args' => ['artisan', 'boost:mcp']],
            ],
        ], JSON_PRETTY_PRINT));

        $this->artisan('sp-jwt-auth:boost')->assertExitCode(0);

        $mcp = json_decode((string) file_get_contents(base_path('.mcp.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('boost', $mcp['mcpServers']);
        $this->assertSame('stdio', $mcp['mcpServers']['sp-jwt-auth']['type']);
        $this->assertSame(['artisan', 'sp-jwt-auth:mcp'], $mcp['mcpServers']['sp-jwt-auth']['args']);
    }

    public function test_boost_command_creates_mcp_json_when_missing(): void
    {
        $this->artisan('sp-jwt-auth:boost')->assertExitCode(0);

        $mcp = json_decode((string) file_get_contents(base_path('.mcp.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('sp-jwt-auth:mcp', $mcp['mcpServers']['sp-jwt-auth']['args'][1]);
    }

    public function test_boost_command_does_not_overwrite_existing_guidelines_without_force(): void
    {
        if (! is_dir(base_path('guidelines'))) {
            mkdir(base_path('guidelines'));
        }

        file_put_contents(base_path('guidelines/sp-jwt-auth.md'), 'custom content');

        $this->artisan('sp-jwt-auth:boost')->assertExitCode(0);

        $this->assertSame('custom content', (string) file_get_contents(base_path('guidelines/sp-jwt-auth.md')));
    }

    public function test_boost_command_force_overwrites_existing_guidelines(): void
    {
        if (! is_dir(base_path('guidelines'))) {
            mkdir(base_path('guidelines'));
        }

        file_put_contents(base_path('guidelines/sp-jwt-auth.md'), 'custom content');

        $this->artisan('sp-jwt-auth:boost', ['--force' => true])->assertExitCode(0);

        $this->assertStringNotContainsString('custom content', (string) file_get_contents(base_path('guidelines/sp-jwt-auth.md')));
    }
}
