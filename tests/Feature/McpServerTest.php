<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Sopheak\JwtAuth\Support\McpServer;
use Sopheak\JwtAuth\Support\SetupValidator;
use Sopheak\JwtAuth\Signing\JwksFormatter;
use Sopheak\JwtAuth\Signing\SigningKeyRepository;
use Sopheak\JwtAuth\Tests\TestCase;

final class McpServerTest extends TestCase
{
    private function server(): McpServer
    {
        return new McpServer(
            $this->app->make(SigningKeyRepository::class),
            $this->app->make(JwksFormatter::class),
            $this->app->make(SetupValidator::class),
        );
    }

    public function test_initialize_returns_protocol_metadata(): void
    {
        $response = $this->server()->handle([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => ['protocolVersion' => '2024-11-05'],
        ]);

        $this->assertSame(1, $response['id']);
        $this->assertSame('2024-11-05', $response['result']['protocolVersion']);
        $this->assertSame('sp-jwt-auth', $response['result']['serverInfo']['name']);
        $this->assertIsString($response['result']['serverInfo']['version']);
    }

    public function test_initialized_notification_returns_no_response(): void
    {
        $this->assertNull($this->server()->handle([
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
        ]));
    }

    public function test_ping_returns_acknowledgement(): void
    {
        $response = $this->server()->handle([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'ping',
        ]);

        $this->assertSame(2, $response['id']);
        $this->assertSame([], $response['result']);
    }

    public function test_tools_list_returns_expected_tools(): void
    {
        $response = $this->server()->handle([
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/list',
        ]);

        $names = array_column($response['result']['tools'], 'name');

        $this->assertSame(['validate', 'jwks', 'config'], $names);

        foreach ($response['result']['tools'] as $tool) {
            $this->assertArrayHasKey('description', $tool);
            $this->assertArrayHasKey('inputSchema', $tool);
        }
    }

    public function test_tools_call_validate_returns_ok_report_for_valid_setup(): void
    {
        $response = $this->server()->handle([
            'jsonrpc' => '2.0',
            'id' => 4,
            'method' => 'tools/call',
            'params' => ['name' => 'validate', 'arguments' => []],
        ]);

        $report = json_decode($response['result']['content'][0]['text'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('ok', $report['status']);
        $this->assertSame([], $report['errors']);
    }

    public function test_tools_call_validate_returns_error_report_for_invalid_setup(): void
    {
        config()->set('auth.guards.api.driver', 'token');

        $response = $this->server()->handle([
            'jsonrpc' => '2.0',
            'id' => 5,
            'method' => 'tools/call',
            'params' => ['name' => 'validate', 'arguments' => []],
        ]);

        $report = json_decode($response['result']['content'][0]['text'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertTrue($response['result']['isError']);
        $this->assertSame('error', $report['status']);
        $this->assertContains('auth.guards.api.driver must be [sp-jwt].', $report['errors']);
    }

    public function test_tools_call_jwks_returns_public_keys(): void
    {
        $response = $this->server()->handle([
            'jsonrpc' => '2.0',
            'id' => 6,
            'method' => 'tools/call',
            'params' => ['name' => 'jwks', 'arguments' => []],
        ]);

        $jwks = json_decode($response['result']['content'][0]['text'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('keys', $jwks);
        $this->assertSame('test-active', $jwks['keys'][0]['kid']);
    }

    public function test_tools_call_config_never_exposes_secrets(): void
    {
        $response = $this->server()->handle([
            'jsonrpc' => '2.0',
            'id' => 7,
            'method' => 'tools/call',
            'params' => ['name' => 'config', 'arguments' => []],
        ]);

        $text = $response['result']['content'][0]['text'];
        $config = json_decode((string) $text, true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('RS256', $config['algorithm']);
        $this->assertStringNotContainsString('private_key', $text);
        $this->assertStringNotContainsString('hash_keys', $text);
        $this->assertStringNotContainsString('SP_JWT_REFRESH_HASH_KEY', $text);
    }

    public function test_tools_call_unknown_tool_returns_is_error(): void
    {
        $response = $this->server()->handle([
            'jsonrpc' => '2.0',
            'id' => 8,
            'method' => 'tools/call',
            'params' => ['name' => 'drop-database', 'arguments' => []],
        ]);

        $this->assertTrue($response['result']['isError']);
        $this->assertStringContainsString('Unknown tool', $response['result']['content'][0]['text']);
    }

    public function test_unknown_method_returns_json_rpc_error(): void
    {
        $response = $this->server()->handle([
            'jsonrpc' => '2.0',
            'id' => 9,
            'method' => 'resources/list',
        ]);

        $this->assertSame(-32601, $response['error']['code']);
    }
}
