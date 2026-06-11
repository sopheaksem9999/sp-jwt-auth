<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class KeysCommand extends Command
{
    protected $signature = 'sp-jwt-auth:keys
        {--generate : Generate a new signing key pair}
        {--rotate : Generate a new key and retire the current active one}
        {--retire : Mark a key as retired (tokens remain valid until expiry)}
        {--revoke : Mark a key as compromised (reject all tokens signed with it)}
        {--force : Overwrite existing key files without prompting}
        {--kid= : Key identifier (auto-generated if omitted)}
        {--compromised : Mark the key as compromised during revocation}
        {--algorithm=RS256 : Signing algorithm for the generated key}
        {--path=storage : Directory to write key files (relative to project root)}
        {--pem : Use .pem extension instead of .key}
        {--write-env : Write SP_JWT_ACTIVE_KID to .env automatically}';

    protected $description = 'Generate or describe JWT signing key lifecycle changes.';

    public function handle(): int
    {
        $kid = (string) ($this->option('kid') ?: now()->format('Y-m') . '-' . Str::random(8));
        $ext = $this->option('pem') ? 'pem' : 'key';

        if ($this->option('generate') || $this->option('rotate')) {
            $path = base_path((string) $this->option('path'));

            if (! is_dir($path)) {
                mkdir($path, 0755, true);
            }

            $privateKeyPath = $path . sprintf('/jwt-private-%s.%s', $kid, $ext);
            $publicKeyPath = $path . sprintf('/jwt-public-%s.%s', $kid, $ext);

            if (! $this->option('force') && (file_exists($privateKeyPath) || file_exists($publicKeyPath))) {
                $this->warn('Key files already exist. Use --force to overwrite.');

                return self::FAILURE;
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
            file_put_contents($privateKeyPath, $private);
            file_put_contents($publicKeyPath, $public);

            $this->info(sprintf('Generated key pair for kid [%s].', $kid));
            $this->line(sprintf('  Private: %s', $privateKeyPath));
            $this->line(sprintf('  Public:  %s', $publicKeyPath));

            if ($this->option('write-env')) {
                $envPath = base_path('.env');
                $line = sprintf("SP_JWT_ACTIVE_KID=%s", $kid);

                if (file_exists($envPath)) {
                    $contents = file_get_contents($envPath);
                    if (str_contains($contents, 'SP_JWT_ACTIVE_KID=')) {
                        $updated = preg_replace(
                            '/^SP_JWT_ACTIVE_KID=.*$/m',
                            $line,
                            $contents,
                        );
                        file_put_contents($envPath, $updated);
                        $this->line(sprintf('Updated SP_JWT_ACTIVE_KID in %s', $envPath));
                    } else {
                        file_put_contents($envPath, $contents . PHP_EOL . $line . PHP_EOL);
                        $this->line(sprintf('Appended SP_JWT_ACTIVE_KID to %s', $envPath));
                    }
                } else {
                    $this->warn('No .env file found at project root. Set manually:');
                    $this->line($line);
                }
            } else {
                $this->line(sprintf('Set SP_JWT_ACTIVE_KID=%s in your .env', $kid));
            }

            $this->newLine();
            $this->line('Add this to config/sp-jwt-auth.php under keys.items:');
            $this->line(sprintf(
                <<<'CONFIG'
    '%s' => [
        'state' => 'active',
        'private_key_path' => base_path('%s'),
        'public_key_path' => base_path('%s'),
    ],
CONFIG,
                $kid,
                $this->relativePath($privateKeyPath),
                $this->relativePath($publicKeyPath),
            ));

            return self::SUCCESS;
        }

        if ($this->option('retire')) {
            $this->line(sprintf('Remove [%s] from active keys and add to SP_JWT_PREVIOUS_KIDS.', $kid));
            $this->line('Tokens signed with a retired key remain valid until they expire.');

            return self::SUCCESS;
        }

        if ($this->option('revoke')) {
            $tag = $this->option('compromised') ? 'SP_JWT_COMPROMISED_KIDS' : 'SP_JWT_REVOKED_KIDS';
            $this->line(sprintf('Add [%s] to %s immediately.', $kid, $tag));

            return self::SUCCESS;
        }

        $this->warn('Choose --generate, --rotate, --retire, or --revoke.');

        return self::FAILURE;
    }

    private function relativePath(string $absolute): string
    {
        $base = base_path();

        return str_starts_with($absolute, $base)
            ? substr($absolute, strlen($base) + 1)
            : $absolute;
    }
}
