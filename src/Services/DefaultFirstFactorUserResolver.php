<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Sopheak\JwtAuth\Contracts\FirstFactorUserResolver;
use Sopheak\JwtAuth\DTO\OtpDestination;

final class DefaultFirstFactorUserResolver implements FirstFactorUserResolver
{
    public function resolve(OtpDestination $destination, string $purpose): ?Authenticatable
    {
        $model = $this->modelClass();
        $column = $this->columnFor($destination->channel);

        if ($column === null || ! Schema::hasColumn((new $model)->getTable(), $column)) {
            return null;
        }

        /** @var Authenticatable|null */
        return $model::query()->where($column, $destination->normalizedDestination)->first();
    }

    public function create(OtpDestination $destination, string $purpose, ?string $requestedType): ?Authenticatable
    {
        $model = $this->modelClass();
        $column = $this->columnFor($destination->channel);

        if ($column === null) {
            return null;
        }

        try {
            $user = new $model();
            $user->forceFill([$column => $destination->normalizedDestination]);

            if ($requestedType !== null) {
                $user->forceFill([$this->requestedTypeColumn() => $requestedType]);
            }

            $user->save();
        } catch (UniqueConstraintViolationException) {
            return $this->resolve($destination, $purpose);
        }

        return $user;
    }

    /** @return class-string */
    private function modelClass(): string
    {
        $model = config('sp-jwt-auth.first_factor_otp.user_model');

        if (! is_string($model) || $model === '') {
            throw new RuntimeException('sp-jwt-auth.first_factor_otp.user_model is not configured.');
        }

        if (! class_exists($model)) {
            throw new RuntimeException(sprintf('Configured user model [%s] does not exist.', $model));
        }

        return $model;
    }

    private function columnFor(string $channel): ?string
    {
        $key = $channel === 'email' ? 'email' : 'phone';
        $columns = config('sp-jwt-auth.first_factor_otp.destination_columns', []);

        $column = $columns[$key] ?? null;

        return is_string($column) && $column !== '' ? $column : null;
    }

    private function requestedTypeColumn(): string
    {
        return (string) config('sp-jwt-auth.first_factor_otp.requested_type_column', 'type');
    }
}
