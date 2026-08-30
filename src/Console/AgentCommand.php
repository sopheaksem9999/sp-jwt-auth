<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Console;

use Illuminate\Console\Command;
use JsonException;

final class AgentCommand extends Command
{
    protected $signature = 'sp-jwt-auth:agent
        {--force : Overwrite existing agent files}
        {--skill : Install agent skill only}
        {--rules : Install agent rules/guidelines only}
        {--mcp : Configure MCP entry only}
        {--all : Install skill, rules, and MCP entry (default)}';

    protected $description = 'Set up AI agent skills, rules, and MCP configuration on demand';

    protected $aliases = ['sp-jwt-auth:agent-init'];

    private const array MCP_SERVER_ENTRY = [
        'type' => 'stdio',
        'command' => 'php',
        'args' => ['artisan', 'sp-jwt-auth:mcp'],
    ];

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $onlySkill = (bool) $this->option('skill');
        $onlyRules = (bool) $this->option('rules');
        $onlyMcp = (bool) $this->option('mcp');

        $runAll = (bool) $this->option('all') || (! $onlySkill && ! $onlyRules && ! $onlyMcp);

        $this->info('Configuring sp-jwt-auth agent assets...');
        $this->newLine();

        $success = true;

        if ($runAll || $onlySkill) {
            $this->installSkill($force);
        }

        if ($runAll || $onlyRules) {
            $this->installRules($force);
        }

        if (($runAll || $onlyMcp) && ! $this->mergeMcpConfig()) {
            $this->error('Could not update .mcp.json (unparseable JSON or write failure).');
            $success = false;
        }

        $this->newLine();
        $this->info('Running setup validation...');
        $this->call('sp-jwt-auth:validate');
        $this->newLine();

        $this->line('Agent setup complete.');
        $this->line('  • Skill: `.agents/skills/sp-jwt-auth/SKILL.md`');
        $this->line('  • Rules: `.agents/rules/sp-jwt-auth.md`');
        $this->line('  • MCP server: `sp-jwt-auth` in `.mcp.json`');

        return $success ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Install the sp-jwt-auth development skill into .agents/skills/.
     */
    private function installSkill(bool $force): void
    {
        $source = __DIR__ . '/../../skills/sp-jwt-auth/SKILL.md';
        $destinationDir = base_path('.agents/skills/sp-jwt-auth');
        $destination = $destinationDir . '/SKILL.md';

        if (! is_file($source)) {
            $this->warn(sprintf('  ⚠ Agent skill template not found at [%s].', $source));

            return;
        }

        if (! is_dir($destinationDir)) {
            mkdir($destinationDir, 0777, true);
        }

        if (file_exists($destination) && ! $force) {
            $this->line('  → Agent skill already exists at [.agents/skills/sp-jwt-auth/SKILL.md] (use --force to overwrite).');

            return;
        }

        copy($source, $destination);
        $this->info('  ✓ Installed agent skill to [.agents/skills/sp-jwt-auth/SKILL.md].');
    }

    /**
     * Install sp-jwt-auth guidelines/rules into .agents/rules/.
     */
    private function installRules(bool $force): void
    {
        $source = __DIR__ . '/../../guidelines/sp-jwt-auth.md';
        $destinationDir = base_path('.agents/rules');
        $destination = $destinationDir . '/sp-jwt-auth.md';

        if (! is_file($source)) {
            $this->warn(sprintf('  ⚠ Agent guidelines template not found at [%s].', $source));

            return;
        }

        if (! is_dir($destinationDir)) {
            mkdir($destinationDir, 0777, true);
        }

        if (file_exists($destination) && ! $force) {
            $this->line('  → Agent rules already exist at [.agents/rules/sp-jwt-auth.md] (use --force to overwrite).');

            return;
        }

        copy($source, $destination);
        $this->info('  ✓ Installed agent rules to [.agents/rules/sp-jwt-auth.md].');
    }

    /**
     * Read-modify-write merge of the sp-jwt-auth entry into .mcp.json.
     */
    private function mergeMcpConfig(): bool
    {
        $path = base_path('.mcp.json');

        $config = ['mcpServers' => []];

        if (file_exists($path)) {
            $decoded = $this->readJson($path);

            if ($decoded === null) {
                return false;
            }

            $config = $decoded;

            if (! isset($config['mcpServers']) || ! is_array($config['mcpServers'])) {
                $config['mcpServers'] = [];
            }
        }

        $existing = $config['mcpServers']['sp-jwt-auth'] ?? null;

        if ($existing === self::MCP_SERVER_ENTRY) {
            $this->line('  → [sp-jwt-auth] entry in .mcp.json already up to date.');

            return true;
        }

        $config['mcpServers']['sp-jwt-auth'] = self::MCP_SERVER_ENTRY;

        if (! $this->writeJson($path, $config)) {
            return false;
        }

        $this->info('  ✓ Added [sp-jwt-auth] MCP server entry to .mcp.json.');

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readJson(string $path): ?array
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeJson(string $path, array $data): bool
    {
        try {
            $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        return file_put_contents($path, $encoded . PHP_EOL) !== false;
    }
}