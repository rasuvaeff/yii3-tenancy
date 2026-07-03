<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Tenancy\Tests;

use Rasuvaeff\Yii3Tenancy\CompositeTenantResolver;
use Rasuvaeff\Yii3Tenancy\CurrentTenant;
use Rasuvaeff\Yii3Tenancy\HeaderTenantResolver;
use Rasuvaeff\Yii3Tenancy\RequestCurrentTenant;
use Rasuvaeff\Yii3Tenancy\TenantPolicy;
use Rasuvaeff\Yii3Tenancy\TenantProvider;
use Rasuvaeff\Yii3Tenancy\TenantResolutionMiddleware;
use Rasuvaeff\Yii3Tenancy\TenantResolver;
use Testo\Assert;
use Testo\Codecov\CoversNothing;
use Testo\Test;
use Yiisoft\Test\Support\Container\SimpleContainer;

#[Test]
#[CoversNothing]
final class ConfigWiringTest
{
    public function currentTenantIsAliasedToRequestHolder(): void
    {
        Assert::same($this->di()[CurrentTenant::class], RequestCurrentTenant::class);
    }

    public function tenantProviderIsNotBoundByCore(): void
    {
        Assert::false(array_key_exists(TenantProvider::class, $this->di()));
    }

    public function resolverDefinitionBuildsCompositeFromParams(): void
    {
        /** @var array{definition: \Closure} $definition */
        $definition = $this->di()[TenantResolver::class];
        $container = new SimpleContainer([
            HeaderTenantResolver::class => new HeaderTenantResolver(),
        ]);

        $resolver = $definition['definition']($container);

        Assert::instanceOf($resolver, CompositeTenantResolver::class);
    }

    public function middlewarePoliciesAreConvertedFromParams(): void
    {
        /** @var array{'__construct()': array{unresolvedPolicy: TenantPolicy, suspendedPolicy: TenantPolicy}} $definition */
        $definition = $this->di()[TenantResolutionMiddleware::class];

        Assert::same($definition['__construct()']['unresolvedPolicy'], TenantPolicy::Reject);
        Assert::same($definition['__construct()']['suspendedPolicy'], TenantPolicy::Reject);
    }

    /**
     * @return array<string, mixed>
     */
    private function di(): array
    {
        /** @var array<string, mixed> $params */
        $params = require dirname(__DIR__) . '/config/params.php';

        return (static function (array $params): array {
            return require dirname(__DIR__, 1) . '/config/di.php';
        })($params);
    }
}
