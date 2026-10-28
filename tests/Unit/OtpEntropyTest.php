<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Sopheak\JwtAuth\Services\FirstFactorOtpBroker;
use Sopheak\JwtAuth\Services\OtpChallengeBroker;

final class OtpEntropyTest extends TestCase
{
    public function test_otp_generators_produce_fixed_length_string_with_leading_zeroes(): void
    {
        $firstFactorInstance = (new ReflectionClass(FirstFactorOtpBroker::class))->newInstanceWithoutConstructor();
        $firstFactorMethod = new ReflectionMethod(FirstFactorOtpBroker::class, 'generateCode');

        $mfaInstance = (new ReflectionClass(OtpChallengeBroker::class))->newInstanceWithoutConstructor();
        $mfaMethod = new ReflectionMethod(OtpChallengeBroker::class, 'generateCode');

        // Test with 6 digits
        for ($i = 0; $i < 50; $i++) {
            $code1 = $firstFactorMethod->invoke($firstFactorInstance, 6);
            self::assertSame(6, strlen($code1));
            self::assertMatchesRegularExpression('/^[0-9]{6}$/', $code1);

            $code2 = $mfaMethod->invoke($mfaInstance, 6);
            self::assertSame(6, strlen($code2));
            self::assertMatchesRegularExpression('/^[0-9]{6}$/', $code2);
        }
    }
}
