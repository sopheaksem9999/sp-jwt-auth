---
title: "Tenant Isolation"
description: "Use subject context and claims to isolate multi-company data."
---

# Tenant Isolation

The package stores subject and claim data in tokens but does not enforce tenant policy. The application owns tenant authorization rules.

## Token Context with Tenant

Attach tenant information when issuing tokens:

```php
$pair = app(JwtTokenService::class)->issueTokenPair(
    $user,
    TokenContext::make()
        ->subject('tenant', (string) $user->tenant_id)
        ->scopes(['invoices.read', 'invoices.write'])
        ->claims(['tenant_id' => $user->tenant_id]),
);
```

## Access Tenant Data in Middleware

Read the tenant claim from the authenticated token:

```php
use Sopheak\JwtAuth\Traits\HasJwtTokens;

Route::middleware('auth:api')->group(function () {
    Route::get('/invoices', function (Request $request) {
        $token = $request->user()->token();
        $tenantId = $token?->claims['tenant_id'] ?? null;

        // App-owned tenant scoping
        return Invoice::where('tenant_id', $tenantId)->paginate();
    });
});
```

## Token Validation Hooks

Use `TokenContextValidator` to reject token issuance for invalid tenant contexts:

```php
use Sopheak\JwtAuth\Contracts\TokenContextValidator;
use Sopheak\JwtAuth\DTO\TokenContext;

$this->app->bind(TokenContextValidator::class, function (): TokenContextValidator {
    return new class implements TokenContextValidator
    {
        public function validate(Authenticatable $user, TokenContext $context): void
        {
            $tenantId = $context->claims['tenant_id'] ?? null;

            if ($tenantId === null) {
                throw new \RuntimeException('Tenant ID is required.');
            }

            if (! $user->belongsToTenant($tenantId)) {
                throw new \RuntimeException('User does not belong to this tenant.');
            }
        }
    };
});
```

## Multi-Tenant API Key

API keys also support tenant context:

```php
$key = app(ApiKeyService::class)->createApiKey(new ApiKeyContext(
    ownerType: 'tenant',
    ownerId: (string) $user->tenant_id,
    name: 'ERP sync',
    scopes: ['invoices.read'],
    claims: ['tenant_id' => $user->tenant_id],
));
```

## Subject vs Claims

| Concept | When to use |
|---|---|
| `subject(type, id)` | Primary entity the token represents (tenant, company, workspace) |
| `claims([...])` | Additional context passed through to your app logic |

The package stores both but does not enforce either. Your middleware and controllers own tenant isolation.
