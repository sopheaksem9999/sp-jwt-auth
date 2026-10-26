<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Sopheak\JwtAuth\DTO\ExternalIdentity;
use Sopheak\JwtAuth\Events\ExternalIdentityResolved;
use Sopheak\JwtAuth\Models\ExternalIdentity as ExternalIdentityModel;

final class ExternalIdentityStore
{
    public function store(ExternalIdentity $identity, ?Authenticatable $user = null): ExternalIdentityModel
    {
        $record = ExternalIdentityModel::query()
            ->where('provider', $identity->provider)
            ->where('provider_user_id', $identity->providerUserId)
            ->first() ?? new ExternalIdentityModel([
                'id' => (string) Str::uuid(),
                'provider' => $identity->provider,
                'provider_user_id' => $identity->providerUserId,
            ]);

        $record->forceFill([
            'email' => $identity->email,
            'email_verified' => $identity->emailVerified,
            'name' => $identity->name,
            'avatar' => $identity->avatar,
            'raw_profile' => $identity->rawProfile,
            'provider_tokens' => $this->providerTokens($identity),
            'last_resolved_at' => now(),
        ]);

        // Omitting the user keeps an existing link; use unlink() to remove it.
        if ($user instanceof Authenticatable) {
            $record->forceFill(['user_type' => $user::class, 'user_id' => (string) $user->getAuthIdentifier()]);
        }

        $record->save();

        Event::dispatch(new ExternalIdentityResolved($identity, $record));

        return $record;
    }

    public function unlink(string $provider, string $providerUserId): void
    {
        ExternalIdentityModel::query()
            ->where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->update(['user_type' => null, 'user_id' => null]);
    }

    public function find(string $provider, string $providerUserId): ?ExternalIdentityModel
    {
        $record = ExternalIdentityModel::query()
            ->where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first();

        return $record instanceof ExternalIdentityModel ? $record : null;
    }

    private function providerTokens(ExternalIdentity $identity): ?array
    {
        if (! (bool) config('sp-jwt-auth.external_identities.store_provider_tokens', false)) {
            return null;
        }

        return $identity->providerTokens;
    }
}
