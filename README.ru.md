# Расуваефф/yii3-аренда
[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-tenancy?label=stable&sort_semver=1)](https://packagist.org/packages/rasuvaeff/yii3-tenancy)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-tenancy)](https://packagist.org/packages/rasuvaeff/yii3-tenancy)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-tenancy/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-tenancy/actions)
[![Static analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-tenancy/static-analysis.yml?branch=master&label=static%20analysis)](https://github.com/rasuvaeff/yii3-tenancy/actions)
[![Psalm level](https://img.shields.io/badge/psalm-level%201-141F48?logo=psalm&logoColor=white)](https://github.com/rasuvaeff/yii3-tenancy/blob/master/psalm.xml)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-tenancy/php)](https://packagist.org/packages/rasuvaeff/yii3-tenancy)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-tenancy)](LICENSE.md)
Ядро мультиарендности для Yii3: разрешение арендаторов на основе запроса
 (заголовок/поддомен/путь), контекст CurrentTenant в области запроса и определение области действия примитивов
. Намеренно **никакой магии автоматического определения области ORM** — вместо этого явные примитивы
 и рецепты.

 > **Используете помощника по кодированию с использованием искусственного интеллекта?** [llms.txt](llms.txt) содержит компактную ссылку
 > API, которой вы можете поделиться с моделью. Авторы: см. [AGENTS.md](AGENTS.md). @@ЛИНИЯ@@
## Требования
| Требование | Версия |
 |-------------|---------|
 | PHP | 8,3 – 8,5 |
 | ПСР-7/ПСР-15/ПСР-17/ПСР-16 | любая реализация | @@ЛИНИЯ@@
## Установка
```bash
composer require rasuvaeff/yii3-tenancy
```
Для постоянного хранилища арендаторов добавьте серверную часть БД (планируется:
 `rasuvaeff/yii3-tenancy-db`) или привяжите свой собственный `TenantProvider`. @@ЛИНИЯ@@
## Использование
### Промежуточное программное обеспечение разрешения
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
Поместите его в конвейер промежуточного программного обеспечения **перед** аутентификацией — клиент
 обычно определяет хранилище пользователей. В случае успеха арендатор публикуется дважды:

 — сервис CurrentTenant (внедрите его в любое место конструктором);
 — атрибут запроса `Tenant::class`.

 Неразрешенный/неизвестный ключ → `404`; приостановлен арендатор → `403`. Обе политики являются политиками
 (`TenantPolicy::Reject` | `TenantPolicy::PassThrough`). @@ЛИНИЯ@@
### Резольверы
| Резольвер | Источник | Пример |
 |---|---|---|
 | `HeaderTenantResolver` | Заголовок `X-Tenant-Id` (настраиваемый) | `X-Tenant-Id: acme` |
 | `SubdomainTenantResolver` | первая метка в настроенном базовом домене | `acme.example.com` |
 | `PathTenantResolver` | первый сегмент после настроенного префикса | `/t/acme/dashboard` |
 | `CompositeTenantResolver` | цепочка, первые ненулевые выигрыши | заголовок, затем субдомен |

 Каждый преобразователь проверяет извлеченный ключ на соответствие `Tenant::isValidId()`
 (`/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/`) и возвращает `null` в случае несоответствия — ключи
, взятые из запросов, являются ненадежными входными данными. Вложенные поддомены
 (`a.b.example.com`) и похожие хосты (`acmeexample.com`) разрешаются в `null`. @@ЛИНИЯ@@
### Чтение текущего арендатора
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
Для контекстов консоли/теста, где один процесс обрабатывает несколько клиентов, используйте
 `RequestCurrentTenant::override()`. @@ЛИНИЯ@@
### Кэш на уровне клиента
```php
use Rasuvaeff\Yii3Tenancy\TenantScopedCache;

$cache = new TenantScopedCache($psr16Cache, $currentTenant);
$cache->set('report', $data);   // stored as "t.acme.report"
```
> `clear()` делегирует внутренний кеш и очищает **все** арендаторов — PSR-16
 > не имеет очистки с префиксной областью. Не вызывайте его в путях кода на уровне клиента. @@ЛИНИЯ@@
### Конфигурация DI (Yii3)
Поставляется `config/di.php` + `config/params.php` через `config-plugin`. Ядро
 связывает CurrentTenant, преобразователи и промежуточное программное обеспечение. **`TenantProvider`
 намеренно не привязан** — его привязывает ровно один источник: серверный пакет
 или ваше приложение:

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
### Рецепты: подключение к экосистеме rasuvaeff/*
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
### `Арендатор`
| Недвижимость | Тип | Описание |
 |---|---|---|
 | `идентификатор` | `строка` | проверено: `/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/` |
 | `имя` | `строка` | необязательное отображаемое имя |
 | `статус` | `ТенантСтатус` | `Активный` (по умолчанию) / `Приостановлен` |
 | `атрибуты` | `массив<строка, смешанный>` | метаданные арендатора в свободной форме | @@ЛИНИЯ@@
### `CurrentTenant` / `RequestCurrentTenant`
Читатели зависят от интерфейса CurrentTenant (get(), find(),
isResolved()); промежуточное программное обеспечение зависит от конкретного `RequestCurrentTenant`
 (`set()` один раз для каждого запроса,`override()` для консоли/тестов). @@ЛИНИЯ@@
### `TenantResolutionMiddleware`
| Параметр | Тип | По умолчанию | Описание |
 |---|---|---|---|
 | `резольвер` | `TenantResolver` | — | извлечение ключей |
 | `провайдер` | `ТенантПровайдер` | — | ключ → Поиск «Арендатора» |
 | `текущийТенант` | `RequestCurrentTenant` | — | цель публикации |
 | `responseFactory` | `ResponseFactoryInterface` | — | строит 404/403 |
 | `unresolvedPolicy` | `ТенантПолиси` | `Отклонить` | неразрешенный/неизвестный ключ |
 | `приостановленная политика` | `ТенантПолиси` | `Отклонить` | приостановлен арендатор | @@ЛИНИЯ@@
## Безопасность
- Ключи арендатора, извлеченные из запросов, являются **ненадежными входными данными** — каждый преобразователь
 проверяет соответствие строгому шаблону белого списка перед поиском.
 — разрешение поддомена соответствует только настроенному базовому домену, а не
 только необработанному значению `Host`; вложенные метки отклоняются.
 — неявного резервного варианта «клиента по умолчанию» не существует — неразрешенные запросы
 отклоняются, если вы явно не выберете `passthrough`.
 - Пакет сам не выполняет ввод-вывод, SQL или доступ к оболочке. @@ЛИНИЯ@@
## Примеры
См. [examples/](examples/) для работоспособного сценария.

 | Скрипт | Шоу | Нужен сервер? |
 |--------|-------|:-------------:|
 | [`resolve-tenant.php`](examples/resolve-tenant.php) | Разрешение, атрибут запроса, политики 404/403 | нет | @@ЛИНИЯ@@
## Разработка
На хосте нет PHP/Composer — запустите в Docker через образ `composer:2`:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
```
Или с помощью Make: make build, make cs-fix, make psalm, make test. @@ЛИНИЯ@@
## Лицензия
BSD-3-пункт. См. [LICENSE.md](LICENSE.md).
