<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Exceptions;

use RuntimeException as ParentException;
use Sopheak\JwtAuth\Contracts\OAuthErrorException;

final class OAuthMalformedTokenException extends ParentException implements OAuthErrorException
{
    public function oauthError(): string
    {
        return 'invalid_grant';
    }

    public function oauthStatus(): int
    {
        return 400;
    }
}
