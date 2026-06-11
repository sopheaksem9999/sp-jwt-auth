<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Signing;

use RuntimeException;

final class ConfigSigningKeyRepository implements SigningKeyRepository
{
    public function active(): SigningKey
    {
        $kid = (string) config('sp-jwt-auth.keys.active_kid');

        if ($kid === '') {
            throw new RuntimeException('No active JWT key id is configured.');
        }

        $key = $this->make($kid);

        if (! $key->canSign()) {
            throw new RuntimeException(sprintf('JWT key [%s] cannot sign new tokens.', $kid));
        }

        return $key;
    }

    public function forVerification(string $kid): SigningKey
    {
        $key = $this->make($kid);

        if (! $key->canVerify()) {
            throw new RuntimeException(sprintf('JWT key [%s] cannot verify tokens.', $kid));
        }

        return $key;
    }

    /**
     * @return SigningKey[]
     */
    public function publicKeys(bool $activeOnly = false): array
    {
        $items = config('sp-jwt-auth.keys.items', []);
        $keys = [];

        foreach (array_keys(is_array($items) ? $items : []) as $kid) {
            $key = $this->make((string) $kid);

            if ($activeOnly && $key->state !== 'active') {
                continue;
            }

            if (in_array($key->state, ['active', 'previous'], true)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    private function make(string $kid): SigningKey
    {
        $item = config('sp-jwt-auth.keys.items.' . $kid);

        if (! is_array($item)) {
            throw new RuntimeException(sprintf('JWT key [%s] is not configured.', $kid));
        }

        $state = (string) ($item['state'] ?? 'active');

        if ($state === 'compromised') {
            throw new RuntimeException(sprintf('JWT key [%s] is compromised.', $kid));
        }

        return new SigningKey(
            $kid,
            (string) config('sp-jwt-auth.algorithm', 'RS256'),
            isset($item['private_key']) ? (string) $item['private_key'] : null,
            (string) ($item['public_key'] ?? ''),
            $state,
        );
    }
}
