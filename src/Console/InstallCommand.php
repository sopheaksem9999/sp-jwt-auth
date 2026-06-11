<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Console;

use Illuminate\Console\Command;

final class InstallCommand extends Command
{
    protected $signature = 'sp-jwt-auth:install {--keys : Generate local key files after publishing config and migrations}';

    protected $description = 'Publish sp-jwt-auth configuration and migrations.';

    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'sp-jwt-auth-config']);
        $this->call('vendor:publish', ['--tag' => 'sp-jwt-auth-migrations']);

        if ($this->option('keys')) {
            $this->call('sp-jwt-auth:keys', ['--generate' => true]);
        }

        return self::SUCCESS;
    }
}
