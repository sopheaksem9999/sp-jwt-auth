<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Signing;

final readonly class SigningKey
{
    public function __construct(
        public string $kid,
        public string $algorithm,
        public ?string $privateKey,
        public string $publicKey,
        public string $state,
    ) {
    }

    public function canSign(): bool
    {
        return $this->state === 'active' && $this->privateKey !== null;
    }

    public function canVerify(): bool
    {
        return in_array($this->state, ['active', 'previous'], true);
    }
}
