<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\DTO;

use InvalidArgumentException;

final readonly class TokenSubject
{
    public function __construct(
        public string $type,
        public string $id,
    ) {
        if ($type === '' || $id === '') {
            throw new InvalidArgumentException('Token subject type and id are required.');
        }
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return ['type' => $this->type, 'id' => $this->id];
    }
}
