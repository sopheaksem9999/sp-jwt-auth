<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Security;

use RuntimeException;

final readonly class HashKey
{
    public function __construct(
        public string $id,
        public string $key,
        public string $state,
    ) {
        if ($this->key === '') {
            throw new RuntimeException(sprintf('Hash key [%s] is empty.', $id));
        }
    }
}
