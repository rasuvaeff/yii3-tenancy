---
name: rasuvaeff-yii3-tenancy
description: >-
  Multi-tenancy core for Yii3: resolves the tenant of an incoming request
  (HeaderTenantResolver / SubdomainTenantResolver / PathTenantResolver /
  CompositeTenantResolver), publishes it via a request-scoped CurrentTenant
  holder (RequestCurrentTenant) and TenantResolutionMiddleware, with
  TenantProvider lookup and a TenantScopedCache PSR-16 decorator. Use when
  writing, reviewing or debugging tenant resolution, tenant context, or
  tenant data isolation in a project that has this package installed.
---

# rasuvaeff/yii3-tenancy

Multi-tenancy core for Yii3 (namespace `Rasuvaeff\Yii3Tenancy`): request tenant
resolution, request-scoped `CurrentTenant` context, and scoping primitives.
Deliberately no ORM auto-scoping magic.

## Safety rules — verify these on every change

1. **Tenant isolation is manual and mandatory.** This package does NOT
   auto-inject `tenant_id` into queries. Every tenant-owned query must filter
   by the current tenant explicitly — a missing filter is a cross-tenant data
   leak. Read the id from the context, never from raw request input:
   ```php
   $tenantId = $currentTenant->get()->id; // then bind it in EVERY query
   ```
2. **Tenant keys from requests are untrusted.** Resolvers validate against
   `Tenant::isValidId()` (`/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/`) and return
   `null` on mismatch. Never construct a `Tenant` from an unvalidated key.
3. **The core never binds `TenantProvider` in DI.** Exactly one source binds
   it: the backend package (`rasuvaeff/yii3-tenancy-db`) or the application —
   two binders break `yiisoft/config` with `Duplicate key`. Without a backend,
   bind `TenantProvider => ConfigTenantProvider` in the app's `config/common/di/`.
4. **Middleware order:** `TenantResolutionMiddleware` runs BEFORE
   authentication (the tenant determines the user store). No implicit default
   tenant: unresolved → 404 unless `TenantPolicy::PassThrough` is chosen.
5. **`RequestCurrentTenant` is set-once per request** — a second `set()`
   throws `LogicException`; use `override()` only in console/tests.
6. **`TenantScopedCache::clear()` wipes ALL tenants** (PSR-16 has no scoped
   clear). Keys are prefixed `t.{id}.` with dots, not colons — do not "fix"
   either behavior.

## Canonical usage

```php
use Rasuvaeff\Yii3Tenancy\CompositeTenantResolver;
use Rasuvaeff\Yii3Tenancy\HeaderTenantResolver;
use Rasuvaeff\Yii3Tenancy\SubdomainTenantResolver;
use Rasuvaeff\Yii3Tenancy\TenantPolicy;
use Rasuvaeff\Yii3Tenancy\TenantResolutionMiddleware;

$resolver = new CompositeTenantResolver(
    new HeaderTenantResolver(headerName: 'X-Tenant-Id'),
    new SubdomainTenantResolver(baseDomain: 'example.com'),
);

new TenantResolutionMiddleware(
    resolver: $resolver,
    provider: $provider,               // TenantProvider (backend or app binds it)
    currentTenant: $requestCurrentTenant,
    responseFactory: $psr17ResponseFactory,
    unresolvedPolicy: TenantPolicy::Reject,   // 404
    suspendedPolicy: TenantPolicy::Reject,    // 403
);
// success: publishes to RequestCurrentTenant + Tenant::class request attribute
```

## Full API

The complete API reference (value object, all resolvers, middleware, cache
decorator, DI params) lives in `vendor/rasuvaeff/yii3-tenancy/llms.txt` —
read it before guessing a method name.
