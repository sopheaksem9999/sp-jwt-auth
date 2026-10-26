<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Contracts;

use Throwable;

/**
 * An OAuth failure that maps to an RFC 6749 §5.2 error response.
 */
interface OAuthErrorException extends Throwable
{
    /** The RFC 6749 `error` code, e.g. `invalid_client`. */
    public function oauthError(): string;

    public function oauthStatus(): int;
}
