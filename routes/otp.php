<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Sopheak\JwtAuth\DTO\OtpDestination;
use Sopheak\JwtAuth\Services\FirstFactorOtpBroker;

Route::prefix((string) config('sp-jwt-auth.first_factor_otp.route_prefix', 'otp'))->group(function (): void {
    Route::post('/request', static function (Request $request, FirstFactorOtpBroker $broker) {
        $data = $request->validate([
            'destination' => ['required', 'string', 'max:255'],
            'channel' => ['required', 'string', 'in:email,sms'],
            'purpose' => ['required', 'string', 'max:50'],
            'requested_type' => ['sometimes', 'string', 'max:50'],
        ]);

        $destination = $data['channel'] === 'email'
            ? OtpDestination::email($data['destination'])
            : OtpDestination::phone($data['destination']);

        $dispatch = $broker->request(
            $destination,
            $data['purpose'],
            is_string($data['requested_type'] ?? null) ? $data['requested_type'] : null,
        );

        return response()->json([
            'otp_id' => $dispatch->otpId,
            'destination_masked' => $destination->maskedDestination,
            'expires_in' => (int) config('sp-jwt-auth.first_factor_otp.ttl_minutes', 5) * 60,
        ], 202);
    })->middleware('throttle:sp-jwt-ffotp-request');

    Route::post('/resend', static function (Request $request, FirstFactorOtpBroker $broker) {
        $data = $request->validate([
            'otp_id' => ['required', 'string'],
            'destination' => ['required', 'string', 'max:255'],
            'channel' => ['required', 'string', 'in:email,sms'],
        ]);

        $destination = $data['channel'] === 'email'
            ? OtpDestination::email($data['destination'])
            : OtpDestination::phone($data['destination']);

        $dispatch = $broker->resend($data['otp_id'], $destination);

        return response()->json([
            'otp_id' => $dispatch->otpId,
            'destination_masked' => $destination->maskedDestination,
            'expires_in' => (int) config('sp-jwt-auth.first_factor_otp.ttl_minutes', 5) * 60,
        ], 202);
    })->middleware('throttle:sp-jwt-ffotp-request');

    Route::post('/verify', static function (Request $request, FirstFactorOtpBroker $broker) {
        $data = $request->validate([
            'otp_id' => ['required', 'string'],
            'code' => ['required', 'string', 'max:10'],
            'destination' => ['sometimes', 'string', 'max:255'],
            'channel' => ['sometimes', 'string', 'in:email,sms'],
        ]);

        $destination = null;

        if (is_string($data['destination'] ?? null) && isset($data['channel'])) { // Deviation J
            $destination = $data['channel'] === 'email'
                ? OtpDestination::email($data['destination'])
                : OtpDestination::phone($data['destination']);
        }

        $verification = $broker->verify($data['otp_id'], $data['code'], $destination);

        return response()->json([
            'access_token' => $verification->pair->accessToken,
            'refresh_token' => $verification->pair->refreshToken,
            'token_type' => 'Bearer',
            'expires_in' => $verification->pair->expiresIn(),
        ]);
    })->middleware('throttle:sp-jwt-ffotp-verify');
});
