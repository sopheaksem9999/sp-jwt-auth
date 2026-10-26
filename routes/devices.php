<?php

declare(strict_types=1);

use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Sopheak\JwtAuth\DTO\DeviceRegistration;
use Sopheak\JwtAuth\DTO\TokenPair;
use Sopheak\JwtAuth\Services\DeviceService;
use Sopheak\JwtAuth\Support\DeviceConfig;
use Sopheak\JwtAuth\Support\RefreshCookie;
use Sopheak\JwtAuth\Support\ResponseEnvelope;

$respond = static function (array $extra, TokenPair $pair, int $status) {
    $payload = $extra + ['access_token' => $pair->accessToken];

    if (RefreshCookie::usesBody()) {
        $payload['refresh_token'] = $pair->refreshToken;
    }

    $payload += ['token_type' => 'Bearer', 'expires_in' => $pair->expiresIn()];

    return RefreshCookie::attach(
        response()->json(ResponseEnvelope::wrap($payload, (string) config('sp-jwt-auth.token_endpoints.response_envelope', 'raw')), $status),
        $pair,
    );
};

Route::prefix(DeviceConfig::routePrefix())->middleware(ThrottleRequests::class . ':sp-jwt-devices')->group(function () use ($respond): void {
    Route::post('/devices', static function (Request $request, DeviceService $devices) use ($respond) {
        $data = $request->validate([
            'platform' => ['required', 'string', Rule::in(DeviceConfig::platforms())],
            'name' => ['nullable', 'string', 'max:255'],
            'client_version' => ['nullable', 'string', 'max:50'],
            'attestation' => ['nullable', 'array'],
        ]);

        try {
            $result = $devices->register(new DeviceRegistration(
                platform: $data['platform'],
                name: $data['name'] ?? null,
                clientVersion: $data['client_version'] ?? null,
                attestation: $data['attestation'] ?? null,
            ));
        } catch (AuthenticationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 401);
        }

        return $respond(['device_id' => $result->device->public_id, 'device_credential' => $result->credential], $result->tokens, 201);
    });

    Route::post('/devices/token', static function (Request $request, DeviceService $devices) use ($respond) {
        $data = $request->validate([
            'credential' => ['required', 'string'],
            'attestation' => ['nullable', 'array'],
        ]);

        try {
            $device = $devices->authenticate($data['credential'], $data['attestation'] ?? null);
        } catch (AuthenticationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 401);
        }

        return $respond(['device_id' => $device->public_id], $devices->issueTokenPair($device), 200);
    });
});
