<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Console;

use Illuminate\Console\Command;
use Sopheak\JwtAuth\Support\AuthConfigPatcher;
use Sopheak\JwtAuth\Support\EnvFile;

final class SetupCommand extends Command
{
    protected $signature = 'sp-jwt-auth:setup
        {--keys : Generate local PEM key files after publishing config and migrations}
        {--force : Overwrite published files and generated key files}
        {--skip-auth-guard : Do not attempt to add the sp-jwt API guard to config/auth.php}';

    protected $description = 'Publish sp-jwt-auth client scaffolding and optionally configure the Laravel API guard.';

    public function handle(): int
    {
        $publishOptions = ['--tag' => 'sp-jwt-auth-config'];

        if ($this->option('force')) {
            $publishOptions['--force'] = true;
        }

        $this->call('vendor:publish', $publishOptions);

        $publishOptions['--tag'] = 'sp-jwt-auth-migrations';
        $this->call('vendor:publish', $publishOptions);

        if (! $this->option('skip-auth-guard')) {
            $this->patchAuthConfig();
        }

        if ($this->option('keys')) {
            $this->call('sp-jwt-auth:keys', [
                '--generate' => true,
                '--pem' => true,
                '--force' => (bool) $this->option('force'),
            ]);
        }

        $this->ensureRefreshHashKey();

        $this->line('Run php artisan sp-jwt-auth:validate after reviewing config/sp-jwt-auth.php.');

        return self::SUCCESS;
    }

    private function patchAuthConfig(): void
    {
        $path = config_path('auth.php');

        if (! file_exists($path)) {
            $this->warn('config/auth.php was not found. Add the sp-jwt API guard manually.');

            return;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            $this->warn('Unable to read config/auth.php. Add the sp-jwt API guard manually.');

            return;
        }

        $patched = AuthConfigPatcher::patch($contents);

        if ($patched === null) {
            $this->line('config/auth.php already has an API guard or could not be patched automatically.');

            return;
        }

        file_put_contents($path, $patched);
        $this->info('Added sp-jwt API guard to config/auth.php.');
    }

    private function ensureRefreshHashKey(): void
    {
        if (is_string(config('sp-jwt-auth.hash_keys.items.default.key'))) {
            return;
        }

        $envPath = base_path('.env');
        $secret = bin2hex(random_bytes(32));

        $created = EnvFile::put($envPath, 'SP_JWT_REFRESH_HASH_KEY', $secret, overwrite: false);
        $this->line($created
            ? 'Ensured SP_JWT_REFRESH_HASH_KEY exists in .env.'
            : 'SP_JWT_REFRESH_HASH_KEY already exists in .env.');
    }
}
