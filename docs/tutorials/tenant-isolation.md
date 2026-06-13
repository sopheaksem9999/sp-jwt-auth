---
title: "Tenant Isolation"
description: "Use subject context and claims to isolate multi-company data."
---

# Tenant Isolation

The package stores subject and claim data in tokens but does not enforce tenant policy. The application owns tenant authorization rules.

## Token Context with Tenant

Attach tenant or company information when issuing tokens. Use the built-in company helpers for the primary active context and values your middleware/controllers need to read:

```php
$pair = app(JwtTokenService::class)->issueTokenPair(
    $user,
    TokenContext::make()
        ->companyId($activeCompanyId)
        ->companyIds($allowedCompanyIds)
        ->impersonated($isImpersonating)
        ->scopes(['invoices.read', 'invoices.write'])
);
```

`companyId()` sets `subject_type=company`, `subject_id`, and `claims.company_id`. `companyIds()` and `impersonated()` write app-readable claims. Claims are persisted in the `claims` JSON column and embedded into the signed JWT payload.

## Access Tenant Data in Middleware

Read the tenant/company claim from the authenticated token. This does not require adding a custom `company_id` column to the package table.

```php
use Sopheak\JwtAuth\Traits\HasJwtTokens;

Route::middleware('auth:api')->group(function () {
    Route::get('/invoices', function (Request $request) {
        $token = $request->user()->token();
        $companyId = $token?->companyId();

        // App-owned tenant scoping
        return Invoice::where('company_id', $companyId)->paginate();
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
            $companyId = $context->claims['company_id'] ?? null;

            if ($companyId === null) {
                throw new \RuntimeException('Company ID is required.');
            }

            if (! $user->belongsToCompany($companyId)) {
                throw new \RuntimeException('User does not belong to this company.');
            }
        }
    };
});
```

## Multi-Tenant API Key

API keys also support tenant context:

```php
$key = app(ApiKeyService::class)->createApiKey(ApiKeyContext::forCompany(
    companyId: $activeCompanyId,
    name: 'ERP sync',
    scopes: ['invoices.read'],
));
```

## Subject vs Claims

| Concept | When to use |
|---|---|
| `subject(type, id)` | Primary active entity the token represents (tenant, company, workspace) |
| `claims([...])` | Additional context passed through to your app logic |

The package stores both but does not enforce either. Your middleware and controllers own tenant isolation.
