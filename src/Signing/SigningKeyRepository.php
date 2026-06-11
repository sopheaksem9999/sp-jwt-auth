<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Signing;

interface SigningKeyRepository
{
    public function active(): SigningKey;

    public function forVerification(string $kid): SigningKey;

    /** @return list<SigningKey> */
    public function publicKeys(bool $activeOnly = false): array;
}
