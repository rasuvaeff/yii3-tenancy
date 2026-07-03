# AGENTS.md — yii3-tenancy

Guidance for AI agents working on this package. Read before changing code.

## What this is

Multi-tenancy core for Yii3 (namespace `Rasuvaeff\Yii3Tenancy\`): resolves the
tenant of an incoming request (header/subdomain/path), publishes it via a
request-scoped `CurrentTenant` holder and the `Tenant::class` request
attribute, and provides scoping primitives (`TenantScopedCache`). The
swappable storage axis is `TenantProvider` — bound by a backend package
(planned `yii3-tenancy-db`) or the application, never by this core.

Public API: `Tenant`, `TenantStatus`, `TenantPolicy`, `CurrentTenant` +
`RequestCurrentTenant`, `TenantResolver` + `Header`/`Subdomain`/`Path`/
`Composite` resolvers, `TenantProvider` + `ConfigTenantProvider`,
`TenantResolutionMiddleware`, `TenantScopedCache`,
`Exception\TenantNotResolvedException`.

## Golden rules

1. **Verification is mandatory.** Never claim "done" without a fresh green
   `composer build`. "Should work" does not count.
2. **No suppressions.** No `@psalm-suppress`, no baseline. Fix the root cause.
3. **Tenant keys from requests are untrusted input.** Every resolver must
   validate the extracted candidate with `Tenant::isValidId()` and return
   `null` on mismatch; never construct a `Tenant` from an unvalidated key and
   never resolve against the raw `Host` without the configured base domain.
4. **Preserve the public contract.** Update README + tests with any API change.

## Commands

No PHP/Composer on the host — run in Docker via the `composer:2` image.

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
docker run --rm -v "$PWD":/app -w /app composer:2 composer cs:fix
docker run --rm -v "$PWD":/app -w /app composer:2 composer psalm
docker run --rm -v "$PWD":/app -w /app composer:2 composer test
docker run --rm -v "$PWD":/app -w /app composer:2 composer release-check
```

## Invariants & gotchas

- **No ORM auto-scoping — ever.** This core ships resolution + context +
  primitives. Automatic `tenant_id` injection into yiisoft/db queries is out
  of scope by design decision (see `yii3-package-plans/yii3-tenancy.md`);
  integration happens through explicit recipes (mandatory filters,
  specifications) documented in README.
- **Core must not bind `TenantProvider` in `config/di.php`.** Exactly one
  source binds it (backend package or app) — otherwise `yiisoft/config` fails
  with `Duplicate key`. `ConfigWiringTest::tenantProviderIsNotBoundByCore`
  guards this.
- **No implicit default tenant.** Unresolved requests are rejected (404)
  unless the app explicitly opts into `passthrough`.
- `RequestCurrentTenant` is set-once per request (`set()` twice throws);
  `override()` exists for console/tests only. Readers depend on the
  `CurrentTenant` interface, the middleware on the concrete class —
  `config/di.php` aliases them to the same singleton.
- Middleware order: tenant resolution runs BEFORE auth (tenant determines the
  user store). Documented in README — keep it that way.
- `TenantScopedCache` prefixes with `t.{id}.` — dots, not colons (PSR-16
  reserves `:`). `clear()` intentionally delegates and wipes ALL tenants;
  PSR-16 has no prefix-scoped clear. Do not "fix" it into a per-tenant clear.
- `SubdomainTenantResolver` rejects nested labels (`a.b.example.com`) and
  suffix lookalikes (`acmeexample.com`); base domain is normalized (lowercase,
  leading dot stripped) in the constructor.
- `config/di.php` is NOT covered by cs/psalm (src-only) — it is exercised by
  `ConfigWiringTest` and must stay in sync with `config/params.php`.
- Property-based tests use `rasuvaeff/property-testing` v2: generators are
  `<testMethod>Generators(): array` companions; `Gen::map(inner, closure)`
  argument order, `Gen::oneOf(...values)` is variadic. CI needs `mbstring`.
- Code: `declare(strict_types=1)`, `final readonly class` (except
  `RequestCurrentTenant` — mutable holder), `#[\Override]`, explicit types.
- `examples/` is part of the public contract: keep scripts runnable and update
  `examples/README.md` when example usage changes.
- **CI workflows are SHA-pinned.** Every `uses:` in `.github/workflows/*.yml`
  references a 40-char commit SHA with a `# vN` trailing comment. Never revert
  to floating `@vN` tags. Updates go through Dependabot. Workflows carry
  `permissions: { contents: read }` and `persist-credentials: false` on every
  checkout. Verify with `zizmor --persona=auditor .github/`.

## When you finish

- Update `README.md` (and `examples/` if usage changed); update `CHANGELOG.md`
  when releasing.
- Re-run `composer build`; if the change affects public API or release safety,
  also run `make release-check`. Paste the output.
