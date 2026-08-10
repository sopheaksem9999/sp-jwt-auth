<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Console;

use Illuminate\Console\Command;
use JsonException;

final class BoostCommand extends Command
{
    protected $signature = 'sp-jwt-auth:boost
        {--force : Overwrite existing guidelines and skills files}';

    protected $description = 'Wire sp-jwt-auth into the client app for Laravel Boost agents.';

    public function handle(): int
    {
        $this->installGuidelines();
        $this->installSkill();
        $this->registerBoostSkill();
        $this->registerMcpServer();

        $this->info('Run php artisan boost:install to regenerate agent guidelines for this app.');

        return self::SUCCESS;
    }

    private function installGuidelines(): void
    {
        $this->copyPackageFile(
            __DIR__ . '/../../guidelines/sp-jwt-auth.md',
            base_path('guidelines/sp-jwt-auth.md'),
            'guidelines/sp-jwt-auth.md',
        );
    }

    private function installSkill(): void
    {
        $this->copyPackageFile(
            __DIR__ . '/../../skills/sp-jwt-auth/SKILL.md',
            base_path('.agents/skills/sp-jwt-auth/SKILL.md'),
            '.agents/skills/sp-jwt-auth/SKILL.md',
        );
    }

    private function copyPackageFile(string $source, string $target, string $label): void
    {
        if (file_exists($target) && ! $this->option('force')) {
            $this->line(sprintf('Skipped %s (already exists; use --force to overwrite).', $label));

            return;
        }

        $contents = file_get_contents($source);

        if ($contents === false) {
            $this->warn(sprintf('Unable to read packaged %s.', $label));

            return;
        }

        if (! is_dir(dirname($target))) {
            mkdir(dirname($target), 0777, true);
        }

        file_put_contents($target, $contents);
        $this->info(sprintf('Installed %s.', $label));
    }

    private function registerBoostSkill(): void
    {
        $path = base_path('boost.json');

        if (! file_exists($path)) {
            $this->line('Skipped boost.json (not found; create one to enable the sp-jwt-auth skill).');

            return;
        }

        $boost = $this->readJson($path);

        if ($boost === null) {
            $this->warn('Skipped boost.json (invalid JSON).');

            return;
        }

        $skills = $boost['skills'] ?? [];

        if (! is_array($skills)) {
            $this->warn('Skipped boost.json (skills must be an array).');

            return;
        }

        if (in_array('sp-jwt-auth', $skills, true)) {
            $this->line('boost.json already registers the sp-jwt-auth skill.');

            return;
        }

        $skills[] = 'sp-jwt-auth';
        $boost['skills'] = array_values($skills);

        $this->writeJson($path, $boost);
        $this->info('Registered the sp-jwt-auth skill in boost.json.');
    }

    private function registerMcpServer(): void
    {
        $path = base_path('.mcp.json');
        $server = [
            'type' => 'stdio',
            'command' => 'php',
            'args' => ['artisan', 'sp-jwt-auth:mcp'],
        ];

        if (! file_exists($path)) {
            $this->writeJson($path, ['mcpServers' => ['sp-jwt-auth' => $server]]);
            $this->info('Created .mcp.json with the sp-jwt-auth MCP server.');

            return;
        }

        $mcp = $this->readJson($path);

        if ($mcp === null) {
            $this->warn('Skipped .mcp.json (invalid JSON).');

            return;
        }

        $servers = $mcp['mcpServers'] ?? [];

        if (! is_array($servers)) {
            $this->warn('Skipped .mcp.json (mcpServers must be an object).');

            return;
        }

        if (isset($servers['sp-jwt-auth'])) {
            $this->line('.mcp.json already registers the sp-jwt-auth MCP server.');

            return;
        }

        $servers['sp-jwt-auth'] = $server;
        $mcp['mcpServers'] = $servers;

        $this->writeJson($path, $mcp);
        $this->info('Registered the sp-jwt-auth MCP server in .mcp.json.');
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
    private function writeJson(string $path, array $data): void
    {
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    }
}
