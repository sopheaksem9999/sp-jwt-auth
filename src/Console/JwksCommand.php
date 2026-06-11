<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Console;

use Illuminate\Console\Command;
use Sopheak\JwtAuth\Signing\JwksFormatter;
use Sopheak\JwtAuth\Signing\SigningKeyRepository;

final class JwksCommand extends Command
{
    protected $signature = 'sp-jwt-auth:jwks {--output=} {--pretty} {--active-only}';

    protected $description = 'Print or write the sp-jwt-auth public JWKS document.';

    public function handle(SigningKeyRepository $keys, JwksFormatter $formatter): int
    {
        $flags = $this->option('pretty') ? JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES : JSON_UNESCAPED_SLASHES;
        $json = json_encode($formatter->format($keys->publicKeys((bool) $this->option('active-only'))), $flags | JSON_THROW_ON_ERROR);

        if ($this->option('output')) {
            file_put_contents((string) $this->option('output'), $json);
        } else {
            $this->line($json);
        }

        return self::SUCCESS;
    }
}
