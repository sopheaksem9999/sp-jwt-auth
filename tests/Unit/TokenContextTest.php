<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\DTO\TokenSubject;

final class TokenContextTest extends TestCase
{
    public function test_context_builds_subject_scopes_and_claims(): void
    {
        $context = TokenContext::make()
            ->sessionId('session-1')
            ->subject('tenant', '42')
            ->scopes(['client', 'tenant:42'])
            ->claims(['tenant_id' => 42])
            ->replaceClaim('tenant_role', 'owner');

        self::assertSame('session-1', $context->sessionId);
        self::assertEquals(new TokenSubject('tenant', '42'), $context->subjectValue());
        self::assertSame(['client', 'tenant:42'], $context->scopes);
        self::assertSame('owner', $context->claims['tenant_role']);
    }

    public function test_reserved_claim_names_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TokenContext::make()->claims(['exp' => 123]);
    }
}
