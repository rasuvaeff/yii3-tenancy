<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Tenancy;

/**
 * Looks a tenant up by its resolved key. The swappable backend axis: bind
 * this interface in a backend package (yii3-tenancy-db) or in the
 * application — the core deliberately does not bind it.
 *
 * @api
 */
interface TenantProvider
{
    public function find(string $key): ?Tenant;
}
