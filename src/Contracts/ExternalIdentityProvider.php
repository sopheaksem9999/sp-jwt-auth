<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Contracts;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Sopheak\JwtAuth\DTO\ExternalIdentity;

interface ExternalIdentityProvider
{
    public function redirect(string $provider, array $options = []): RedirectResponse;

    public function callback(string $provider, Request $request): ExternalIdentity;
}
