<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Events;

use Sopheak\JwtAuth\DTO\ExternalIdentity;
use Sopheak\JwtAuth\Models\ExternalIdentity as ExternalIdentityModel;

final readonly class ExternalIdentityResolved
{
    public function __construct(
        public ExternalIdentity $identity,
        public ExternalIdentityModel $record,
    ) {
    }
}
