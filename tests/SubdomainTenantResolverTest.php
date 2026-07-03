<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Tenancy\Tests;

use InvalidArgumentException;
use Nyholm\Psr7\ServerRequest;
use Rasuvaeff\Yii3Tenancy\SubdomainTenantResolver;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(SubdomainTenantResolver::class)]
final class SubdomainTenantResolverTest
{
    #[DataProvider('resolvableProvider')]
    public function resolvesFirstLabel(string $host, string $expected): void
    {
        $resolver = new SubdomainTenantResolver(baseDomain: 'example.com');

        Assert::same($resolver->resolve(new ServerRequest('GET', 'https://' . $host . '/')), $expected);
    }

    public static function resolvableProvider(): iterable
    {
        yield 'plain' => ['acme.example.com', 'acme'];
        yield 'uppercase host is normalized' => ['ACME.Example.COM', 'acme'];
        yield 'digits' => ['42.example.com', '42'];
    }

    #[DataProvider('unresolvableProvider')]
    public function returnsNullWhenNotResolvable(string $host): void
    {
        $resolver = new SubdomainTenantResolver(baseDomain: 'example.com');

        Assert::null($resolver->resolve(new ServerRequest('GET', 'https://' . $host . '/')));
    }

    public static function unresolvableProvider(): iterable
    {
        yield 'bare base domain' => ['example.com'];
        yield 'nested subdomain' => ['a.b.example.com'];
        yield 'different domain' => ['acme.evil.com'];
        yield 'suffix lookalike' => ['acmeexample.com'];
    }

    public function normalizesBaseDomainInConstructor(): void
    {
        $resolver = new SubdomainTenantResolver(baseDomain: ' .Example.COM ');

        Assert::same($resolver->resolve(new ServerRequest('GET', 'https://acme.example.com/')), 'acme');
    }

    public function throwsOnEmptyBaseDomain(): void
    {
        Expect::exception(InvalidArgumentException::class);

        new SubdomainTenantResolver(baseDomain: '');
    }
}
