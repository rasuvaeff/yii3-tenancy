<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Tenancy\Tests;

use Rasuvaeff\Yii3Tenancy\TenantStatus;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(TenantStatus::class)]
final class TenantStatusTest
{
    public function mapsFromBackingValues(): void
    {
        Assert::same(TenantStatus::from('active'), TenantStatus::Active);
        Assert::same(TenantStatus::from('suspended'), TenantStatus::Suspended);
        Assert::null(TenantStatus::tryFrom('frozen'));
    }
}
