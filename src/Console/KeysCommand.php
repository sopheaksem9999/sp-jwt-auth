<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class KeysCommand extends Command
{
    protected $signature = 'sp-jwt-auth:keys {--generate} {--rotate} {--retire} {--revoke} {--force} {--kid=} {--compromised} {--algorithm=RS256} {--path=storage}';

    protected $description = 'Generate or describe JWT signing key lifecycle changes.';

    public function handle(): int
    {
        $kid = (string) ($this->option('kid') ?: now()->format('Y-m') . '-' . Str::random(8));

        if ($this->option('generate') || $this->option('rotate')) {
            $path = base_path((string) $this->option('path'));

            if (! is_dir($path)) {
                mkdir($path, 0755, true);
            }

            $resource = openssl_pkey_new([
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
            ]);

            if ($resource === false || ! openssl_pkey_export($resource, $private)) {
                $this->error('Unable to generate RSA key pair.');

                return self::FAILURE;
            }

            $details = openssl_pkey_get_details($resource);
            $public = (string) ($details['key'] ?? '');
            file_put_contents($path . sprintf('/jwt-private-%s.key', $kid), $private);
            file_put_contents($path . sprintf('/jwt-public-%s.key', $kid), $public);

            $this->info(sprintf('Generated key pair for kid [%s].', $kid));
            $this->line(sprintf('Set SP_JWT_ACTIVE_KID=%s and point config keys.items.%s to the generated files.', $kid, $kid));

            return self::SUCCESS;
        }

        if ($this->option('retire')) {
            $this->line(sprintf('Remove [%s] from SP_JWT_PREVIOUS_KIDS after all tokens signed by it expire.', $kid));

            return self::SUCCESS;
        }

        if ($this->option('revoke')) {
            $this->line(sprintf('Add [%s] to SP_JWT_COMPROMISED_KIDS immediately.', $kid));

            return self::SUCCESS;
        }

        $this->warn('Choose --generate, --rotate, --retire, or --revoke.');

        return self::FAILURE;
    }
}
