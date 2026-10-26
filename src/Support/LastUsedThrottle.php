<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Support;

use Carbon\CarbonInterface;

final class LastUsedThrottle
{
    /**
     * Whether last_used_at should be written now. An interval of 0 (default)
     * writes on every request.
     */
    public static function due(?CarbonInterface $lastUsedAt): bool
    {
        $interval = (int) config('sp-jwt-auth.last_used_interval_minutes', 0);

        return $interval <= 0
            || ! $lastUsedAt instanceof CarbonInterface
            || $lastUsedAt->lte(now()->subMinutes($interval));
    }
}
