<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Tenancy\Tests;

use LogicException;
use Rasuvaeff\Yii3Tenancy\Exception\TenantNotResolvedException;
use Rasuvaeff\Yii3Tenancy\RequestCurrentTenant;
use Rasuvaeff\Yii3Tenancy\Tenant;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(RequestCurrentTenant::class)]
#[Covers(TenantNotResolvedException::class)]
final class RequestCurrentTenantTest
{
    public function startsUnresolved(): void
    {
        $current = new RequestCurrentTenant();

        Assert::false($current->isResolved());
        Assert::null($current->find());
    }

    public function getBeforeSetThrows(): void
    {
        Expect::exception(TenantNotResolvedException::class);

        (new RequestCurrentTenant())->get();
    }

    public function setPublishesTenant(): void
    {
        $tenant = new Tenant(id: 'acme');
        $current = new RequestCurrentTenant();

        $current->set($tenant);

        Assert::true($current->isResolved());
        Assert::same($current->get(), $tenant);
        Assert::same($current->find(), $tenant);
    }

    public function secondSetThrows(): void
    {
        $current = new RequestCurrentTenant();
        $current->set(new Tenant(id: 'acme'));

        Expect::exception(LogicException::class);

        $current->set(new Tenant(id: 'globex'));
    }

    public function overrideReplacesTenant(): void
    {
        $current = new RequestCurrentTenant();
        $current->set(new Tenant(id: 'acme'));

        $replacement = new Tenant(id: 'globex');
        $current->override($replacement);

        Assert::same($current->get(), $replacement);
    }

    public function overrideWorksOnUnresolvedHolder(): void
    {
        $tenant = new Tenant(id: 'acme');
        $current = new RequestCurrentTenant();

        $current->override($tenant);

        Assert::same($current->get(), $tenant);
    }
}
