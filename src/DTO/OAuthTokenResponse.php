<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\DTO;

final readonly class OAuthTokenResponse
{
    public function __construct(
        public string $accessToken,
        public string $tokenType,
        public int $expiresIn,
        public ?string $refreshToken = null,
        public array $scopes = [],
    ) {
    }

    public function toArray(): array
    {
        $payload = [
            'access_token' => $this->accessToken,
            'token_type' => $this->tokenType,
            'expires_in' => $this->expiresIn,
            'scope' => implode(' ', $this->scopes),
        ];

        if ($this->refreshToken !== null) {
            $payload['refresh_token'] = $this->refreshToken;
        }

        return $payload;
    }
}
