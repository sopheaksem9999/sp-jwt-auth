<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\DTO;

final readonly class OtpDestination
{
    public function __construct(
        public string $channel,
        public string $normalizedDestination,
        public string $maskedDestination,
        public ?string $locale = null,
        public array $metadata = [],
    ) {
    }

    public static function email(string $email, ?string $locale = null, array $metadata = []): self
    {
        $normalized = strtolower(trim($email));
        [$name, $domain] = array_pad(explode('@', $normalized, 2), 2, '');
        $masked = ($name[0] ?? '*') . '***@' . $domain;

        return new self('email', $normalized, $masked, $locale, $metadata);
    }

    public static function phone(string $phone, string $channel = 'sms', ?string $locale = null, array $metadata = []): self
    {
        $normalized = preg_replace('/\s+/', '', $phone) ?: $phone;
        $masked = substr($normalized, 0, 4) . '******' . substr($normalized, -3);

        return new self($channel, $normalized, $masked, $locale, $metadata);
    }
}
