<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\DTO;

use Carbon\CarbonInterface;
use Sopheak\JwtAuth\Models\OAuthAuthorizationCode as OAuthAuthorizationCodeModel;

final readonly class OAuthAuthorizationCode
{
    public function __construct(
        public string $code,
        public string $redirectUri,
        public CarbonInterface $expiresAt,
        public OAuthAuthorizationCodeModel $record,
    ) {
    }
}
