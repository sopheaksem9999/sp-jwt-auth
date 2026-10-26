<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Sopheak\JwtAuth\DTO\TokenPair;
use Illuminate\Http\JsonResponse;
use Illuminate\Testing\TestResponse;
use Override;
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Models\JwtRefreshToken;
use Sopheak\JwtAuth\Services\JwtTokenService;
use Sopheak\JwtAuth\Support\TokenResponse;
use Sopheak\JwtAuth\Tests\TestCase;
use Symfony\Component\HttpFoundation\Cookie;

final class RefreshCookieTransportTest extends TestCase
{
    #[Override]
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('sp-jwt-auth.token_endpoints.enabled', true);
        $app['config']->set('sp-jwt-auth.token_endpoints.refresh_transport', 'cookie');
    }

    private function pair(): TokenPair
    {
        return app(JwtTokenService::class)->issueTokenPair($this->createUser(), TokenContext::make());
    }

    /** Cookies are read raw: the package routes sit outside the `web` group's EncryptCookies.
     * @param array<string, string> $cookies */
    private function withCsrf(array $cookies, string $csrf = 'csrf-value', ?string $header = 'csrf-value'): self
    {
        $this->withCredentials()->withUnencryptedCookies($cookies + ['sp_csrf' => $csrf]);

        return $header === null ? $this : $this->withHeader('X-SP-CSRF', $header);
    }

    private function cookie(TestResponse $response, string $name): ?Cookie
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $name) {
                return $cookie;
            }
        }

        return null;
    }

    public function test_refresh_reads_the_cookie_rotates_it_and_keeps_the_token_out_of_the_body(): void
    {
        $pair = $this->pair();

        $response = $this->withCsrf(['sp_refresh_token' => $pair->refreshToken])->postJson('/auth/token/refresh');

        $response->assertOk()->assertJsonStructure(['access_token', 'token_type', 'expires_in'])->assertJsonMissingPath('refresh_token');
        $refresh = $this->cookie($response, 'sp_refresh_token');
        self::assertNotNull($refresh);
        self::assertTrue($refresh->isHttpOnly());
        self::assertTrue($refresh->isSecure());
        self::assertSame('strict', $refresh->getSameSite());
        self::assertSame('/auth/token', $refresh->getPath());
        self::assertNotSame($pair->refreshToken, $refresh->getValue());
        self::assertStringContainsString($pair->accessTokenRecord->session_id, (string) JwtRefreshToken::query()->where('id', explode('.', $refresh->getValue())[0])->value('session_id'));

        $csrf = $this->cookie($response, 'sp_csrf');
        self::assertNotNull($csrf);
        self::assertFalse($csrf->isHttpOnly());
        self::assertSame('/', $csrf->getPath());
        self::assertNotNull(JwtRefreshToken::query()->find($pair->refreshTokenRecord->id)->revoked_at);
    }

    public function test_every_cookie_setting_response_also_exposes_the_csrf_value_in_a_header(): void
    {
        $pair = $this->pair();
        $refresh = $this->withCsrf(['sp_refresh_token' => $pair->refreshToken])->postJson('/auth/token/refresh');

        $refresh->assertOk()->assertHeader('X-SP-CSRF');
        self::assertSame($this->cookie($refresh, 'sp_csrf')->getValue(), $refresh->headers->get('X-SP-CSRF'));
        self::assertNotSame('csrf-value', $refresh->headers->get('X-SP-CSRF'));

        // A cross-site client has no readable cookie: it echoes the exposed value, which is the cookie it was sent.
        $next = $this->flushHeaders()->withCredentials()
            ->withUnencryptedCookies(['sp_refresh_token' => $this->cookie($refresh, 'sp_refresh_token')->getValue(), 'sp_csrf' => $refresh->headers->get('X-SP-CSRF')])
            ->withHeader('X-SP-CSRF', (string) $refresh->headers->get('X-SP-CSRF'))
            ->postJson('/auth/token/refresh');
        $next->assertOk();
        self::assertSame($this->cookie($next, 'sp_csrf')->getValue(), $next->headers->get('X-SP-CSRF'));

        $helper = TokenResponse::withRefreshCookie(new JsonResponse(["access_token" => "a"]), $pair);
        self::assertSame(collect($helper->headers->getCookies())->first(fn (Cookie $c): bool => $c->getName() === 'sp_csrf')->getValue(), $helper->headers->get('X-SP-CSRF'));
    }

    public function test_missing_or_wrong_csrf_header_is_403_and_does_not_rotate(): void
    {
        $pair = $this->pair();

        $this->withCsrf(['sp_refresh_token' => $pair->refreshToken], header: null)->postJson('/auth/token/refresh')->assertForbidden();
        $this->withCsrf(['sp_refresh_token' => $pair->refreshToken], header: 'wrong')->postJson('/auth/token/refresh')->assertForbidden();

        self::assertNull(JwtRefreshToken::query()->find($pair->refreshTokenRecord->id)->revoked_at);
    }

    public function test_cookie_mode_ignores_a_body_refresh_token_and_missing_cookie_is_401(): void
    {
        $pair = $this->pair();

        $this->postJson('/auth/token/refresh', ['refresh_token' => $pair->refreshToken])->assertUnauthorized();
    }

    public function test_reused_cookie_triggers_reuse_detection_and_clears_the_cookie(): void
    {
        $pair = $this->pair();
        $this->withCsrf(['sp_refresh_token' => $pair->refreshToken])->postJson('/auth/token/refresh')->assertOk();

        $response = $this->withCsrf(['sp_refresh_token' => $pair->refreshToken])->postJson('/auth/token/refresh');

        $response->assertUnauthorized();
        self::assertSame(1, $this->cookie($response, 'sp_refresh_token')?->getExpiresTime());
    }

    public function test_revoke_works_from_the_cookie_alone_and_clears_it_even_when_already_revoked(): void
    {
        $pair = $this->pair();

        $first = $this->withCsrf(['sp_refresh_token' => $pair->refreshToken])->postJson('/auth/token/revoke');
        $first->assertOk();
        self::assertSame(1, $this->cookie($first, 'sp_refresh_token')?->getExpiresTime());

        $again = $this->withCsrf(['sp_refresh_token' => $pair->refreshToken])->postJson('/auth/token/revoke');
        $again->assertOk();
        self::assertSame(1, $this->cookie($again, 'sp_refresh_token')?->getExpiresTime());

        $this->withCsrf(['sp_refresh_token' => $pair->refreshToken])->postJson('/auth/token/refresh')->assertUnauthorized();
    }

    public function test_revoke_from_cookie_needs_csrf_and_a_valid_secret(): void
    {
        $pair = $this->pair();

        $this->withCsrf(['sp_refresh_token' => $pair->refreshToken], header: 'wrong')->postJson('/auth/token/revoke')->assertForbidden();
        $this->withCsrf(['sp_refresh_token' => explode('.', $pair->refreshToken)[0] . '.' . str_repeat('0', 64)])->postJson('/auth/token/revoke')->assertUnauthorized();
        $this->postJson('/auth/token/revoke')->assertUnauthorized();
    }

    public function test_revoke_with_a_bearer_token_needs_no_csrf(): void
    {
        $pair = $this->pair();

        $this->withToken($pair->accessToken)->postJson('/auth/token/revoke')->assertOk();
    }

    public function test_csrf_can_be_disabled_and_same_site_none_forces_secure(): void
    {
        config()->set('sp-jwt-auth.token_endpoints.csrf.enabled', false);
        config()->set('sp-jwt-auth.token_endpoints.refresh_cookie.same_site', 'none');
        config()->set('sp-jwt-auth.token_endpoints.refresh_cookie.secure', false);

        $pair = $this->pair();

        $response = $this->withCredentials()->withUnencryptedCookies(['sp_refresh_token' => $pair->refreshToken])->postJson('/auth/token/refresh');

        $response->assertOk();

        $cookie = $this->cookie($response, 'sp_refresh_token');
        self::assertSame('none', $cookie->getSameSite());
        self::assertTrue($cookie->isSecure());
        self::assertNull($this->cookie($response, 'sp_csrf'));
    }

    public function test_both_mode_accepts_body_and_cookie_and_returns_the_token_in_both(): void
    {
        config()->set('sp-jwt-auth.token_endpoints.refresh_transport', 'both');
        $pair = $this->pair();

        $response = $this->postJson('/auth/token/refresh', ['refresh_token' => $pair->refreshToken]);

        $response->assertOk()->assertJsonStructure(['access_token', 'refresh_token']);
        self::assertSame($response->json('refresh_token'), $this->cookie($response, 'sp_refresh_token')?->getValue());
    }

    public function test_body_mode_is_unchanged(): void
    {
        config()->set('sp-jwt-auth.token_endpoints.refresh_transport', 'body');
        $pair = $this->pair();

        $this->postJson('/auth/token/refresh')->assertStatus(422);
        $response = $this->postJson('/auth/token/refresh', ['refresh_token' => $pair->refreshToken]);

        $response->assertOk()->assertJsonStructure(['access_token', 'refresh_token']);
        self::assertNull($this->cookie($response, 'sp_refresh_token'));
    }

    public function test_host_login_routes_can_use_with_refresh_cookie(): void
    {
        $pair = $this->pair();
        $response = TokenResponse::withRefreshCookie(
            new JsonResponse(['access_token' => 'a', 'data' => ['token' => ['refresh_token' => $pair->refreshToken]]]),
            $pair,
        );

        self::assertSame(['access_token' => 'a', 'data' => ['token' => []]], $response->getData(true));
        self::assertSame($pair->refreshToken, collect($response->headers->getCookies())->first(fn (Cookie $c): bool => $c->getName() === 'sp_refresh_token')->getValue());
    }
}
