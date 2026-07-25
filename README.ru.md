# rasuvaeff/yii3-tenancy

[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-tenancy?label=stable&sort_semver=1)](https://packagist.org/packages/rasuvaeff/yii3-tenancy)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-tenancy)](https://packagist.org/packages/rasuvaeff/yii3-tenancy)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-tenancy/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-tenancy/actions)
[![Static analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-tenancy/static-analysis.yml?branch=master&label=static%20analysis)](https://github.com/rasuvaeff/yii3-tenancy/actions)
[![Psalm level](https://img.shields.io/badge/psalm-level%201-141F48?logo=psalm&logoColor=white)](https://github.com/rasuvaeff/yii3-tenancy/blob/master/psalm.xml)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-tenancy/php)](https://packagist.org/packages/rasuvaeff/yii3-tenancy)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-tenancy)](LICENSE.md)
[English version](README.md)

Ядро multi-tenancy для Yii3: резолвинг тенанта из запроса
(header/subdomain/path), request-scoped контекст `CurrentTenant` и примитивы
скопирования. Намеренно **без ORM-магии авто-скопирования** — только явные
примитивы и рецепты.

> **Используете AI-ассистента?** В [llms.txt](llms.txt) — компактный
> API-справочник, которым можно поделиться с моделью. Контрибьюторам: см.
> [AGENTS.md](AGENTS.md).
> Проекты с Composer-плагином [llm/skills](https://github.com/roxblnfk/skills)
> дополнительно получают agent-скилл этого пакета в `.agents/skills/`
> автоматически при установке.

## Требования

| Требование | Версия |
|-------------|---------|
| PHP | 8.3 – 8.5 |
| PSR-7 / PSR-15 / PSR-17 / PSR-16 | любая реализация |

## Установка

```bash
composer require rasuvaeff/yii3-tenancy
```

Для персистентного хранения тенантов добавьте DB-бэкенд (планируется
`rasuvaeff/yii3-tenancy-db`) или биндите свой `TenantProvider`.

## Использование

### Middleware резолвинга

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

Ставьте его в pipeline middleware-ов **до** аутентификации — тенант обычно
определяет user store. При успехе тенант публикуется в двух местах:

- сервис `CurrentTenant` (инжектить куда угодно через конструктор);
- request-атрибут `Tenant::class`.

Неразрезолвленный/неизвестный ключ → `404`; заблокированный тенант → `403`. Оба
— политики (`TenantPolicy::Reject` | `TenantPolicy::PassThrough`).

### Резолверы

| Резолвер | Источник | Пример |
|---|---|---|
| `HeaderTenantResolver` | header `X-Tenant-Id` (настраиваемый) | `X-Tenant-Id: acme` |
| `SubdomainTenantResolver` | первая label под настроенным базовым доменом | `acme.example.com` |
| `PathTenantResolver` | первый сегмент после настроенного префикса | `/t/acme/dashboard` |
| `CompositeTenantResolver` | цепочка, выигрывает первый non-null | header, затем subdomain |

Каждый резолвер валидирует извлечённый ключ через `Tenant::isValidId()`
(`/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}\z/`) и возвращает `null` при несовпадении —
ключи из запроса это untrusted input. Вложенные subdomain-ы
(`a.b.example.com`) и look-alike хосты (`acmeexample.com`) резолвятся в `null`.

### Чтение текущего тенанта

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

Для консольных/тестовых контекстов, где один процесс обслуживает несколько
тенантов, используйте `RequestCurrentTenant::override()`.

### Tenant-scoped кэш

```php
use Rasuvaeff\Yii3Tenancy\TenantScopedCache;

$cache = new TenantScopedCache($psr16Cache, $currentTenant);
$cache->set('report', $data);   // stored as "t.acme.report"
```

> `clear()` делегирует во внутренний кэш и вычищает **всех** тенантов — у PSR-16
> нет prefix-scoped clear. Не вызывайте его в tenant-scoped коде.

### DI-конфигурация (Yii3)

Пакет несёт `config/di.php` + `config/params.php` через `config-plugin`. Ядро
биндит `CurrentTenant`, резолверы и middleware. **`TenantProvider` намеренно не
биндится** — его биндит ровно один источник: backend-пакет или приложение:

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

При необходимости переопределите параметры:

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

### Рецепты: интеграция в экосистему rasuvaeff/*

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

## Компоненты

### `Tenant`

| Поле | Тип | Описание |
|---|---|---|
| `id` | `string` | валидируется: `/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}\z/` |
| `name` | `string` | опциональное отображаемое имя |
| `status` | `TenantStatus` | `Active` (по умолчанию) / `Suspended` |
| `attributes` | `array<string, mixed>` | произвольные метаданные тенанта |

### `CurrentTenant` / `RequestCurrentTenant`

Читатели зависят от интерфейса `CurrentTenant` (`get()`, `find()`,
`isResolved()`); middleware зависит от конкретного `RequestCurrentTenant`
(`set()` один раз за запрос, `override()` для консоли/тестов).

### `TenantResolutionMiddleware`

| Параметр | Тип | По умолчанию | Описание |
|---|---|---|---|
| `resolver` | `TenantResolver` | — | извлечение ключа |
| `provider` | `TenantProvider` | — | lookup ключ → `Tenant` |
| `currentTenant` | `RequestCurrentTenant` | — | цель публикации |
| `responseFactory` | `ResponseFactoryInterface` | — | строит 404/403 |
| `unresolvedPolicy` | `TenantPolicy` | `Reject` | неразрезолвленный/неизвестный ключ |
| `suspendedPolicy` | `TenantPolicy` | `Reject` | заблокированный тенант |

## Безопасность

- Ключи тенантов, извлечённые из запроса — **untrusted input**: каждый резолвер
  валидирует их по строгому whitelist-паттерну перед lookup-ом.
- Резолвинг subdomain-а матчится только по настроенному базовому домену, никогда
  — по сырому `Host` как есть; вложенные label-ы отклоняются.
- Неявного фолбэка на «тенант по умолчанию» нет — неразрезолвленные запросы
  отклоняются, если вы явно не включили `passthrough`.
- Пакет сам не делает I/O, SQL и не обращается к shell.

## Примеры

См. [examples/](examples/) — запускаемый скрипт.

| Скрипт | Показывает | Нужен сервер? |
|--------|-------|:-------------:|
| [`resolve-tenant.php`](examples/resolve-tenant.php) | Резолвинг, request-атрибут, политики 404/403 | нет |

## Разработка

На хосте нет PHP/Composer — запускайте через Docker-образ `composer:2`:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
```

Или через Make: `make build`, `make cs-fix`, `make psalm`, `make test`.

## Лицензия

BSD-3-Clause. См. [LICENSE.md](LICENSE.md).
