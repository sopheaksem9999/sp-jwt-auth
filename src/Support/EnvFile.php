<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Support;

final class EnvFile
{
    public static function put(string $path, string $key, string $value, bool $overwrite = true): bool
    {
        $line = sprintf('%s=%s', $key, $value);

        if (! file_exists($path)) {
            file_put_contents($path, $line . PHP_EOL);

            return true;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return false;
        }

        if (preg_match(sprintf('/^%s=.*$/m', preg_quote($key, '/')), $contents) === 1) {
            if (! $overwrite) {
                return false;
            }

            $updated = preg_replace(
                sprintf('/^%s=.*$/m', preg_quote($key, '/')),
                $line,
                $contents,
            );

            if (! is_string($updated)) {
                return false;
            }

            file_put_contents($path, $updated);

            return true;
        }

        file_put_contents($path, rtrim($contents) . PHP_EOL . $line . PHP_EOL);

        return true;
    }
}
