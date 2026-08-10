<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Contracts;

interface ResponseEnvelope
{
    public function wrap(array $payload): array;
}
