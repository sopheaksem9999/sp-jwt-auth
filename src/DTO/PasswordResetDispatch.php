<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\DTO;

use Illuminate\Contracts\Auth\Authenticatable;
use Sopheak\JwtAuth\Models\PasswordResetToken;

final readonly class PasswordResetDispatch
{
    public function __construct(
        public string $tokenId,
        public string $token,
        public string $plaintextToken,
        public Authenticatable $user,
        public string $email,
        public PasswordResetToken $record,
    ) {
    }
}
