<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Sopheak\JwtAuth\Events\OAuthConsentRevoked;
use Sopheak\JwtAuth\Models\OAuthClient;
use Sopheak\JwtAuth\Models\OAuthConsent;

final class OAuthConsentRepository
{
    public function hasConsent(Authenticatable $user, OAuthClient $client, array $scopes): bool
    {
        $consent = OAuthConsent::query()
            ->where('client_id', $client->id)
            ->where('user_type', $user::class)
            ->where('user_id', (string) $user->getAuthIdentifier())
            ->whereNull('revoked_at')
            ->first();

        return $consent instanceof OAuthConsent
            && empty(array_diff($scopes, $consent->scopes ?? []));
    }

    public function rememberConsent(Authenticatable $user, OAuthClient $client, array $scopes): void
    {
        OAuthConsent::query()->updateOrCreate([
            'client_id' => $client->id,
            'user_type' => $user::class,
            'user_id' => (string) $user->getAuthIdentifier(),
        ], [
            'id' => (string) (OAuthConsent::query()
                ->where('client_id', $client->id)
                ->where('user_type', $user::class)
                ->where('user_id', (string) $user->getAuthIdentifier())
                ->value('id') ?: Str::uuid()),
            'scopes' => array_values($scopes),
            'revoked_at' => null,
        ]);
    }

    public function revokeConsent(Authenticatable $user, OAuthClient $client): void
    {
        OAuthConsent::query()
            ->where('client_id', $client->id)
            ->where('user_type', $user::class)
            ->where('user_id', (string) $user->getAuthIdentifier())
            ->update(['revoked_at' => now()]);

        Event::dispatch(new OAuthConsentRevoked($user, $client));
    }
}
