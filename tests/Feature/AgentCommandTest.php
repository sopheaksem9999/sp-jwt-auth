<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Sopheak\JwtAuth\Tests\TestCase;

final class AgentCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->removeAgentScaffolding();
    }

    protected function tearDown(): void
    {
        $this->removeAgentScaffolding();

        parent::tearDown();
    }

    private function removeAgentScaffolding(): void
    {
        @unlink(base_path('.agents/skills/sp-jwt-auth/SKILL.md'));
        @rmdir(base_path('.agents/skills/sp-jwt-auth'));
        @rmdir(base_path('.agents/skills'));
        @unlink(base_path('.agents/rules/sp-jwt-auth.md'));
        @rmdir(base_path('.agents/rules'));
        @rmdir(base_path('.agents'));
        @unlink(base_path('.mcp.json'));
    }

    public function test_agent_command_copies_skill_and_rules(): void
    {
        $this->artisan('sp-jwt-auth:agent')->assertExitCode(0);

        $this->assertFileExists(base_path('.agents/skills/sp-jwt-auth/SKILL.md'));
        $this->assertFileExists(base_path('.agents/rules/sp-jwt-auth.md'));

        $skill = (string) file_get_contents(base_path('.agents/skills/sp-jwt-auth/SKILL.md'));

        $this->assertStringContainsString('name: sp-jwt-auth', $skill);
    }

    public function test_agent_command_merges_mcp_json_preserving_existing_servers(): void
    {
        file_put_contents(base_path('.mcp.json'), json_encode([
            'mcpServers' => [
                'boost' => ['type' => 'stdio', 'command' => 'php', 'args' => ['artisan', 'boost:mcp']],
            ],
        ], JSON_PRETTY_PRINT));

        $this->artisan('sp-jwt-auth:agent')->assertExitCode(0);

        $mcp = json_decode((string) file_get_contents(base_path('.mcp.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('boost', $mcp['mcpServers']);
        $this->assertSame('stdio', $mcp['mcpServers']['sp-jwt-auth']['type']);
        $this->assertSame(['artisan', 'sp-jwt-auth:mcp'], $mcp['mcpServers']['sp-jwt-auth']['args']);
    }

    public function test_agent_command_creates_mcp_json_when_missing(): void
    {
        $this->artisan('sp-jwt-auth:agent')->assertExitCode(0);

        $mcp = json_decode((string) file_get_contents(base_path('.mcp.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('sp-jwt-auth:mcp', $mcp['mcpServers']['sp-jwt-auth']['args'][1]);
    }

    public function test_agent_command_is_idempotent(): void
    {
        $this->artisan('sp-jwt-auth:agent')->assertExitCode(0);
        $this->artisan('sp-jwt-auth:agent')->assertExitCode(0);

        $this->assertFileExists(base_path('.agents/skills/sp-jwt-auth/SKILL.md'));
        $this->assertFileExists(base_path('.agents/rules/sp-jwt-auth.md'));

        $mcp = json_decode((string) file_get_contents(base_path('.mcp.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('sp-jwt-auth:mcp', $mcp['mcpServers']['sp-jwt-auth']['args'][1]);
    }

    public function test_agent_command_does_not_overwrite_existing_files_without_force(): void
    {
        if (! is_dir(base_path('.agents/rules'))) {
            mkdir(base_path('.agents/rules'), 0777, true);
        }

        file_put_contents(base_path('.agents/rules/sp-jwt-auth.md'), 'custom content');

        $this->artisan('sp-jwt-auth:agent')->assertExitCode(0);

        $this->assertSame('custom content', (string) file_get_contents(base_path('.agents/rules/sp-jwt-auth.md')));
    }

    public function test_agent_command_force_overwrites_existing_files(): void
    {
        if (! is_dir(base_path('.agents/rules'))) {
            mkdir(base_path('.agents/rules'), 0777, true);
        }

        file_put_contents(base_path('.agents/rules/sp-jwt-auth.md'), 'custom content');

        $this->artisan('sp-jwt-auth:agent', ['--force' => true])->assertExitCode(0);

        $this->assertStringNotContainsString('custom content', (string) file_get_contents(base_path('.agents/rules/sp-jwt-auth.md')));
    }

    public function test_agent_command_skill_only_flag(): void
    {
        $this->artisan('sp-jwt-auth:agent', ['--skill' => true])->assertExitCode(0);

        $this->assertFileExists(base_path('.agents/skills/sp-jwt-auth/SKILL.md'));
        $this->assertFileDoesNotExist(base_path('.agents/rules/sp-jwt-auth.md'));
    }

    public function test_agent_command_rules_only_flag(): void
    {
        $this->artisan('sp-jwt-auth:agent', ['--rules' => true])->assertExitCode(0);

        $this->assertFileDoesNotExist(base_path('.agents/skills/sp-jwt-auth/SKILL.md'));
        $this->assertFileExists(base_path('.agents/rules/sp-jwt-auth.md'));
    }
}