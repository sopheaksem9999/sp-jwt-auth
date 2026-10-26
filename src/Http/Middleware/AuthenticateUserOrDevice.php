<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Accepts a user token (configured guard) or a device token (`sp_device` is set for the latter). */
final readonly class AuthenticateUserOrDevice
{
    public function __construct(private AuthenticateDevice $device)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user((string) config('sp-jwt-auth.guard', 'api')) !== null) {
            return $next($request);
        }

        return $this->device->handle($request, $next);
    }
}
