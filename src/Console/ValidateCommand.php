<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Sopheak\JwtAuth\Support\AuthConfigPatcher;

final class ValidateCommand extends Command
{
    protected $signature = 'sp-jwt-auth:validate
        {--fix : Publish missing scaffolding and attempt safe config/auth.php fixes}';

    protected $description = 'Validate client application setup for sp-jwt-auth.';

    public function handle(): int
    {
        if ($this->option('fix')) {
            $this->call('vendor:publish', ['--tag' => 'sp-jwt-auth-config']);
            $this->call('vendor:publish', ['--tag' => 'sp-jwt-auth-migrations']);
            $this->patchAuthConfig();
        }

        $errors = [];
        $warnings = [];
        $guard = (string) config('sp-jwt-auth.guard', 'api');
        $guardConfig = config(sprintf('auth.guards.%s', $guard));

        if (! is_array($guardConfig)) {
            $errors[] = sprintf('auth.guards.%s must exist and use the [sp-jwt] driver.', $guard);
        } elseif (($guardConfig['driver'] ?? null) !== 'sp-jwt') {
            $errors[] = sprintf('auth.guards.%s.driver must be [sp-jwt].', $guard);
        }

        $provider = is_array($guardConfig)
            ? (string) ($guardConfig['provider'] ?? config('sp-jwt-auth.user_provider', 'users'))
            : (string) config('sp-jwt-auth.user_provider', 'users');

        if (! is_array(config(sprintf('auth.providers.%s', $provider)))) {
            $errors[] = sprintf('auth.providers.%s must exist for the configured JWT guard.', $provider);
        }

        $this->validateSigningKey($errors);
        $this->validateHashKey($errors);

        if ((bool) config('sp-jwt-auth.keys.jwks_enabled', true) && ! Route::has('sp-jwt-auth.jwks')) {
            $warnings[] = 'JWKS is enabled, but the sp-jwt-auth.jwks route was not registered.';
        }

        foreach ($warnings as $warning) {
            $this->warn($warning);
        }

        foreach ($errors as $error) {
            $this->error($error);
        }

        if ($errors !== []) {
            $this->newLine();
            $this->line('Run php artisan sp-jwt-auth:setup --keys, then configure keys.items and SP_JWT_REFRESH_HASH_KEY.');

            return self::FAILURE;
        }

        $this->info('sp-jwt-auth setup looks valid.');

        return self::SUCCESS;
    }

    /**
     * @param list<string> $errors
     */
    private function validateSigningKey(array &$errors): void
    {
        $activeKid = config('sp-jwt-auth.keys.active_kid');
        $keys = config('sp-jwt-auth.keys.items');

        if (! is_string($activeKid) || $activeKid === '') {
            $errors[] = 'sp-jwt-auth.keys.active_kid must be configured.';

            return;
        }

        if (! is_array($keys) || ! array_key_exists($activeKid, $keys)) {
            $errors[] = sprintf('sp-jwt-auth.keys.items must contain the active kid [%s].', $activeKid);

            return;
        }

        $key = $keys[$activeKid];

        if (! is_array($key) || ! $this->hasSigningMaterial($key, 'private_key') || ! $this->hasSigningMaterial($key, 'public_key')) {
            $errors[] = sprintf('sp-jwt-auth.keys.items.%s must contain signing key material.', $activeKid);
        }
    }

    /**
     * @param array<string, mixed> $key
     */
    private function hasSigningMaterial(array $key, string $name): bool
    {
        $inline = $key[$name] ?? null;
        $path = $key[$name . '_path'] ?? null;

        if (is_string($inline) && $inline !== '') {
            return true;
        }

        return is_string($path) && $path !== '' && file_exists($path);
    }

    /**
     * @param list<string> $errors
     */
    private function validateHashKey(array &$errors): void
    {
        $activeId = config('sp-jwt-auth.hash_keys.active_id');
        $keys = config('sp-jwt-auth.hash_keys.items');

        if (! is_string($activeId) || $activeId === '') {
            $errors[] = 'sp-jwt-auth.hash_keys.active_id must be configured.';

            return;
        }

        if (! is_array($keys) || ! isset($keys[$activeId]) || ! is_array($keys[$activeId])) {
            $errors[] = sprintf('sp-jwt-auth.hash_keys.items must contain the active hash key [%s].', $activeId);

            return;
        }

        $key = $keys[$activeId]['key'] ?? null;

        if (! is_string($key) || $key === '') {
            $errors[] = sprintf('sp-jwt-auth.hash_keys.items.%s.key must be a non-empty secret.', $activeId);
        }
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
