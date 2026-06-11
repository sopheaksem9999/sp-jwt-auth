<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Guards;

use Throwable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Sopheak\JwtAuth\Services\JwtTokenService;

final class JwtGuard implements Guard
{
    private ?Authenticatable $user = null;

    public function __construct(
        private readonly JwtTokenService $tokens,
        private readonly ?UserProvider $provider,
        private Request $request,
    ) {
    }

    public function check(): bool
    {
        return $this->user() instanceof Authenticatable;
    }

    public function guest(): bool
    {
        return ! $this->check();
    }

    public function user(): ?Authenticatable
    {
        $bearer = $this->request->bearerToken();

        if ($bearer === null || $bearer === '') {
            return null;
        }

        try {
            $token = $this->tokens->validateAccessToken($bearer);
        } catch (Throwable) {
            return null;
        }

        $user = $this->provider?->retrieveById($token->user_id);

        if (! $user instanceof Authenticatable) {
            return null;
        }

        if (method_exists($user, 'withAccessToken')) {
            $user->withAccessToken($token);
        }

        $token->forceFill(['last_used_at' => now()])->save();

        return $this->user = $user;
    }

    public function id(): mixed
    {
        return $this->user()?->getAuthIdentifier();
    }

    public function validate(array $credentials = []): bool
    {
        return false;
    }

    public function hasUser(): bool
    {
        return $this->user instanceof Authenticatable;
    }

    public function setUser(Authenticatable $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function setRequest(Request $request): void
    {
        $this->request = $request;
        $this->user = null;
    }
}
