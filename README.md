# rasuvaeff/yii3-tenancy

[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-tenancy?label=stable&sort_semver=1)](https://packagist.org/packages/rasuvaeff/yii3-tenancy)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-tenancy)](https://packagist.org/packages/rasuvaeff/yii3-tenancy)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-tenancy/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-tenancy/actions)
[![Static analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-tenancy/static-analysis.yml?branch=master&label=static%20analysis)](https://github.com/rasuvaeff/yii3-tenancy/actions)
[![Psalm level](https://img.shields.io/badge/psalm-level%201-141F48?logo=psalm&logoColor=white)](https://github.com/rasuvaeff/yii3-tenancy/blob/master/psalm.xml)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-tenancy/php)](https://packagist.org/packages/rasuvaeff/yii3-tenancy)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-tenancy)](LICENSE.md)
[Русская версия](README.ru.md)

Multi-tenancy core for Yii3: tenant resolution from the request
(header/subdomain/path), a request-scoped `CurrentTenant` context, and scoping
primitives. Deliberately **no ORM auto-scoping magic** — explicit primitives
and recipes instead.

> **Using an AI coding assistant?** [llms.txt](llms.txt) contains a compact
> API reference you can share with the model. Contributors: see [AGENTS.md](AGENTS.md).
> Projects using the [llm/skills](https://github.com/roxblnfk/skills) Composer
> plugin also get this package's agent skill synced into `.agents/skills/`
> automatically on install.

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 8.3 – 8.5 |
| PSR-7 / PSR-15 / PSR-17 / PSR-16 | any implementation |

## Installation

```bash
composer require rasuvaeff/yii3-tenancy
```

For persistent tenant storage add the DB backend (planned:
`rasuvaeff/yii3-tenancy-db`) or bind your own `TenantProvider`.

## Usage

### Resolution middleware

```php
use Rasuvaeff\Yii3Tenancy\ConfigTenantProvider;
use Rasuvaeff\Yii3Tenancy\HeaderTenantResolver;
use Rasuvaeff\Yii3Tenancy\RequestCurrentTenant;
use Rasuvaeff\Yii3Tenancy\TenantResolutionMiddleware;

$middleware = new TenantResolutionMiddleware(
    resolver: new HeaderTenantResolver(),                 // X-Tenant-Id
    provider: new ConfigTenantProvider([
        'acme' => ['name' => 'Acme Inc', 'attributes' => ['plan' => 'pro']],
    ]),
    currentTenant: $requestCurrentTenant,                 // shared RequestCurrentTenant
    responseFactory: $psr17Factory,
);
```

Place it in the middleware pipeline **before** authentication — the tenant
usually determines the user store. On success the tenant is published twice:

- `CurrentTenant` service (constructor-inject it anywhere);
- `Tenant::class` request attribute.

Unresolved/unknown key → `404`; suspended tenant → `403`. Both are policies
(`TenantPolicy::Reject` | `TenantPolicy::PassThrough`).

### Resolvers

| Resolver | Source | Example |
|---|---|---|
| `HeaderTenantResolver` | `X-Tenant-Id` header (configurable) | `X-Tenant-Id: acme` |
| `SubdomainTenantResolver` | first label under a configured base domain | `acme.example.com` |
| `PathTenantResolver` | first segment after a configured prefix | `/t/acme/dashboard` |
| `CompositeTenantResolver` | chain, first non-null wins | header, then subdomain |

Every resolver validates the extracted key against `Tenant::isValidId()`
(`/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}\z/`) and returns `null` on mismatch —
keys taken from requests are untrusted input. Nested subdomains
(`a.b.example.com`) and lookalike hosts (`acmeexample.com`) resolve to `null`.

### Reading the current tenant

```php
use Rasuvaeff\Yii3Tenancy\CurrentTenant;

final readonly class InvoiceService
{
    public function __construct(private CurrentTenant $currentTenant) {}

    public function create(): void
    {
        $tenantId = $this->currentTenant->get()->id;   // throws if unresolved
        $plan = $this->currentTenant->get()->attributes['plan'] ?? 'free';
    }
}
```

For console/test contexts where one process handles several tenants use
`RequestCurrentTenant::override()`.

### Tenant-scoped cache

```php
use Rasuvaeff\Yii3Tenancy\TenantScopedCache;

$cache = new TenantScopedCache($psr16Cache, $currentTenant);
$cache->set('report', $data);   // stored as "t.acme.report"
```

> `clear()` delegates to the inner cache and wipes **all** tenants — PSR-16
> has no prefix-scoped clear. Do not call it in tenant-scoped code paths.

### DI configuration (Yii3)

Ships `config/di.php` + `config/params.php` via `config-plugin`. The core
binds `CurrentTenant`, the resolvers, and the middleware. **`TenantProvider`
is deliberately not bound** — exactly one source binds it: a backend package
or your application:

```php
// config/common/di/tenancy.php
use Rasuvaeff\Yii3Tenancy\ConfigTenantProvider;
use Rasuvaeff\Yii3Tenancy\TenantProvider;

return [
    TenantProvider::class => static fn (): TenantProvider => new ConfigTenantProvider([
        'acme' => ['name' => 'Acme Inc'],
    ]),
];
```

Override params as needed:

```php
// config/params.php
return [
    'rasuvaeff/yii3-tenancy' => [
        'header' => 'X-Tenant-Id',
        'base_domain' => 'example.com',   // required by SubdomainTenantResolver
        'path_prefix' => '/t',
        'resolvers' => [
            \Rasuvaeff\Yii3Tenancy\HeaderTenantResolver::class,
            \Rasuvaeff\Yii3Tenancy\SubdomainTenantResolver::class,
        ],
        'unresolved_policy' => 'reject',      // or 'passthrough'
        'suspended_policy' => 'reject',
    ],
];
```

### Recipes: wiring into the rasuvaeff/* ecosystem

```php
// feature flags: tenant-aware FlagContext
FlagContext::class => static fn (CurrentTenant $t): FlagContext =>
    new FlagContext(tenantId: $t->find()?->id),

// clickhouse-toolkit: mandatory tenant filter
$builder->withMandatoryFilter(column: 'tenant_id', value: $currentTenant->get()->id);

// settings / feature flags: tenant-isolated cache layer
CacheInterface::class => static fn (CacheInterface $inner, CurrentTenant $t): CacheInterface =>
    new TenantScopedCache($inner, $t),
```

## Components

### `Tenant`

| Property | Type | Description |
|---|---|---|
| `id` | `string` | validated: `/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}\z/` |
| `name` | `string` | optional display name |
| `status` | `TenantStatus` | `Active` (default) / `Suspended` |
| `attributes` | `array<string, mixed>` | free-form tenant metadata |

### `CurrentTenant` / `RequestCurrentTenant`

Readers depend on the `CurrentTenant` interface (`get()`, `find()`,
`isResolved()`); the middleware depends on the concrete `RequestCurrentTenant`
(`set()` once per request, `override()` for console/tests).

### `TenantResolutionMiddleware`

| Parameter | Type | Default | Description |
|---|---|---|---|
| `resolver` | `TenantResolver` | — | key extraction |
| `provider` | `TenantProvider` | — | key → `Tenant` lookup |
| `currentTenant` | `RequestCurrentTenant` | — | publication target |
| `responseFactory` | `ResponseFactoryInterface` | — | builds 404/403 |
| `unresolvedPolicy` | `TenantPolicy` | `Reject` | unresolved/unknown key |
| `suspendedPolicy` | `TenantPolicy` | `Reject` | suspended tenant |

## Security

- Tenant keys extracted from requests are **untrusted input** — every resolver
  validates against a strict whitelist pattern before lookup.
- Subdomain resolution matches only against the configured base domain, never
  the raw `Host` value alone; nested labels are rejected.
- There is no implicit "default tenant" fallback — unresolved requests are
  rejected unless you explicitly opt into `passthrough`.
- The package performs no I/O, SQL, or shell access itself.

## Examples

See [examples/](examples/) for a runnable script.

| Script | Shows | Needs server? |
|--------|-------|:-------------:|
| [`resolve-tenant.php`](examples/resolve-tenant.php) | Resolution, request attribute, 404/403 policies | no |

## Development

No PHP/Composer on the host — run in Docker via the `composer:2` image:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
```

Or with Make: `make build`, `make cs-fix`, `make psalm`, `make test`.

## License

BSD-3-Clause. See [LICENSE.md](LICENSE.md).
