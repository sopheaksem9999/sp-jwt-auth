<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Console;

use Illuminate\Console\Command;
use Sopheak\JwtAuth\Support\AuthConfigPatcher;
use Sopheak\JwtAuth\Support\SetupValidator;

final class ValidateCommand extends Command
{
    protected $signature = 'sp-jwt-auth:validate
        {--fix : Publish missing scaffolding and attempt safe config/auth.php fixes}
        {--json : Output a machine-readable JSON report}';

    protected $description = 'Validate client application setup for sp-jwt-auth.';

    public function handle(SetupValidator $validator): int
    {
        if ($this->option('fix')) {
            $this->call('vendor:publish', ['--tag' => 'sp-jwt-auth-config']);
            $this->call('vendor:publish', ['--tag' => 'sp-jwt-auth-migrations']);
            $this->patchAuthConfig();
        }

        $report = $validator->report();

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $report['status'] === 'ok' ? self::SUCCESS : self::FAILURE;
        }

        foreach ($report['warnings'] as $warning) {
            $this->warn($warning);
        }

        foreach ($report['errors'] as $error) {
            $this->error($error);
        }

        if ($report['errors'] !== []) {
            $this->newLine();
            $this->line('Run php artisan sp-jwt-auth:setup --keys, then configure keys.items and SP_JWT_REFRESH_HASH_KEY.');

            return self::FAILURE;
        }

        $this->info('sp-jwt-auth setup looks valid.');

        return self::SUCCESS;
    }

    private function patchAuthConfig(): void
    {
        $path = config_path('auth.php');

        if (! file_exists($path)) {
            return;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return;
        }

        $patched = AuthConfigPatcher::patch($contents);

        if ($patched !== null) {
            file_put_contents($path, $patched);
            $this->info('Added sp-jwt API guard to config/auth.php.');
        }
    }
}
