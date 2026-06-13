<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Unit;

use Sopheak\JwtAuth\DTO\TokenSubject;
use Sopheak\JwtAuth\Models\JwtAccessToken;
use Sopheak\JwtAuth\Tests\TestCase;

final class JwtAccessTokenHelperTest extends TestCase
{
    public function test_token_exposes_common_saas_claims(): void
    {
        $token = new JwtAccessToken([
            'subject_type' => 'company',
            'subject_id' => '42',
            'claims' => [
                'company_id' => 42,
                'company_ids' => [42, 84],
                'tenant_id' => 'tenant-1',
                'tenant_ids' => ['tenant-1', 'tenant-2'],
                'impersonated' => true,
            ],
        ]);

        self::assertSame(42, $token->companyId());
        self::assertSame([42, 84], $token->companyIds());
        self::assertSame('tenant-1', $token->tenantId());
        self::assertSame(['tenant-1', 'tenant-2'], $token->tenantIds());
        self::assertTrue($token->isImpersonated());
        self::assertEquals(new TokenSubject('company', '42'), $token->subject());
    }

    public function test_token_helper_defaults_are_safe(): void
    {
        $token = new JwtAccessToken(['claims' => []]);

        self::assertNull($token->companyId());
        self::assertSame([], $token->companyIds());
        self::assertNull($token->tenantId());
        self::assertSame([], $token->tenantIds());
        self::assertFalse($token->isImpersonated());
    }
}
