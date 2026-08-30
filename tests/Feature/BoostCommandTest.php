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

    public function test_boost_command_creates_mcp_json_when_missing(): void
    {
        $this->artisan('sp-jwt-auth:boost')->assertExitCode(0);

        $mcp = json_decode((string) file_get_contents(base_path('.mcp.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('sp-jwt-auth:mcp', $mcp['mcpServers']['sp-jwt-auth']['args'][1]);
    }

    public function test_boost_command_merges_mcp_json_preserving_existing_servers(): void
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

    public function test_boost_command_is_idempotent(): void
    {
        $this->artisan('sp-jwt-auth:boost')->assertExitCode(0);
        $this->artisan('sp-jwt-auth:boost')->assertExitCode(0);

        $mcp = json_decode((string) file_get_contents(base_path('.mcp.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('sp-jwt-auth:mcp', $mcp['mcpServers']['sp-jwt-auth']['args'][1]);
    }

    public function test_boost_command_does_not_install_skill_or_rules(): void
    {
        $this->artisan('sp-jwt-auth:boost')->assertExitCode(0);

        $this->assertFileDoesNotExist(base_path('.agents/skills/sp-jwt-auth/SKILL.md'));
        $this->assertFileDoesNotExist(base_path('.agents/rules/sp-jwt-auth.md'));
    }
}