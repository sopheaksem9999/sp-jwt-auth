<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Sopheak\JwtAuth\Contracts\ResponseEnvelope as ResponseEnvelopeContract;

final class ResponseEnvelope
{
    public static function wrap(array $payload, string $mode): array
    {
        if ($mode === 'raw' || $mode === '') {
            return $payload;
        }

        if ($mode === 'laravel') {
            return ['data' => $payload];
        }

        $envelope = app($mode);

        if (! $envelope instanceof ResponseEnvelopeContract) {
            throw new RuntimeException(sprintf('Response envelope [%s] must implement %s.', $mode, ResponseEnvelopeContract::class));
        }

        return $envelope->wrap($payload);
    }

    public static function serializeUser(Authenticatable $user, ?string $resource = null): array
    {
        if ($resource !== null && $resource !== '') {
            $serializer = app($resource);

            if (! is_callable($serializer)) {
                throw new RuntimeException(sprintf('User resource [%s] must be invokable.', $resource));
            }

            $serialized = $serializer($user);

            if (! is_array($serialized)) {
                throw new RuntimeException(sprintf('User resource [%s] must return an array.', $resource));
            }

            return $serialized;
        }

        if ($user instanceof Model) {
            return $user->toArray();
        }

        return ['id' => $user->getAuthIdentifier()];
    }
}
