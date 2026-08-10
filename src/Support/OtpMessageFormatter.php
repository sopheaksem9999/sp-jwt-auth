<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Support;

final class OtpMessageFormatter
{
    /**
     * @param array<string, string|int> $values
     */
    public static function format(string $template, array $values): string
    {
        $search = array_map(static fn (string $key): string => sprintf('{%s}', $key), array_keys($values));

        return str_replace($search, array_values($values), $template);
    }
}
