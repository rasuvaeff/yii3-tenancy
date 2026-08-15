<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Tenancy\Tests;

use Nyholm\Psr7\ServerRequest;
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
    public static function headerWinsOverPathForAnyValidIdsGenerators(): array
    {
        return [
            'headerId' => self::tenantIdGenerator(),
            'pathId' => self::tenantIdGenerator(),
        ];
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function headerWinsOverPathForAnyValidIdsExamples(): iterable
    {
        // Precedence has to hold when the two agree, when one is a prefix of
        // the other, and at the shortest legal id — the cases where a resolver
        // that concatenated or compared instead of choosing would still look
        // right.
        yield 'both the same' => ['acme', 'acme'];
        yield 'path id is a prefix of the header id' => ['acmecorp', 'acme'];
        yield 'header id is a prefix of the path id' => ['acme', 'acmecorp'];
        yield 'shortest legal ids' => ['a', 'z'];
    }

    private static function tenantIdGenerator(): ArbitraryInterface
    {
        // The identifier format spelled once, as the pattern it is, instead of
        // a first character tupled with an array of the rest and imploded.
        // Gen::regex() builds only strings the resolvers accept, so no run is
        // spent on a value that would be discarded.
        return Gen::regex('[azA09][abzAZ09_-]{0,20}');
    }
}
