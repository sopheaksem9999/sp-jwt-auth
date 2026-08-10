<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Support;

use Illuminate\Database\Schema\Blueprint;

final class UserIdColumn
{
    public static function apply(Blueprint $table, string $name = 'user_id', bool $nullable = false): void
    {
        $column = config('sp-jwt-auth.id_type', 'integer') === 'uuid'
            ? $table->uuid($name)
            : $table->unsignedBigInteger($name);

        if ($nullable) {
            $column->nullable();
        }
    }
}
