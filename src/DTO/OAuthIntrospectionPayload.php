<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\DTO;

use Carbon\CarbonInterface;

final readonly class OAuthIntrospectionPayload
{
    public function __construct(
        public bool $active,
        public ?string $clientId = null,
        public ?string $userType = null,
        public ?string $userId = null,
        public ?string $grantType = null,
        public array $scopes = [],
        public array $claims = [],
        public ?string $tokenId = null,
        public ?CarbonInterface $expiresAt = null,
    ) {
    }

    /**
     * RFC 7662 §2.2 response members.
     *
     * @return array<string, mixed>
     */
    public function toRfcArray(): array
    {
        if (! $this->active) {
            return ['active' => false];
        }

        return array_filter([
            'active' => true,
            'scope' => implode(' ', $this->scopes),
            'client_id' => $this->clientId,
            'sub' => $this->userId,
            'token_type' => 'Bearer',
            'exp' => $this->expiresAt?->getTimestamp(),
            'jti' => $this->tokenId,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
