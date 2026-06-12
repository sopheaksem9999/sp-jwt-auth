<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Support;

final class AuthConfigPatcher
{
    public static function patch(string $contents): ?string
    {
        $guardsKey = self::findGuardsKey($contents);

        if ($guardsKey === null) {
            return null;
        }

        $arrayStart = strpos($contents, '[', $guardsKey);

        if ($arrayStart === false) {
            return null;
        }

        $arrayEnd = self::findMatchingBracket($contents, $arrayStart);

        if ($arrayEnd === null) {
            return null;
        }

        $guardsBlock = substr($contents, $arrayStart, $arrayEnd - $arrayStart + 1);

        if (preg_match("/['\"]api['\"]\s*=>/", $guardsBlock) === 1) {
            return null;
        }

        $indent = self::detectChildIndent($contents, $arrayStart);
        $snippet = PHP_EOL
            . $indent . "'api' => [" . PHP_EOL
            . $indent . "    'driver' => 'sp-jwt'," . PHP_EOL
            . $indent . "    'provider' => env('SP_JWT_USER_PROVIDER', 'users')," . PHP_EOL
            . $indent . '],' . PHP_EOL;

        return substr($contents, 0, $arrayStart + 1)
            . $snippet
            . substr($contents, $arrayStart + 1);
    }

    private static function findGuardsKey(string $contents): ?int
    {
        $matched = preg_match("/['\"]guards['\"]\s*=>\s*\[/", $contents, $matches, PREG_OFFSET_CAPTURE);

        return $matched === 1 ? $matches[0][1] : null;
    }

    private static function findMatchingBracket(string $contents, int $start): ?int
    {
        $depth = 0;
        $length = strlen($contents);
        $quote = null;
        $escaped = false;

        for ($i = $start; $i < $length; $i++) {
            $char = $contents[$i];

            if ($quote !== null) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($char === '\\') {
                    $escaped = true;
                } elseif ($char === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($char === "'" || $char === '"') {
                $quote = $char;
                continue;
            }

            if ($char === '[') {
                $depth++;
            }

            if ($char === ']') {
                $depth--;

                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    private static function detectChildIndent(string $contents, int $arrayStart): string
    {
        $lineStart = strrpos(substr($contents, 0, $arrayStart), PHP_EOL);

        if ($lineStart === false) {
            return '    ';
        }

        $line = substr($contents, $lineStart + 1, $arrayStart - $lineStart - 1);
        preg_match('/^\s*/', $line, $matches);

        return ($matches[0] ?? '') . '    ';
    }
}
