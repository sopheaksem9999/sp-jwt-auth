<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Exceptions;

use InvalidArgumentException as ParentException;
use Sopheak\JwtAuth\Contracts\OAuthErrorException;

final class OAuthUnsupportedGrantTypeException extends ParentException implements OAuthErrorException
{
    public function oauthError(): string
    {
        return 'unsupported_grant_type';
    }

    public function oauthStatus(): int
    {
        return 400;
    }
}
