<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\DTO;

final readonly class DeviceRegistration
{
    /**
     * @param array<string, mixed> $metadata
     * @param array<string, mixed>|null $attestation
     */
    public function __construct(
        public string $platform,
        public ?string $name = null,
        public ?string $clientVersion = null,
        public array $metadata = [],
        public ?array $attestation = null,
    ) {
    }
}
