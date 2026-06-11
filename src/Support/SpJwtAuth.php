<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Support;

final class SpJwtAuth
{
    public static function hooks(): HookRegistry
    {
        return app(HookRegistry::class);
    }
}
