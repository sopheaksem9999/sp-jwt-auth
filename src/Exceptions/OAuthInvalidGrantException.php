<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Exceptions;

use Illuminate\Auth\AuthenticationException as ParentException;
use Sopheak\JwtAuth\Contracts\OAuthErrorException;

final class OAuthInvalidGrantException extends ParentException implements OAuthErrorException
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
