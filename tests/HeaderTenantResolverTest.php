<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Tenancy\Tests;

use InvalidArgumentException;
use Nyholm\Psr7\ServerRequest;
use Rasuvaeff\Yii3Tenancy\HeaderTenantResolver;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(HeaderTenantResolver::class)]
final class HeaderTenantResolverTest
{
    public function resolvesFromDefaultHeader(): void
    {
        $request = new ServerRequest('GET', '/', ['X-Tenant-Id' => 'acme']);

        Assert::same((new HeaderTenantResolver())->resolve($request), 'acme');
    }

    public function resolvesFromCustomHeader(): void
    {
        $request = new ServerRequest('GET', '/', ['X-Customer' => 'acme']);

        Assert::same((new HeaderTenantResolver(headerName: 'X-Customer'))->resolve($request), 'acme');
    }

    public function trimsSurroundingWhitespace(): void
    {
        $request = new ServerRequest('GET', '/', ['X-Tenant-Id' => '  acme  ']);

        Assert::same((new HeaderTenantResolver())->resolve($request), 'acme');
    }

    #[DataProvider('unresolvableProvider')]
    public function returnsNullWhenNotResolvable(array $headers): void
    {
        $request = new ServerRequest('GET', '/', $headers);

        Assert::null((new HeaderTenantResolver())->resolve($request));
    }

    public static function unresolvableProvider(): iterable
    {
        yield 'missing header' => [[]];
        yield 'empty value' => [['X-Tenant-Id' => '']];
        yield 'invalid characters' => [['X-Tenant-Id' => 'acme.inc']];
        yield 'multiple values collapse to invalid' => [['X-Tenant-Id' => ['acme', 'globex']]];
    }

    public function throwsOnEmptyHeaderName(): void
    {
        Expect::exception(InvalidArgumentException::class);

        new HeaderTenantResolver(headerName: '');
    }
}
