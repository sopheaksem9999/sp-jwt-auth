<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Security;

final readonly class SecretHasher
{
    public function __construct(private HashKeyRepository $keys)
    {
    }

    /** @return array{hash: string, hash_key_id: string} */
    public function hash(string $secret): array
    {
        $key = $this->keys->active();

        return [
            'hash' => hash_hmac('sha256', $secret, $key->key),
            'hash_key_id' => $key->id,
        ];
    }

    public function verify(string $secret, string $hash, ?string $hashKeyId): bool
    {
        $key = $this->keys->find($hashKeyId ?: $this->keys->active()->id);
        $actual = hash_hmac('sha256', $secret, $key->key);

        return hash_equals($hash, $actual);
    }
}
