<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Services\Concerns;

use Sopheak\JwtAuth\DTO\TokenContext;

trait SerializesTokenContext
{
    /**
     * @return array<string, string|mixed[]|null>
     */
    private function contextToArray(TokenContext $context): array
    {
        return [
            'scopes' => $context->scopes,
            'claims' => $context->claims,
            'subject_type' => $context->subjectType,
            'subject_id' => $context->subjectId,
            'audience' => $context->audience,
            'device_id' => $context->deviceId,
            'device_name' => $context->deviceName,
            'session_id' => $context->sessionId,
            'metadata' => $context->metadata,
        ];
    }

    /**
     * @param array<string, mixed> $context
     */
    private function contextFromArray(array $context): TokenContext
    {
        return new TokenContext(
            scopes: $context['scopes'] ?? [],
            claims: $context['claims'] ?? [],
            subjectType: $context['subject_type'] ?? null,
            subjectId: $context['subject_id'] ?? null,
            audience: $context['audience'] ?? null,
            deviceId: $context['device_id'] ?? null,
            deviceName: $context['device_name'] ?? null,
            sessionId: $context['session_id'] ?? null,
            metadata: $context['metadata'] ?? [],
        );
    }
}
