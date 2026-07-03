<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Tenancy\Tests;

use InvalidArgumentException;
use Nyholm\Psr7\ServerRequest;
use Rasuvaeff\Yii3Tenancy\PathTenantResolver;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(PathTenantResolver::class)]
final class PathTenantResolverTest
{
    #[DataProvider('resolvableProvider')]
    public function resolvesFirstSegmentAfterPrefix(string $path, string $expected): void
    {
        Assert::same((new PathTenantResolver())->resolve(new ServerRequest('GET', $path)), $expected);
    }

    public static function resolvableProvider(): iterable
    {
        yield 'segment with rest' => ['/t/acme/dashboard', 'acme'];
        yield 'segment only' => ['/t/acme', 'acme'];
        yield 'trailing slash' => ['/t/acme/', 'acme'];
    }

    #[DataProvider('unresolvableProvider')]
    public function returnsNullWhenNotResolvable(string $path): void
    {
        Assert::null((new PathTenantResolver())->resolve(new ServerRequest('GET', $path)));
    }

    public static function unresolvableProvider(): iterable
    {
        yield 'root' => ['/'];
        yield 'prefix only' => ['/t'];
        yield 'prefix with empty segment' => ['/t/'];
        yield 'different prefix' => ['/admin/acme'];
        yield 'prefix lookalike' => ['/tenant/acme'];
        yield 'invalid segment' => ['/t/acme.inc/x'];
    }

    public function resolvesWithCustomPrefix(): void
    {
        $resolver = new PathTenantResolver(prefix: '/tenants/');

        Assert::same($resolver->resolve(new ServerRequest('GET', '/tenants/acme/users')), 'acme');
    }

    #[DataProvider('invalidPrefixProvider')]
    public function throwsOnInvalidPrefix(string $prefix): void
    {
        Expect::exception(InvalidArgumentException::class);

        new PathTenantResolver(prefix: $prefix);
    }

    public static function invalidPrefixProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'root' => ['/'];
        yield 'missing leading slash' => ['t'];
    }
}
