<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Sopheak\JwtAuth\Console\McpCommand;
use Sopheak\JwtAuth\Support\McpServer;
use Sopheak\JwtAuth\Support\SetupValidator;
use Sopheak\JwtAuth\Support\StdioTransport;
use Sopheak\JwtAuth\Signing\JwksFormatter;
use Sopheak\JwtAuth\Signing\SigningKeyRepository;
use Sopheak\JwtAuth\Tests\TestCase;

final class McpCommandTest extends TestCase
{
    public function test_mcp_command_processes_stdio_messages_and_writes_responses(): void
    {
        $input = fopen('php://temp', 'w+');
        $output = fopen('php://temp', 'w+');

        fwrite($input, json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => ['protocolVersion' => '2024-11-05'],
        ]) . "\n");
        fwrite($input, json_encode([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/call',
            'params' => ['name' => 'validate', 'arguments' => []],
        ]) . "\n");
        rewind($input);

        $transport = new StdioTransport($input, $output);

        $exitCode = $this->app->make(McpCommand::class)->handle(
            $transport,
            new McpServer(
                $this->app->make(SigningKeyRepository::class),
                $this->app->make(JwksFormatter::class),
                $this->app->make(SetupValidator::class),
            ),
        );

        rewind($output);
        $lines = explode("\n", trim((string) stream_get_contents($output)));

        $this->assertSame(0, $exitCode);
        $this->assertCount(2, $lines);

        $initialize = json_decode($lines[0], true, 512, JSON_THROW_ON_ERROR);
        $validate = json_decode($lines[1], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(1, $initialize['id']);
        $this->assertSame('sp-jwt-auth', $initialize['result']['serverInfo']['name']);
        $this->assertSame(2, $validate['id']);
        $this->assertSame('ok', json_decode($validate['result']['content'][0]['text'], true)['status']);
    }

    public function test_mcp_command_ignores_malformed_lines(): void
    {
        $input = fopen('php://temp', 'w+');
        $output = fopen('php://temp', 'w+');

        fwrite($input, "not-json\n");
        rewind($input);

        $transport = new StdioTransport($input, $output);

        $exitCode = $this->app->make(McpCommand::class)->handle(
            $transport,
            $this->app->make(McpServer::class),
        );

        rewind($output);

        $this->assertSame(0, $exitCode);
        $this->assertSame('', (string) stream_get_contents($output));
    }
}
