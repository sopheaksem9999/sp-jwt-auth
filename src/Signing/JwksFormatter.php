<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Signing;

use RuntimeException;

final class JwksFormatter
{
    /** @param list<SigningKey> $keys
     * @return array<string, list<array>> */
    public function format(array $keys): array
    {
        return [
            'keys' => array_values(array_map($this->formatKey(...), $keys)),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function formatKey(SigningKey $key): array
    {
        $resource = openssl_pkey_get_public($key->publicKey);

        if ($resource === false) {
            throw new RuntimeException(sprintf('JWT public key [%s] is invalid.', $key->kid));
        }

        $details = openssl_pkey_get_details($resource);
        $rsa = $details['rsa'] ?? null;

        if (! is_array($rsa) || ! isset($rsa['n'], $rsa['e'])) {
            throw new RuntimeException(sprintf('JWT public key [%s] is not RSA.', $key->kid));
        }

        return [
            'kty' => 'RSA',
            'use' => 'sig',
            'kid' => $key->kid,
            'alg' => $key->algorithm,
            'n' => $this->base64Url($rsa['n']),
            'e' => $this->base64Url($rsa['e']),
        ];
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
