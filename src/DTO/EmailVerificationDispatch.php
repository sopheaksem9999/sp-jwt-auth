<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\DTO;

use Illuminate\Contracts\Auth\Authenticatable;
use Sopheak\JwtAuth\Models\EmailVerificationToken;

final readonly class EmailVerificationDispatch
{
    public function __construct(
        public string $tokenId,
        public string $token,
        public string $plaintextToken,
        public Authenticatable $user,
        public string $email,
        public EmailVerificationToken $record,
    ) {
    }
}
