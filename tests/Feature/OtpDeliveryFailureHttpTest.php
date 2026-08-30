<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Illuminate\Contracts\Auth\Authenticatable;
use Override;
use Sopheak\JwtAuth\Contracts\FirstFactorUserResolver;
use Sopheak\JwtAuth\Contracts\OtpDeliveryAwareSender;
use Sopheak\JwtAuth\DTO\OtpDeliveryResult;
use Sopheak\JwtAuth\DTO\OtpDestination;
use Sopheak\JwtAuth\DTO\OtpDispatch;
use Sopheak\JwtAuth\Models\FirstFactorOtpCode;
use Sopheak\JwtAuth\Tests\TestCase;

/**
 * End-to-end proof that a failed delivery reaches the client as HTTP 502
 * without any route or middleware change.
 */
final class OtpDeliveryFailureHttpTest extends TestCase
{
    #[Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('sp-jwt-auth.first_factor_otp.enabled', true);
    }

    private function bindSender(OtpDeliveryResult $result): void
    {
        $this->app->instance(OtpDeliveryAwareSender::class, new readonly class ($result) implements OtpDeliveryAwareSender {
            public function __construct(private OtpDeliveryResult $result)
            {
            }

            public function deliver(OtpDispatch $dispatch): OtpDeliveryResult
            {
                return $this->result;
            }
        });

        $this->app->instance(FirstFactorUserResolver::class, new readonly class implements FirstFactorUserResolver {
            public function resolve(OtpDestination $destination, string $purpose): ?Authenticatable
            {
                return null;
            }

            public function create(OtpDestination $destination, string $purpose, ?string $requestedType): ?Authenticatable
            {
                return null;
            }
        });
    }

    public function test_failed_delivery_returns_502_and_issues_no_challenge(): void
    {
        $this->bindSender(OtpDeliveryResult::failure('connection_error', 'ref-1'));

        $response = $this->postJson('/otp/request', [
            'destination' => '+85512345678',
            'channel' => 'sms',
            'purpose' => 'login',
        ]);

        $response->assertStatus(502);
        $response->assertJson(['message' => 'connection_error']);

        self::assertSame(0, FirstFactorOtpCode::query()->count());
    }

    public function test_successful_delivery_still_returns_202(): void
    {
        $this->bindSender(OtpDeliveryResult::success('msg-1'));

        $response = $this->postJson('/otp/request', [
            'destination' => '+85512345678',
            'channel' => 'sms',
            'purpose' => 'login',
        ]);

        $response->assertStatus(202);

        self::assertSame(1, FirstFactorOtpCode::query()->count());
    }
}
