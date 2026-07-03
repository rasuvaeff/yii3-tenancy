<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Tenancy\Tests;

use InvalidArgumentException;
use Rasuvaeff\Yii3Tenancy\ConfigTenantProvider;
use Rasuvaeff\Yii3Tenancy\TenantStatus;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(ConfigTenantProvider::class)]
final class ConfigTenantProviderTest
{
    public function findsConfiguredTenant(): void
    {
        $provider = new ConfigTenantProvider([
            'acme' => [
                'name' => 'Acme Inc',
                'status' => 'suspended',
                'attributes' => ['plan' => 'pro'],
            ],
        ]);

        $tenant = $provider->find('acme');

        Assert::same($tenant?->id, 'acme');
        Assert::same($tenant?->name, 'Acme Inc');
        Assert::same($tenant?->status, TenantStatus::Suspended);
        Assert::same($tenant?->attributes, ['plan' => 'pro']);
    }

    public function appliesDefaultsToMinimalDefinition(): void
    {
        $tenant = (new ConfigTenantProvider(['acme' => []]))->find('acme');

        Assert::same($tenant?->name, '');
        Assert::same($tenant?->status, TenantStatus::Active);
        Assert::same($tenant?->attributes, []);
    }

    public function returnsNullForUnknownKey(): void
    {
        Assert::null((new ConfigTenantProvider(['acme' => []]))->find('globex'));
    }

    public function throwsOnUnknownStatus(): void
    {
        Expect::exception(InvalidArgumentException::class);

        new ConfigTenantProvider(['acme' => ['status' => 'frozen']]);
    }

    public function throwsOnInvalidTenantId(): void
    {
        Expect::exception(InvalidArgumentException::class);

        new ConfigTenantProvider(['bad id' => []]);
    }
}
