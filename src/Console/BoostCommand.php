<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Console;

use Illuminate\Console\Command;
use JsonException;

final class BoostCommand extends Command
{
    protected $signature = 'sp-jwt-auth:boost';

    protected $description = 'Merge the sp-jwt-auth MCP server into .mcp.json and validate the setup';

    private const array MCP_SERVER_ENTRY = [
        'type' => 'stdio',
        'command' => 'php',
        'args' => ['artisan', 'sp-jwt-auth:mcp'],
    ];

    public function handle(): int
    {
        $this->info('Merging the [sp-jwt-auth] MCP server into .mcp.json...');
        $merged = $this->mergeMcpConfig();

        if (! $merged) {
            $this->error('Could not update .mcp.json (unparseable JSON or write failure).');
            $this->line('Fix .mcp.json manually, then re-run this command.');

            return self::FAILURE;
        }

        $this->info('Running setup validation...');
        $this->newLine();
        $this->call('sp-jwt-auth:validate');
        $this->newLine();

        $this->line('Next steps:');
        $this->line('  • Run `php artisan sp-jwt-auth:agent` to install the agent skill and rules.');
        $this->line('  • If you use Laravel Boost, re-run `php artisan sp-jwt-auth:boost` if Boost regenerates .mcp.json.');

        return self::SUCCESS;
    }

    /**
     * Read-modify-write merge of the sp-jwt-auth entry into .mcp.json,
     * preserving every other server (laravel-boost included). Creates the
     * file when missing. Idempotent: untouched when the entry already matches.
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
            $this->line('  → [sp-jwt-auth] entry already present and up to date.');

            return true;
        }

        $config['mcpServers']['sp-jwt-auth'] = self::MCP_SERVER_ENTRY;

        if (! $this->writeJson($path, $config)) {
            return false;
        }

        $this->line('  → Added the [sp-jwt-auth] MCP server to .mcp.json (existing servers preserved).');

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