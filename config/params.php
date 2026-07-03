<?php

declare(strict_types=1);

use Rasuvaeff\Yii3Tenancy\HeaderTenantResolver;

return [
    'rasuvaeff/yii3-tenancy' => [
        'header' => 'X-Tenant-Id',
        'base_domain' => '',
        'path_prefix' => '/t',
        'resolvers' => [
            HeaderTenantResolver::class,
        ],
        'unresolved_policy' => 'reject',
        'suspended_policy' => 'reject',
    ],
];
