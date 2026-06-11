<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Events;

use Sopheak\JwtAuth\Models\MfaChallenge;

final readonly class MfaChallengeCompleted
{
    public function __construct(public MfaChallenge $challenge)
    {
    }
}
