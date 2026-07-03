<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Tenancy\Benchmarks;

use Nyholm\Psr7\ServerRequest;
use Rasuvaeff\Yii3Tenancy\HeaderTenantResolver;
use Rasuvaeff\Yii3Tenancy\PathTenantResolver;
use Rasuvaeff\Yii3Tenancy\SubdomainTenantResolver;
use Rasuvaeff\Yii3Tenancy\Tenant;
use Testo\Bench;

final class TenantResolutionBench
{
    #[Bench(
        callables: [
            'subdomain' => [self::class, 'viaSubdomain'],
            'path' => [self::class, 'viaPath'],
        ],
        calls: 100_000,
        iterations: 5,
    )]
    public static function viaHeader(): ?string
    {
        return (new HeaderTenantResolver())
            ->resolve(new ServerRequest('GET', '/', ['X-Tenant-Id' => 'acme']));
    }

    public static function viaSubdomain(): ?string
    {
        return (new SubdomainTenantResolver(baseDomain: 'example.com'))
            ->resolve(new ServerRequest('GET', 'https://acme.example.com/'));
    }

    public static function viaPath(): ?string
    {
        return (new PathTenantResolver())
            ->resolve(new ServerRequest('GET', '/t/acme/dashboard'));
    }

    #[Bench(
        callables: [
            'sparse' => [self::class, 'constructSparseTenant'],
        ],
        calls: 1_000_000,
        iterations: 5,
    )]
    public static function constructFullTenant(): Tenant
    {
        return new Tenant(id: 'acme', name: 'Acme Inc', attributes: ['plan' => 'pro']);
    }

    public static function constructSparseTenant(): Tenant
    {
        return new Tenant(id: 'acme');
    }
}
