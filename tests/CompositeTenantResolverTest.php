<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Tenancy\Tests;

use Nyholm\Psr7\ServerRequest;
use Rasuvaeff\PropertyTesting\Arbitrary\ArrayArbitrary;
use Rasuvaeff\PropertyTesting\ArbitraryInterface;
use Rasuvaeff\PropertyTesting\Gen;
use Rasuvaeff\PropertyTesting\Property;
use Rasuvaeff\Yii3Tenancy\CompositeTenantResolver;
use Rasuvaeff\Yii3Tenancy\HeaderTenantResolver;
use Rasuvaeff\Yii3Tenancy\PathTenantResolver;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(CompositeTenantResolver::class)]
final class CompositeTenantResolverTest
{
    public function returnsFirstNonNullResult(): void
    {
        $composite = new CompositeTenantResolver(
            new HeaderTenantResolver(),
            new PathTenantResolver(),
        );
        $request = new ServerRequest('GET', '/t/from-path', ['X-Tenant-Id' => 'from-header']);

        Assert::same($composite->resolve($request), 'from-header');
    }

    public function fallsThroughToLaterResolver(): void
    {
        $composite = new CompositeTenantResolver(
            new HeaderTenantResolver(),
            new PathTenantResolver(),
        );

        Assert::same($composite->resolve(new ServerRequest('GET', '/t/acme')), 'acme');
    }

    public function returnsNullWhenAllResolversMiss(): void
    {
        $composite = new CompositeTenantResolver(new HeaderTenantResolver(), new PathTenantResolver());

        Assert::null($composite->resolve(new ServerRequest('GET', '/')));
    }

    public function emptyCompositeResolvesToNull(): void
    {
        Assert::null((new CompositeTenantResolver())->resolve(new ServerRequest('GET', '/t/acme')));
    }

    #[Property(runs: 200)]
    public function headerWinsOverPathForAnyValidIds(string $headerId, string $pathId): void
    {
        $composite = new CompositeTenantResolver(new HeaderTenantResolver(), new PathTenantResolver());
        $request = new ServerRequest('GET', '/t/' . $pathId, ['X-Tenant-Id' => $headerId]);

        Assert::same($composite->resolve($request), $headerId);
    }

    /** @return array<string, ArbitraryInterface> */
    private function headerWinsOverPathForAnyValidIdsGenerators(): array
    {
        return [
            'headerId' => self::tenantIdGenerator(),
            'pathId' => self::tenantIdGenerator(),
        ];
    }

    private static function tenantIdGenerator(): ArbitraryInterface
    {
        return Gen::map(
            Gen::tuple(
                Gen::oneOf('a', 'z', 'A', '0', '9'),
                new ArrayArbitrary(Gen::oneOf('a', 'b', 'z', 'A', 'Z', '0', '9', '-', '_'), 0, 20),
            ),
            static fn(array $parts): string => $parts[0] . implode('', $parts[1]),
        );
    }
}
