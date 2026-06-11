<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Support;

use Sopheak\JwtAuth\DTO\TokenPair;

final class TokenResponse
{
    public static function passportCompatible(TokenPair $pair, array $extra = []): array
    {
        return array_merge([
            'token_type' => 'Bearer',
            'expires_in' => $pair->expiresIn(),
            'access_token' => $pair->accessToken,
            'refresh_token' => $pair->refreshToken,
        ], $extra);
    }
}
