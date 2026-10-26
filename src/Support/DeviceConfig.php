<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Support;

/**
 * Device module settings. Every default lives here because a published config
 * replaces the whole `devices` array instead of merging into it.
 */
final class DeviceConfig
{
    public static function enabled(): bool
    {
        return (bool) config('sp-jwt-auth.devices.enabled', false);
    }

    public static function routePrefix(): string
    {
        return trim((string) config('sp-jwt-auth.devices.route_prefix', 'auth'), '/');
    }

    /** @return list<string> */
    public static function platforms(): array
    {
        return array_values(array_map(strval(...), (array) config('sp-jwt-auth.devices.platforms', ['web', 'ios', 'android', 'desktop'])));
    }

    /** @return list<string> */
    public static function defaultScopes(): array
    {
        return array_values(array_map(strval(...), (array) config('sp-jwt-auth.devices.default_scopes', ['device'])));
    }

    public static function perMinute(): int
    {
        return max(1, (int) config('sp-jwt-auth.devices.rate_limit.per_minute', 10));
    }

    public static function attestationRequired(): bool
    {
        return (bool) config('sp-jwt-auth.devices.attestation.required', false);
    }

    public static function attestationOnAuthenticate(): bool
    {
        return (bool) config('sp-jwt-auth.devices.attestation.on_authenticate', false);
    }

    public static function pruneInactiveDays(): int
    {
        return max(1, (int) config('sp-jwt-auth.devices.prune_inactive_days', 180));
    }
}
