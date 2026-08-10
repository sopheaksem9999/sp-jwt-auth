<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Fixtures;

use Sopheak\JwtAuth\Contracts\ResponseEnvelope;

final class TestEnvelope implements ResponseEnvelope
{
    public function wrap(array $payload): array
    {
        return ['wrapped' => $payload];
    }
}
