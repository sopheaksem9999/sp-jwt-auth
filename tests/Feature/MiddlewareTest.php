<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Sopheak\JwtAuth\DTO\ApiKeyPrincipal;
use Sopheak\JwtAuth\DTO\OAuthPrincipal;
use Sopheak\JwtAuth\Http\Middleware\AuthenticateApiKey;
use Sopheak\JwtAuth\Http\Middleware\AuthenticateOAuthToken;
use Sopheak\JwtAuth\Http\Middleware\RequireAnyApiKeyScope;
use Sopheak\JwtAuth\Http\Middleware\RequireApiKeyScope;
use Sopheak\JwtAuth\Http\Middleware\RequireOAuthClient;
use Sopheak\JwtAuth\Http\Middleware\RequireOAuthScope;
use Sopheak\JwtAuth\Services\ApiKeyService;
use Sopheak\JwtAuth\Services\OAuthServerService;
use Sopheak\JwtAuth\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class MiddlewareTest extends TestCase
{
    public function test_authenticate_api_key_middleware_requires_bearer_token(): void
    {
        $middleware = new AuthenticateApiKey(app(ApiKeyService::class));
        $request = Request::create('/test', 'GET');

        $this->expectException(HttpException::class);
        $middleware->handle($request, static fn (): ResponseFactory|Response => response('OK'));
    }

    public function test_authenticate_oauth_token_middleware_requires_bearer_token(): void
    {
        $middleware = new AuthenticateOAuthToken(app(OAuthServerService::class));
        $request = Request::create('/test', 'GET');

        $this->expectException(HttpException::class);
        $middleware->handle($request, static fn (): ResponseFactory|Response => response('OK'));
    }

    public function test_require_api_key_scope_middleware(): void
    {
        $middleware = new RequireApiKeyScope();
        $request = Request::create('/test', 'GET');

        // Missing principal -> 403
        try {
            $middleware->handle($request, static fn (): ResponseFactory|Response => response('OK'), 'orders:read');
            self::fail('Expected 403 HttpException');
        } catch (HttpException $httpException) {
            self::assertSame(403, $httpException->getStatusCode());
        }

        // Principal missing required scope -> 403
        $principal = new ApiKeyPrincipal(
            apiKeyId: 'key1',
            ownerType: 'user',
            ownerId: '1',
            scopes: ['orders:read'],
            claims: [],
            expiresAt: null,
        );
        $request->attributes->set('sp_api_key_principal', $principal);

        try {
            $middleware->handle($request, static fn (): ResponseFactory|Response => response('OK'), 'orders:read', 'orders:write');
            self::fail('Expected 403 HttpException');
        } catch (HttpException $httpException) {
            self::assertSame(403, $httpException->getStatusCode());
        }

        // Principal has all required scopes -> pass
        $response = $middleware->handle($request, static fn (): ResponseFactory|Response => response('OK'), 'orders:read');
        self::assertSame('OK', $response->getContent());
    }

    public function test_require_any_api_key_scope_middleware(): void
    {
        $middleware = new RequireAnyApiKeyScope();
        $request = Request::create('/test', 'GET');

        $principal = new ApiKeyPrincipal(
            apiKeyId: 'key1',
            ownerType: 'user',
            ownerId: '1',
            scopes: ['orders:read'],
            claims: [],
            expiresAt: null,
        );
        $request->attributes->set('sp_api_key_principal', $principal);

        // One matching scope -> pass
        $response = $middleware->handle($request, static fn (): ResponseFactory|Response => response('OK'), 'users:write', 'orders:read');
        self::assertSame('OK', $response->getContent());

        // No matching scope -> 403
        $this->expectException(HttpException::class);
        $middleware->handle($request, static fn (): ResponseFactory|Response => response('OK'), 'users:write');
    }

    public function test_require_oauth_client_middleware(): void
    {
        $middleware = new RequireOAuthClient();
        $request = Request::create('/test', 'GET');

        $principal = new OAuthPrincipal(
            clientId: 'client_abc',
            userType: 'user',
            userId: '1',
            grantType: 'client_credentials',
            scopes: ['read'],
            claims: [],
            tokenId: 'tok_1',
            expiresAt: Carbon::now()->addHour(),
        );

        $request->attributes->set('sp_oauth_principal', $principal);

        // Allowed client -> pass
        $response = $middleware->handle($request, static fn (): ResponseFactory|Response => response('OK'), 'client_abc', 'client_xyz');
        self::assertSame('OK', $response->getContent());

        // Disallowed client -> 403
        $this->expectException(HttpException::class);
        $middleware->handle($request, static fn (): ResponseFactory|Response => response('OK'), 'other_client');
    }

    public function test_require_oauth_scope_middleware(): void
    {
        $middleware = new RequireOAuthScope();
        $request = Request::create('/test', 'GET');

        $principal = new OAuthPrincipal(
            clientId: 'client_abc',
            userType: 'user',
            userId: '1',
            grantType: 'authorization_code',
            scopes: ['profile', 'email'],
            claims: [],
            tokenId: 'tok_1',
            expiresAt: Carbon::now()->addHour(),
        );

        $request->attributes->set('sp_oauth_principal', $principal);

        // Required scope present -> pass
        $response = $middleware->handle($request, static fn (): ResponseFactory|Response => response('OK'), 'profile');
        self::assertSame('OK', $response->getContent());

        // Missing scope -> 403
        $this->expectException(HttpException::class);
        $middleware->handle($request, static fn (): ResponseFactory|Response => response('OK'), 'admin');
    }
}
