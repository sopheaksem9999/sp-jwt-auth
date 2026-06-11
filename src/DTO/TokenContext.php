<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\DTO;

use InvalidArgumentException;
use Illuminate\Support\Str;

final readonly class TokenContext
{
    private const array RESERVED_CLAIMS = [
        'iss',
        'sub',
        'aud',
        'exp',
        'nbf',
        'iat',
        'jti',
        'sid',
        'scopes',
        'subject',
    ];

    public function __construct(
        public array $scopes = [],
        public array $claims = [],
        public ?string $subjectType = null,
        public ?string $subjectId = null,
        public ?string $audience = null,
        public ?string $deviceId = null,
        public ?string $deviceName = null,
        public ?string $sessionId = null,
        public array $metadata = [],
    ) {
        $this->assertSafeClaims($claims);
    }

    public static function make(): self
    {
        return new self(sessionId: (string) Str::uuid());
    }

    public function scopes(array $scopes): self
    {
        return new self(array_values($scopes), $this->claims, $this->subjectType, $this->subjectId, $this->audience, $this->deviceId, $this->deviceName, $this->sessionId, $this->metadata);
    }

    public function replaceScopes(array $scopes): self
    {
        return $this->scopes($scopes);
    }

    public function claims(array $claims): self
    {
        $this->assertSafeClaims($claims);

        return new self($this->scopes, $claims, $this->subjectType, $this->subjectId, $this->audience, $this->deviceId, $this->deviceName, $this->sessionId, $this->metadata);
    }

    public function replaceClaim(string $key, mixed $value): self
    {
        return $this->claims(array_merge($this->claims, [$key => $value]));
    }

    public function subject(string $type, string $id): self
    {
        return new self($this->scopes, $this->claims, $type, $id, $this->audience, $this->deviceId, $this->deviceName, $this->sessionId, $this->metadata);
    }

    public function sessionId(string $sessionId): self
    {
        return new self($this->scopes, $this->claims, $this->subjectType, $this->subjectId, $this->audience, $this->deviceId, $this->deviceName, $sessionId, $this->metadata);
    }

    public function subjectValue(): ?TokenSubject
    {
        return $this->subjectType !== null && $this->subjectId !== null
            ? new TokenSubject($this->subjectType, $this->subjectId)
            : null;
    }

    private function assertSafeClaims(array $claims): void
    {
        foreach ($claims as $key => $value) {
            if (! is_string($key)) {
                throw new InvalidArgumentException('Custom claim keys must be strings.');
            }

            if (in_array($key, self::RESERVED_CLAIMS, true)) {
                throw new InvalidArgumentException(sprintf('Custom claim [%s] is reserved.', $key));
            }

            json_encode($value, JSON_THROW_ON_ERROR);
        }
    }
}
