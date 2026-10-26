<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

final class ExternalIdentity extends Model
{
    protected $table = 'sp_jwt_external_identities';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'email_verified' => 'boolean',
        'raw_profile' => 'array',
        'last_resolved_at' => 'datetime',
    ];

    /**
     * Stored as JSON. With external_identities.encrypt_provider_tokens the JSON
     * holds `{"enc": "<ciphertext>"}`; rows written before encryption existed
     * (plain arrays) still read normally and are encrypted on their next write.
     *
     * @return Attribute<array<string, mixed>|null, array<string, mixed>|null>
     */
    protected function providerTokens(): Attribute
    {
        return Attribute::make(
            get: static function (mixed $value): ?array {
                if ($value === null) {
                    return null;
                }

                $decoded = is_string($value) ? json_decode($value, true) : $value;

                if (is_array($decoded) && count($decoded) === 1 && is_string($decoded['enc'] ?? null)) {
                    $decoded = json_decode(Crypt::decryptString($decoded['enc']), true);
                }

                return is_array($decoded) ? $decoded : null;
            },
            set: static function (mixed $value): ?string {
                if ($value === null) {
                    return null;
                }

                $json = json_encode($value, JSON_THROW_ON_ERROR);

                return (bool) config('sp-jwt-auth.external_identities.encrypt_provider_tokens', true)
                    ? json_encode(['enc' => Crypt::encryptString($json)], JSON_THROW_ON_ERROR)
                    : $json;
            },
        );
    }
}
