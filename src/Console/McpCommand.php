<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Console;

use Illuminate\Console\Command;
use Sopheak\JwtAuth\Support\McpServer;
use Sopheak\JwtAuth\Support\StdioTransport;

final class McpCommand extends Command
{
    protected $signature = 'sp-jwt-auth:mcp';

    protected $description = 'Run the sp-jwt-auth Model Context Protocol (stdio) server.';

    public function handle(StdioTransport $transport, McpServer $server): int
    {
        while (($line = $transport->readLine()) !== null) {
            $message = json_decode($line, true);

            if (! is_array($message)) {
                continue;
            }

            $response = $server->handle($message);

            if ($response !== null) {
                $transport->writeLine(json_encode($response, JSON_UNESCAPED_SLASHES));
            }
        }

        return self::SUCCESS;
    }
}
