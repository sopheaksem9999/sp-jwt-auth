<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Sopheak\JwtAuth\Support\OtpMessageFormatter;

final class OtpMessageFormatterTest extends TestCase
{
    public function test_formats_template_with_code_and_ttl(): void
    {
        $message = OtpMessageFormatter::format('Your {app} code is {code}. Valid {ttl} min.', [
            'code' => '123456',
            'ttl' => 5,
            'app' => 'MyApp',
        ]);

        $this->assertSame('Your MyApp code is 123456. Valid 5 min.', $message);
    }

    public function test_leaves_unknown_placeholders_untouched(): void
    {
        $message = OtpMessageFormatter::format('Code {code} {unknown}.', ['code' => '42']);

        $this->assertSame('Code 42 {unknown}.', $message);
    }
}
