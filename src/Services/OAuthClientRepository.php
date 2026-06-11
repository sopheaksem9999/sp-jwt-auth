<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Services;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Sopheak\JwtAuth\DTO\OAuthClientData;
use Sopheak\JwtAuth\DTO\OAuthClientSecret;
use Sopheak\JwtAuth\Events\OAuthClientCreated;
use Sopheak\JwtAuth\Events\OAuthClientRevoked;
use Sopheak\JwtAuth\Events\OAuthClientSecretRotated;
use Sopheak\JwtAuth\Models\OAuthClient;
use Sopheak\JwtAuth\Security\SecretHasher;

final readonly class OAuthClientRepository
{
    public function __construct(private SecretHasher $hasher)
    {
    }

    public function createClient(OAuthClientData $data): OAuthClientSecret
    {
        $secret = $data->confidential ? bin2hex(random_bytes(32)) : null;
        $hash = $secret !== null ? $this->hasher->hash($secret) : ['hash' => null, 'hash_key_id' => null];

        $client = OAuthClient::query()->create([
            'id' => (string) Str::uuid(),
            'owner_type' => $data->ownerType,
            'owner_id' => $data->ownerId,
            'name' => $data->name,
            'confidential' => $data->confidential,
            'first_party' => $data->firstParty,
            'redirect_uris' => array_values($data->redirectUris),
            'allowed_grants' => array_values($data->allowedGrants),
            'allowed_scopes' => array_values($data->allowedScopes),
            'secret_hash' => $hash['hash'],
            'hash_key_id' => $hash['hash_key_id'],
            'secret_preview' => $secret !== null ? substr($secret, 0, 8) . '...' . substr($secret, -4) : null,
        ]);

        Event::dispatch(new OAuthClientCreated($client));

        return new OAuthClientSecret($client, $secret);
    }

    public function rotateSecret(string $clientId): OAuthClientSecret
    {
        $client = OAuthClient::query()->findOrFail($clientId);
        $secret = bin2hex(random_bytes(32));
        $hash = $this->hasher->hash($secret);
        $client->forceFill([
            'confidential' => true,
            'secret_hash' => $hash['hash'],
            'hash_key_id' => $hash['hash_key_id'],
            'secret_preview' => substr($secret, 0, 8) . '...' . substr($secret, -4),
        ])->save();

        Event::dispatch(new OAuthClientSecretRotated($client));

        return new OAuthClientSecret($client, $secret);
    }

    public function revokeClient(string $clientId): void
    {
        OAuthClient::query()->whereKey($clientId)->update(['revoked_at' => now()]);
        Event::dispatch(new OAuthClientRevoked($clientId));
    }

    public function findActiveClient(string $clientId): ?OAuthClient
    {
        $client = OAuthClient::query()->find($clientId);

        return $client instanceof OAuthClient && $client->revoked_at === null ? $client : null;
    }

    public function validateSecret(OAuthClient $client, ?string $secret): bool
    {
        if (! $client->confidential) {
            return true;
        }

        if ($secret === null || $secret === '' || $client->secret_hash === null) {
            return false;
        }

        return $this->hasher->verify($secret, $client->secret_hash, $client->hash_key_id);
    }
}
