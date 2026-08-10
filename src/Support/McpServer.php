<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Support;

use OutOfBoundsException;
use Composer\InstalledVersions;
use Sopheak\JwtAuth\Signing\JwksFormatter;
use Sopheak\JwtAuth\Signing\SigningKeyRepository;

final readonly class McpServer
{
    private const string PROTOCOL_VERSION = '2024-11-05';

    public function __construct(
        private SigningKeyRepository $keys,
        private JwksFormatter $formatter,
        private SetupValidator $validator,
    ) {
    }

    /**
     * Handle one JSON-RPC message and return the response (null for notifications).
     *
     * @param array<string, mixed> $message
     *
     * @return array<string, mixed>|null
     */
    public function handle(array $message): ?array
    {
        $id = $message['id'] ?? null;
        $method = $message['method'] ?? null;

        if (! is_string($method)) {
            return $this->error($id, -32600, 'Invalid request: missing method.');
        }

        if ($method === 'notifications/initialized') {
            return null;
        }

        return match ($method) {
            'initialize' => $this->response($id, [
                'protocolVersion' => self::PROTOCOL_VERSION,
                'capabilities' => ['tools' => ['listChanged' => false]],
                'serverInfo' => ['name' => 'sp-jwt-auth', 'version' => $this->version()],
            ]),
            'ping' => $this->response($id, []),
            'tools/list' => $this->response($id, ['tools' => $this->tools()]),
            'tools/call' => $this->callTool($id, $message['params'] ?? []),
            default => $this->error($id, -32601, 'Method not found.'),
        };
    }

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    private function callTool(mixed $id, array $params): array
    {
        $name = $params['name'] ?? null;
        $arguments = $params['arguments'] ?? [];

        if (! is_string($name)) {
            return $this->error($id, -32602, 'Invalid params: missing tool name.');
        }

        if (! is_array($arguments)) {
            return $this->error($id, -32602, 'Invalid params: arguments must be an object.');
        }

        return match ($name) {
            'validate' => $this->toolResult($id, json_encode($this->validator->report(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), $this->validator->report()['status'] === 'error'),
            'jwks' => $this->toolResult($id, json_encode($this->formatter->format($this->keys->publicKeys()), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)),
            'config' => $this->toolResult($id, json_encode($this->safeConfig(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)),
            default => $this->toolResult($id, sprintf('Unknown tool [%s].', $name), isError: true),
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tools(): array
    {
        return [
            [
                'name' => 'validate',
                'description' => 'Validate the client sp-jwt-auth setup. Returns {"status":"ok"|"error","errors":[...],"warnings":[...]}.',
                'inputSchema' => ['type' => 'object', 'properties' => [], 'additionalProperties' => false],
            ],
            [
                'name' => 'jwks',
                'description' => 'Return the public JWKS document for token verification.',
                'inputSchema' => ['type' => 'object', 'properties' => [], 'additionalProperties' => false],
            ],
            [
                'name' => 'config',
                'description' => 'Return safe (non-secret) sp-jwt-auth configuration values.',
                'inputSchema' => ['type' => 'object', 'properties' => [], 'additionalProperties' => false],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function safeConfig(): array
    {
        return [
            'guard' => config('sp-jwt-auth.guard'),
            'driver' => config('sp-jwt-auth.driver'),
            'user_provider' => config('sp-jwt-auth.user_provider'),
            'issuer' => config('sp-jwt-auth.issuer'),
            'audience' => config('sp-jwt-auth.audience'),
            'algorithm' => config('sp-jwt-auth.algorithm'),
            'access_ttl_minutes' => config('sp-jwt-auth.access_ttl_minutes'),
            'refresh_ttl_days' => config('sp-jwt-auth.refresh_ttl_days'),
            'active_kid' => config('sp-jwt-auth.keys.active_kid'),
            'jwks_enabled' => config('sp-jwt-auth.keys.jwks_enabled'),
            'jwks_route' => config('sp-jwt-auth.keys.jwks_route'),
            'mfa_enabled' => config('sp-jwt-auth.mfa.enabled'),
            'otp_enabled' => config('sp-jwt-auth.otp.enabled'),
            'email_verification_enabled' => config('sp-jwt-auth.email_verification.enabled'),
        ];
    }

    /**
     * @param array<string, mixed> $result
     *
     * @return array<string, mixed>
     */
    private function response(mixed $id, array $result): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    /**
     * @return array<string, mixed>
     */
    private function toolResult(mixed $id, string $text, bool $isError = false): array
    {
        return $this->response($id, [
            'content' => [['type' => 'text', 'text' => $text]],
            'isError' => $isError,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function error(mixed $id, int $code, string $message): array
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }

    private function version(): string
    {
        try {
            return InstalledVersions::getPrettyVersion('sopheak/sp-jwt-auth') ?? 'unknown';
        } catch (OutOfBoundsException) {
            return 'unknown';
        }
    }
}
