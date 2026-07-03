# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.0.0 — 2026-07-04

- Initial release: multi-tenancy core for Yii3 — request tenant resolution, a
  request-scoped `CurrentTenant` context, and scoping primitives. No ORM
  auto-scoping magic by design.
- `Tenant` value object (validated id `/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/`,
  optional name, `TenantStatus`, free-form attributes) and `TenantStatus`
  (`Active`/`Suspended`).
- Resolvers `HeaderTenantResolver`, `SubdomainTenantResolver`,
  `PathTenantResolver`, `CompositeTenantResolver` — each validates the extracted
  key against `Tenant::isValidId()` and returns `null` on mismatch (request keys
  are untrusted input); subdomain resolution rejects nested labels and lookalike
  hosts.
- `CurrentTenant` reader interface (`get()`/`find()`/`isResolved()`) with the
  set-once `RequestCurrentTenant` holder (`override()` for console/tests).
- `TenantProvider` lookup interface + `ConfigTenantProvider`; the core does not
  bind `TenantProvider` — exactly one source (backend package or app) binds it.
- `TenantResolutionMiddleware` (PSR-15): publishes the tenant as the
  `CurrentTenant` service and the `Tenant::class` request attribute; unresolved
  → 404, suspended → 403, governed by `TenantPolicy` (`Reject`/`PassThrough`).
- `TenantScopedCache` — PSR-16 decorator prefixing keys with `t.{id}.`.
- `Exception\TenantNotResolvedException`.
- Yii3 `config-plugin` wiring (`config/di.php` + `config/params.php`).
