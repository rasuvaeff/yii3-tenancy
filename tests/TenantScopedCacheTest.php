<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Tenancy\Tests;

use Rasuvaeff\PropertyTesting\ArbitraryInterface;
use Rasuvaeff\PropertyTesting\Classify;
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
    public static function distinctTenantsNeverShareEntriesGenerators(): array
    {
        // Gen::stringFrom() over the same alphabets, instead of mapping an
        // array of single characters through implode(): one generator rather
        // than two, and shrinking walks the string's length directly.
        return [
            'suffixA' => Gen::stringFrom('az09-_', minLength: 0, maxLength: 10),
            'suffixB' => Gen::stringFrom('az09-_', minLength: 0, maxLength: 10),
            'key' => Gen::stringFrom('key.1', minLength: 1, maxLength: 10),
        ];
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function distinctTenantsNeverShareEntriesExamples(): iterable
    {
        // PSR-16 reserves `:` in keys, so a prefix scheme has to use something
        // else — and the separator is exactly where two tenant ids can be made
        // to collide. "a" + "b.x" and "ab" + ".x" must not meet.
        yield 'identical suffixes' => ['', '', 'key'];
        yield 'suffix containing the separator character' => ['-', '', 'key'];
        yield 'key that looks like a prefixed key' => ['', '', 'a.key'];
        yield 'longest allowed ids' => ['zzzzzzzzzz', 'zzzzzzzzzz', 'k'];
    }

    #[Property(runs: 200)]
    public function oneTenantAlwaysSeesItsOwnEntry(string $suffix, string $key, int $value): void
    {
        $tenantId = 't' . $suffix;
        $cache = $this->cacheFor($tenantId);

        Assert::true($cache->set($key, $value));

        Classify::when($suffix === '', 'shortest tenant id');

        // The counterpart to the isolation property: a prefix scheme that
        // isolated tenants by mangling keys beyond recognition would satisfy
        // "never share" and still be useless.
        Assert::true($cache->has($key));
        Assert::same($cache->get($key), $value);
        Assert::true($cache->delete($key));
        Assert::false($cache->has($key));
    }

    /** @return array<string, ArbitraryInterface> */
    public static function oneTenantAlwaysSeesItsOwnEntryGenerators(): array
    {
        return [
            'suffix' => Gen::stringFrom('az09-_', minLength: 0, maxLength: 10),
            'key' => Gen::stringFrom('key.1', minLength: 1, maxLength: 10),
            'value' => Gen::int(),
        ];
    }

    private function cacheFor(string $tenantId): TenantScopedCache
    {
        $current = new RequestCurrentTenant();
        $current->set(new Tenant(id: $tenantId));

        return new TenantScopedCache($this->inner, $current);
    }
}
