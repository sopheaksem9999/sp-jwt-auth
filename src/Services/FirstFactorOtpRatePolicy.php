<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Services;

use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Cache;
use Sopheak\JwtAuth\DTO\OtpDestination;
use Sopheak\JwtAuth\Security\SecretHasher;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

final readonly class FirstFactorOtpRatePolicy
{
    public function __construct(private SecretHasher $hasher) {}

    /**
     * Reserve all send allowances before creating or delivering a challenge.
     *
     * @return array<int, array{key: string, decay: int}>
     */
    public function reserveSend(OtpDestination $destination, ?string $ip): array
    {
        $destinationHash = $this->hasher->hash($destination->normalizedDestination)['hash'];
        $limits = [
            $this->limit('send_per_destination', 'request_per_destination', 5, 'send:destination:' . $destination->channel . ':' . $destinationHash),
        ];

        if ($ip !== null) {
            $limits[] = $this->limit('send_per_ip', 'request_per_ip', 20, 'send:ip:' . $ip);
        }

        if ($destination->channel === 'sms') {
            $project = $this->configuredLimit('sms_per_project', 'send:sms:project');

            if ($project !== null) {
                $limits[] = $project;
            }
        }

        return $this->withLocks(array_column($limits, 'key'), function (RateLimiter $limiter) use ($limits): array {
            foreach ($limits as $limit) {
                if ($limiter->tooManyAttempts($limit['key'], $limit['max'])) {
                    throw new TooManyRequestsHttpException(max(1, $limiter->availableIn($limit['key'])), 'Too many requests.');
                }
            }

            foreach ($limits as $limit) {
                $limiter->hit($limit['key'], $limit['decay']);
            }

            return array_map(static fn (array $limit): array => ['key' => $limit['key'], 'decay' => $limit['decay']], $limits);
        });
    }

    /** @param array<int, array{key: string, decay: int}> $reservation */
    public function releaseSend(array $reservation): void
    {
        $this->withLocks(array_column($reservation, 'key'), function (RateLimiter $limiter) use ($reservation): void {
            foreach ($reservation as $limit) {
                if ($limiter->attempts($limit['key']) > 0) {
                    $limiter->decrement($limit['key'], $limit['decay']);
                }
            }
        });
    }

    public function hitVerify(?string $ip): void
    {
        if ($ip === null) {
            return;
        }

        $limit = $this->limit('verify_per_ip', 'verify_per_ip', 30, 'verify:ip:' . $ip);

        $this->withLocks([$limit['key']], function (RateLimiter $limiter) use ($limit): void {
            if ($limiter->tooManyAttempts($limit['key'], $limit['max'])) {
                throw new TooManyRequestsHttpException(max(1, $limiter->availableIn($limit['key'])), 'Too many requests.');
            }

            $limiter->hit($limit['key'], $limit['decay']);
        });
    }

    /** @return array{key: string, max: int, decay: int} */
    private function limit(string $name, string $legacyName, int $legacyDefault, string $key): array
    {
        return $this->configuredLimit($name, $key) ?? [
            'key' => 'sp-jwt-ffotp:' . $key,
            'max' => (int) config('sp-jwt-auth.first_factor_otp.limits.' . $legacyName, $legacyDefault),
            'decay' => (int) config('sp-jwt-auth.first_factor_otp.limits.decay_minutes', 60) * 60,
        ];
    }

    /** @return array{key: string, max: int, decay: int}|null */
    private function configuredLimit(string $name, string $key): ?array
    {
        $policy = config('sp-jwt-auth.first_factor_otp.limits.' . $name);

        if (! is_array($policy)) {
            return null;
        }

        return [
            'key' => 'sp-jwt-ffotp:' . $key,
            'max' => max(1, (int) ($policy['max_attempts'] ?? 1)),
            'decay' => max(1, (int) ($policy['decay_seconds'] ?? 60)),
        ];
    }

    /**
     * Lock exactly the rate-limit keys the operation touches. Shared keys (per-IP,
     * project SMS quota) still serialize, but unrelated destinations no longer wait
     * on each other. Locks are taken in sorted order so concurrent callers can't deadlock.
     *
     * @param list<string> $keys
     */
    private function withLocks(array $keys, callable $callback): mixed
    {
        $store = (string) (config('cache.limiter') ?: config('cache.default'));
        $cache = Cache::store($store);
        $keys = array_values(array_unique($keys));
        sort($keys);
        $held = [];

        try {
            foreach ($keys as $key) {
                $lock = $cache->lock($key . ':lock', 10);
                $lock->block(5);
                $held[] = $lock;
            }

            return $callback(new RateLimiter($cache));
        } finally {
            foreach (array_reverse($held) as $lock) {
                $lock->release();
            }
        }
    }
}
