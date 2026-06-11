<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Sopheak\JwtAuth\Traits\HasJwtTokens;

final class User extends Authenticatable
{
    use HasJwtTokens;

    protected $guarded = [];

    protected $hidden = ['password'];
}
