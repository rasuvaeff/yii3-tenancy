<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Tenancy\Tests;

use Rasuvaeff\Yii3Tenancy\TenantPolicy;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(TenantPolicy::class)]
final class TenantPolicyTest
{
    public function mapsFromBackingValues(): void
    {
        Assert::same(TenantPolicy::from('reject'), TenantPolicy::Reject);
        Assert::same(TenantPolicy::from('passthrough'), TenantPolicy::PassThrough);
        Assert::null(TenantPolicy::tryFrom('ignore'));
    }
}
