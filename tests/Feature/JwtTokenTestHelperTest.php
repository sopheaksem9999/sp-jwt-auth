<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Sopheak\JwtAuth\Testing\JwtTokenTestHelper;
use Sopheak\JwtAuth\Tests\TestCase;

final class JwtTokenTestHelperTest extends TestCase
{
    public function test_helper_creates_token_pair_with_company_claims(): void
    {
        $user = $this->createUser();

        $pair = JwtTokenTestHelper::createToken(
            user: $user,
            scopes: ['client'],
            claims: ['company_id' => 42],
            subjectType: 'company',
            subjectId: '42',
        );

        self::assertSame(['client'], $pair->accessTokenRecord->scopes);
        self::assertSame(42, $pair->accessTokenRecord->claim('company_id'));
        self::assertSame('company', $pair->accessTokenRecord->subject_type);
        self::assertSame('42', $pair->accessTokenRecord->subject_id);
        self::assertNotEmpty($pair->accessToken);
        self::assertNotEmpty($pair->refreshToken);
    }
}
