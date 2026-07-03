<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Tenancy\Tests;

use Rasuvaeff\PropertyTesting\Arbitrary\ArrayArbitrary;
use Rasuvaeff\PropertyTesting\ArbitraryInterface;
use Rasuvaeff\PropertyTesting\Gen;
use Rasuvaeff\PropertyTesting\Property;
use Rasuvaeff\Yii3Tenancy\Exception\TenantNotResolvedException;
use Rasuvaeff\Yii3Tenancy\RequestCurrentTenant;
use Rasuvaeff\Yii3Tenancy\Tenant;
use Rasuvaeff\Yii3Tenancy\TenantScopedCache;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Lifecycle\BeforeTest;
use Testo\Test;
use Yiisoft\Test\Support\SimpleCache\MemorySimpleCache;

#[Test]
#[Covers(TenantScopedCache::class)]
final class TenantScopedCacheTest
{
    private MemorySimpleCache $inner;

    #[BeforeTest]
    public function setUp(): void
    {
        $this->inner = new MemorySimpleCache();
    }

    public function isolatesTenants(): void
    {
        $this->cacheFor('acme')->set('config', 'acme-value');
        $this->cacheFor('globex')->set('config', 'globex-value');

        Assert::same($this->cacheFor('acme')->get('config'), 'acme-value');
        Assert::same($this->cacheFor('globex')->get('config'), 'globex-value');
    }

    public function prefixesStoredKeyWithTenantId(): void
    {
        $this->cacheFor('acme')->set('config', 'value');

        Assert::true($this->inner->has('t.acme.config'));
    }

    public function deleteRemovesOnlyOwnTenantEntry(): void
    {
        $this->cacheFor('acme')->set('config', 'a');
        $this->cacheFor('globex')->set('config', 'g');

        $this->cacheFor('acme')->delete('config');

        Assert::false($this->cacheFor('acme')->has('config'));
        Assert::same($this->cacheFor('globex')->get('config'), 'g');
    }

    public function multipleOperationsRoundTrip(): void
    {
        $cache = $this->cacheFor('acme');

        Assert::true($cache->setMultiple(['a' => 1, 'b' => 2]));
        Assert::same($cache->getMultiple(['a', 'b', 'missing'], default: 0), ['a' => 1, 'b' => 2, 'missing' => 0]);
        Assert::true($cache->deleteMultiple(['a', 'b']));
        Assert::false($cache->has('a'));
    }

    public function getReturnsDefaultOnMiss(): void
    {
        Assert::same($this->cacheFor('acme')->get('missing', 'fallback'), 'fallback');
    }

    public function clearWipesInnerCacheEntirely(): void
    {
        $this->cacheFor('acme')->set('config', 'a');
        $this->cacheFor('globex')->set('config', 'g');

        Assert::true($this->cacheFor('acme')->clear());

        Assert::false($this->cacheFor('globex')->has('config'));
    }

    public function throwsWhenTenantIsNotResolved(): void
    {
        $cache = new TenantScopedCache($this->inner, new RequestCurrentTenant());

        Expect::exception(TenantNotResolvedException::class);

        $cache->get('config');
    }

    #[Property(runs: 200)]
    public function distinctTenantsNeverShareEntries(string $suffixA, string $suffixB, string $key): void
    {
        $idA = 'a' . $suffixA;
        $idB = 'b' . $suffixB;

        $this->cacheFor($idA)->set($key, 'value-a');

        Assert::false($this->cacheFor($idB)->has($key));
    }

    /** @return array<string, ArbitraryInterface> */
    private function distinctTenantsNeverShareEntriesGenerators(): array
    {
        $idChars = Gen::map(
            new ArrayArbitrary(Gen::oneOf('a', 'z', '0', '9', '-', '_'), 0, 10),
            static fn(array $chars): string => implode('', $chars),
        );

        return [
            'suffixA' => $idChars,
            'suffixB' => $idChars,
            'key' => Gen::map(
                new ArrayArbitrary(Gen::oneOf('k', 'e', 'y', '.', '1'), 1, 10),
                static fn(array $chars): string => implode('', $chars),
            ),
        ];
    }

    private function cacheFor(string $tenantId): TenantScopedCache
    {
        $current = new RequestCurrentTenant();
        $current->set(new Tenant(id: $tenantId));

        return new TenantScopedCache($this->inner, $current);
    }
}
