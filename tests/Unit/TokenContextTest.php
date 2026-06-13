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

    public function test_context_builds_company_claims_subject_and_impersonation(): void
    {
        $context = TokenContext::make()
            ->companyId(42)
            ->companyIds([42, '84'])
            ->impersonated();

        self::assertEquals(new TokenSubject('company', '42'), $context->subjectValue());
        self::assertSame(42, $context->claims['company_id']);
        self::assertSame([42, '84'], $context->claims['company_ids']);
        self::assertTrue($context->claims['impersonated']);
    }

    public function test_context_builds_tenant_claims_and_metadata(): void
    {
        $context = TokenContext::make()
            ->tenantId('tenant-1')
            ->tenantIds(['tenant-1', 'tenant-2'])
            ->claim('role', 'owner')
            ->metadata(['login_ip' => '127.0.0.1']);

        self::assertEquals(new TokenSubject('tenant', 'tenant-1'), $context->subjectValue());
        self::assertSame('tenant-1', $context->claims['tenant_id']);
        self::assertSame(['tenant-1', 'tenant-2'], $context->claims['tenant_ids']);
        self::assertSame('owner', $context->claims['role']);
        self::assertSame(['login_ip' => '127.0.0.1'], $context->metadata);
    }

    public function test_reserved_claim_names_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TokenContext::make()->claims(['exp' => 123]);
    }
}
