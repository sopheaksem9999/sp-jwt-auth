<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Services;

use Carbon\CarbonInterface;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Sopheak\JwtAuth\DTO\ApiKeyContext;
use Sopheak\JwtAuth\DTO\ApiKeyPlaintextResult;
use Sopheak\JwtAuth\DTO\ApiKeyPrincipal;
use Sopheak\JwtAuth\Events\ApiKeyCreated;
use Sopheak\JwtAuth\Events\ApiKeyRevoked;
use Sopheak\JwtAuth\Events\ApiKeyRotated;
use Sopheak\JwtAuth\Events\ApiKeyUsed;
use Sopheak\JwtAuth\Models\ApiKey;
use Sopheak\JwtAuth\Security\SecretHasher;

final readonly class ApiKeyService
{
    public function __construct(private SecretHasher $hasher)
    {
    }

    public function createApiKey(ApiKeyContext $context): ApiKeyPlaintextResult
    {
        $prefix = (string) config('sp-jwt-auth.api_keys.prefix', 'spak');
        $environment = (string) config('sp-jwt-auth.api_keys.environment', 'live');
        $publicId = Str::lower(Str::random((int) config('sp-jwt-auth.api_keys.public_id_length', 16)));
        $secret = bin2hex(random_bytes((int) config('sp-jwt-auth.api_keys.secret_bytes', 32)));
        $hash = $this->hasher->hash($secret);
        $plaintext = sprintf('%s_%s_%s.%s', $prefix, $environment, $publicId, $secret);
        $expiresAt = $context->expiresAt;

        if (!$expiresAt instanceof CarbonInterface && config('sp-jwt-auth.api_keys.default_ttl_days') !== null) {
            $expiresAt = now()->addDays((int) config('sp-jwt-auth.api_keys.default_ttl_days'));
        }

        $apiKey = ApiKey::query()->create([
            'id' => (string) Str::uuid(),
            'owner_type' => $context->ownerType,
            'owner_id' => $context->ownerId,
            'created_by_type' => $context->createdByType,
            'created_by_id' => $context->createdById,
            'name' => $context->name,
            'prefix' => $prefix,
            'environment' => $environment,
            'public_id' => $publicId,
            'secret_hash' => $hash['hash'],
            'hash_key_id' => $hash['hash_key_id'],
            'key_preview' => substr($plaintext, 0, 18) . '...' . substr($plaintext, -4),
            'scopes' => $context->scopes,
            'claims' => $context->claims,
            'allowed_ips' => $context->allowedIps,
            'expires_at' => $expiresAt,
        ]);

        Event::dispatch(new ApiKeyCreated($apiKey));

        return new ApiKeyPlaintextResult($plaintext, $apiKey);
    }

    public function validateApiKey(string $plaintextKey, ?string $ipAddress = null): ApiKeyPrincipal
    {
        [$prefix, $environment, $publicId, $secret] = $this->parse($plaintextKey);
        $apiKey = ApiKey::query()
            ->where('prefix', $prefix)
            ->where('environment', $environment)
            ->where('public_id', $publicId)
            ->first();

        if (! $apiKey instanceof ApiKey || $apiKey->revoked_at !== null || ($apiKey->expires_at !== null && $apiKey->expires_at->isPast())) {
            throw new AuthenticationException('API key is invalid.');
        }

        if (! $this->hasher->verify($secret, $apiKey->secret_hash, $apiKey->hash_key_id)) {
            throw new AuthenticationException('API key is invalid.');
        }

        if ($apiKey->allowed_ips !== null && $ipAddress !== null && ! in_array($ipAddress, $apiKey->allowed_ips, true)) {
            throw new AuthenticationException('API key is invalid.');
        }

        $apiKey->forceFill(['last_used_at' => now()])->save();
        Event::dispatch(new ApiKeyUsed($apiKey));

        return new ApiKeyPrincipal($apiKey->id, $apiKey->owner_type, $apiKey->owner_id, $apiKey->scopes ?? [], $apiKey->claims ?? [], $apiKey->expires_at);
    }

    public function revokeApiKey(string $apiKeyId): void
    {
        ApiKey::query()->whereKey($apiKeyId)->update(['revoked_at' => now()]);
        Event::dispatch(new ApiKeyRevoked($apiKeyId));
    }

    public function revokeApiKeysForOwner(string $ownerType, string $ownerId): void
    {
        ApiKey::query()
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public function rotateApiKey(string $apiKeyId): ApiKeyPlaintextResult
    {
        $apiKey = ApiKey::query()->findOrFail($apiKeyId);
        $this->revokeApiKey($apiKeyId);

        $result = $this->createApiKey(new ApiKeyContext(
            ownerType: $apiKey->owner_type,
            ownerId: $apiKey->owner_id,
            name: $apiKey->name,
            scopes: $apiKey->scopes ?? [],
            claims: $apiKey->claims ?? [],
            expiresAt: $apiKey->expires_at,
            allowedIps: $apiKey->allowed_ips,
            createdByType: $apiKey->created_by_type,
            createdById: $apiKey->created_by_id,
        ));
        Event::dispatch(new ApiKeyRotated($apiKeyId, $result->apiKey));

        return $result;
    }

    /**
     * @return array<int, string>
     */
    private function parse(string $plaintextKey): array
    {
        [$head, $secret] = array_pad(explode('.', $plaintextKey, 2), 2, '');
        $parts = explode('_', $head, 3);

        if (count($parts) !== 3 || $secret === '') {
            throw new AuthenticationException('API key is invalid.');
        }

        return [$parts[0], $parts[1], $parts[2], $secret];
    }
}
