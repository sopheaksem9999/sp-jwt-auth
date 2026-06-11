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
        /** @var ExternalIdentityModel $record */
        $record = ExternalIdentityModel::query()->updateOrCreate([
            'provider' => $identity->provider,
            'provider_user_id' => $identity->providerUserId,
        ], [
            'id' => (string) (ExternalIdentityModel::query()
                ->where('provider', $identity->provider)
                ->where('provider_user_id', $identity->providerUserId)
                ->value('id') ?: Str::uuid()),
            'user_type' => $user instanceof Authenticatable ? $user::class : null,
            'user_id' => $user instanceof Authenticatable ? (string) $user->getAuthIdentifier() : null,
            'email' => $identity->email,
            'email_verified' => $identity->emailVerified,
            'name' => $identity->name,
            'avatar' => $identity->avatar,
            'raw_profile' => $identity->rawProfile,
            'provider_tokens' => $this->providerTokens($identity),
            'last_resolved_at' => now(),
        ]);

        Event::dispatch(new ExternalIdentityResolved($identity, $record));

        return $record;
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
