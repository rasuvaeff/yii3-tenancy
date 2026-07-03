<?php

declare(strict_types=1);

use Psr\Container\ContainerInterface;
use Rasuvaeff\Yii3Tenancy\CompositeTenantResolver;
use Rasuvaeff\Yii3Tenancy\CurrentTenant;
use Rasuvaeff\Yii3Tenancy\HeaderTenantResolver;
use Rasuvaeff\Yii3Tenancy\PathTenantResolver;
use Rasuvaeff\Yii3Tenancy\RequestCurrentTenant;
use Rasuvaeff\Yii3Tenancy\SubdomainTenantResolver;
use Rasuvaeff\Yii3Tenancy\TenantPolicy;
use Rasuvaeff\Yii3Tenancy\TenantResolutionMiddleware;
use Rasuvaeff\Yii3Tenancy\TenantResolver;

/** @var array $params */

// TenantProvider is deliberately NOT bound here: exactly one source binds it —
// a backend package (yii3-tenancy-db) or the application (ConfigTenantProvider).

return [
    CurrentTenant::class => RequestCurrentTenant::class,
    HeaderTenantResolver::class => [
        '__construct()' => [
            'headerName' => $params['rasuvaeff/yii3-tenancy']['header'],
        ],
    ],
    SubdomainTenantResolver::class => [
        '__construct()' => [
            'baseDomain' => $params['rasuvaeff/yii3-tenancy']['base_domain'],
        ],
    ],
    PathTenantResolver::class => [
        '__construct()' => [
            'prefix' => $params['rasuvaeff/yii3-tenancy']['path_prefix'],
        ],
    ],
    TenantResolver::class => [
        'definition' => static function (ContainerInterface $container) use ($params): TenantResolver {
            /** @var list<class-string<TenantResolver>> $classes */
            $classes = $params['rasuvaeff/yii3-tenancy']['resolvers'];

            return new CompositeTenantResolver(
                ...array_map(
                    static fn (string $class): TenantResolver => $container->get($class),
                    $classes,
                ),
            );
        },
    ],
    TenantResolutionMiddleware::class => [
        '__construct()' => [
            'unresolvedPolicy' => TenantPolicy::from($params['rasuvaeff/yii3-tenancy']['unresolved_policy']),
            'suspendedPolicy' => TenantPolicy::from($params['rasuvaeff/yii3-tenancy']['suspended_policy']),
        ],
    ],
];
