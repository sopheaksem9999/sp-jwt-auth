<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Security;

use RuntimeException;

final class HashKeyRepository
{
    public function active(): HashKey
    {
        $id = (string) config('sp-jwt-auth.hash_keys.active_id', 'default');

        return $this->find($id);
    }

    public function find(string $id): HashKey
    {
        $item = config('sp-jwt-auth.hash_keys.items.' . $id);

        if (! is_array($item)) {
            throw new RuntimeException(sprintf('Hash key [%s] is not configured.', $id));
        }

        return new HashKey($id, (string) ($item['key'] ?? ''), (string) ($item['state'] ?? 'active'));
    }
}
