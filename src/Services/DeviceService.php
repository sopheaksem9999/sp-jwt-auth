<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Services;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Sopheak\JwtAuth\Contracts\AttestationVerifier;
use Sopheak\JwtAuth\DTO\DeviceRegistration;
use Sopheak\JwtAuth\DTO\DeviceRegistrationResult;
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\DTO\TokenPair;
use Sopheak\JwtAuth\Events\DeviceRegistered;
use Sopheak\JwtAuth\Events\DeviceRevoked;
use Sopheak\JwtAuth\Events\DeviceUpgradedToUser;
use Sopheak\JwtAuth\Models\Device;
use Sopheak\JwtAuth\Models\DeviceUser;
use Sopheak\JwtAuth\Models\JwtAccessToken;
use Sopheak\JwtAuth\Models\JwtRefreshToken;
use Sopheak\JwtAuth\Security\SecretHasher;
use Sopheak\JwtAuth\Support\DeviceConfig;

final readonly class DeviceService
{
    public function __construct(
        private JwtTokenService $jwt,
        private SecretHasher $hasher,
        private AttestationVerifier $attestation,
    ) {
    }

    public function register(DeviceRegistration $registration): DeviceRegistrationResult
    {
        $attested = $this->attest($registration, $registration->attestation, DeviceConfig::attestationRequired());
        $secret = bin2hex(random_bytes(32));
        $hash = $this->hasher->hash($secret);

        $device = Device::query()->create([
            'public_id' => (string) Str::uuid(),
            'platform' => $registration->platform,
            'name' => $registration->name,
            'client_version' => $registration->clientVersion,
            'secret_hash' => $hash['hash'],
            'hash_key_id' => $hash['hash_key_id'],
            'metadata' => $registration->metadata === [] ? null : $registration->metadata,
            'attested_at' => $attested ? now() : null,
            'last_seen_at' => now(),
        ]);

        $result = new DeviceRegistrationResult($device, $device->public_id . '.' . $secret, $this->issueTokenPair($device));

        Event::dispatch(new DeviceRegistered($device));

        return $result;
    }

    /**
     * Verify `{public_id}.{secret}` (timing-safe) and return the device. Unknown device,
     * wrong secret and revoked device all fail with the same message.
     *
     * @param array<string, mixed>|null $attestation
     */
    public function authenticate(string $credential, ?array $attestation = null): Device
    {
        $parts = explode('.', $credential, 2);
        $device = count($parts) === 2 && Str::isUuid($parts[0])
            ? Device::query()->where('public_id', $parts[0])->first()
            : null;

        if (! $device instanceof Device
            || $device->isRevoked()
            || ! $this->hasher->verify($parts[1], $device->secret_hash, $device->hash_key_id)
        ) {
            throw new AuthenticationException('Device credential is invalid.');
        }

        $attested = $this->attest($device, $attestation, DeviceConfig::attestationRequired() && DeviceConfig::attestationOnAuthenticate());
        $device->forceFill(array_filter([
            'last_seen_at' => now(),
            'attested_at' => $attested ? now() : null,
        ]))->save();

        return $device;
    }

    public function issueTokenPair(Device $device, ?TokenContext $context = null): TokenPair
    {
        return $this->jwt->issueTokenPair(
            $device,
            ($context ?? TokenContext::make()->scopes(DeviceConfig::defaultScopes()))->device($device->public_id, $device->name),
        );
    }

    /**
     * Link the user to the device and issue the user's tokens with the device attached.
     * With $endGuestSessions the device's own (guest) tokens are revoked; no other
     * session is ever touched. Moving guest data (a cart, say) is the app's job, in a
     * DeviceUpgradedToUser listener.
     */
    public function upgradeToUser(
        Device $device,
        Authenticatable $user,
        ?TokenContext $context = null,
        bool $endGuestSessions = true,
    ): TokenPair {
        return DB::transaction(function () use ($device, $user, $context, $endGuestSessions): TokenPair {
            if ($device->isRevoked()) {
                throw new AuthenticationException('Device is revoked.');
            }

            if ($endGuestSessions) {
                $this->jwt->revokeAllForUser($device);
            }

            $link = DeviceUser::query()->firstOrNew([
                'device_id' => $device->getKey(),
                'user_type' => $user::class,
                'user_id' => (string) $user->getAuthIdentifier(),
            ]);
            $link->id ??= (string) Str::uuid();
            $link->forceFill(['last_authenticated_at' => now(), 'revoked_at' => null])->save();

            $pair = $this->jwt->issueTokenPair(
                $user,
                ($context ?? TokenContext::make())->device($device->public_id, $device->name),
            );

            Event::dispatch(new DeviceUpgradedToUser($device, $user, $pair));

            return $pair;
        });
    }

    /** @return Collection<int, Device> */
    public function devicesForUser(Authenticatable $user): Collection
    {
        return Device::query()
            ->whereNull('revoked_at')
            ->whereIn('id', DeviceUser::query()
                ->where('user_type', $user::class)
                ->where('user_id', (string) $user->getAuthIdentifier())
                ->whereNull('revoked_at')
                ->select('device_id'))
            ->orderByDesc('last_seen_at')
            ->get();
    }

    /** Sign the user out of one device; the device itself stays registered. */
    public function revokeForUser(Authenticatable $user, string $publicId): void
    {
        $device = Device::query()->where('public_id', $publicId)->first();

        if (! $device instanceof Device) {
            return;
        }

        DeviceUser::query()
            ->where('device_id', $device->getKey())
            ->where('user_type', $user::class)
            ->where('user_id', (string) $user->getAuthIdentifier())
            ->update(['revoked_at' => now()]);

        $this->jwt->revokeDevice($user, $publicId);

        Event::dispatch(new DeviceRevoked($device, $user));
    }

    /** Revoke the device, its own tokens and every user's tokens issued on it. */
    public function revoke(string $publicId): void
    {
        $device = Device::query()->where('public_id', $publicId)->first();

        if (! $device instanceof Device) {
            return;
        }

        $device->forceFill(['revoked_at' => now()])->save();
        $this->jwt->revokeAllForUser($device);

        // Refresh tokens carry no device column, so go through the sessions on this device.
        JwtAccessToken::query()
            ->where('device_id', $publicId)
            ->where('user_type', '!=', Device::class)
            ->distinct()
            ->pluck('session_id')
            ->chunk(500)
            ->each(static function ($sessionIds): void {
                $ids = $sessionIds->all();
                JwtAccessToken::query()->whereIn('session_id', $ids)->whereNull('revoked_at')->update(['revoked_at' => now()]);
                JwtRefreshToken::query()->whereIn('session_id', $ids)->whereNull('revoked_at')->update(['revoked_at' => now()]);
            });

        DeviceUser::query()->where('device_id', $device->getKey())->whereNull('revoked_at')->update(['revoked_at' => now()]);

        Event::dispatch(new DeviceRevoked($device));
    }

    /**
     * @param array<string, mixed>|null $attestation
     *
     * @return bool whether the device was attested by a real verifier
     */
    private function attest(DeviceRegistration|Device $subject, ?array $attestation, bool $required): bool
    {
        if ($this->attestation instanceof NullAttestationVerifier) {
            if ($required) {
                // Fail closed: a required check must never pass without a verifier.
                throw new AuthenticationException('Device attestation is required but no verifier is bound.');
            }

            return false;
        }

        if ($attestation === null || $attestation === []) {
            if ($required) {
                throw new AuthenticationException('Device attestation is required.');
            }

            return false;
        }

        if (! $this->attestation->verify($subject, $attestation)) {
            throw new AuthenticationException('Device attestation failed.');
        }

        return true;
    }
}
