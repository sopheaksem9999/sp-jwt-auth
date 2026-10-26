<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Sopheak\JwtAuth\Models\Device;
use Sopheak\JwtAuth\Services\JwtTokenService;
use Sopheak\JwtAuth\Support\LastUsedThrottle;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** Authenticates a device-scoped token and exposes the device as `sp_device`. */
final readonly class AuthenticateDevice
{
    public function __construct(private JwtTokenService $tokens)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $bearer = $request->bearerToken();

        if ($bearer === null || $bearer === '') {
            abort(401);
        }

        try {
            $token = $this->tokens->validateAccessToken($bearer);
        } catch (Throwable) {
            abort(401);
        }

        $device = $token->user_type === Device::class ? Device::query()->whereKey($token->user_id)->first() : null;

        if (! $device instanceof Device || $device->isRevoked()) {
            abort(401);
        }

        if (LastUsedThrottle::due($device->last_seen_at)) {
            $device->forceFill(['last_seen_at' => now()])->save();
        }

        $request->attributes->set('sp_device', $device);
        $request->attributes->set('sp_device_token', $token);

        return $next($request);
    }
}
