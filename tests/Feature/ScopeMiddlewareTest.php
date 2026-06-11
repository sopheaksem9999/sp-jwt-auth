<?php

declare(strict_types=1);

namespace Sopheak\JwtAuth\Tests\Feature;

use Illuminate\Support\Facades\Route;
use Sopheak\JwtAuth\DTO\TokenContext;
use Sopheak\JwtAuth\Services\JwtTokenService;
use Sopheak\JwtAuth\Tests\TestCase;

final class ScopeMiddlewareTest extends TestCase
{
    public function test_required_scope_allows_matching_scope(): void
    {
        Route::middleware(['auth:api', 'sp.jwt.scope:invoices.read'])
            ->get('/scoped', fn (): array => ['ok' => true]);

        $pair = app(JwtTokenService::class)->issueTokenPair(
            $this->createUser(),
            TokenContext::make()->scopes(['invoices.read']),
        );

        $this->withToken($pair->accessToken)->getJson('/scoped')->assertOk();
    }

    public function test_required_scope_rejects_missing_scope(): void
    {
        Route::middleware(['auth:api', 'sp.jwt.scope:invoices.write'])
            ->get('/scope-denied', fn (): array => ['ok' => true]);

        $pair = app(JwtTokenService::class)->issueTokenPair(
            $this->createUser(),
            TokenContext::make()->scopes(['invoices.read']),
        );

        $this->withToken($pair->accessToken)->getJson('/scope-denied')->assertForbidden();
    }
}
