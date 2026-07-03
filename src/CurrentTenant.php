<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3Tenancy;

use Rasuvaeff\Yii3Tenancy\Exception\TenantNotResolvedException;

/**
 * Read-only access to the tenant of the current request.
 *
 * @api
 */
interface CurrentTenant
{
    /**
     * @throws TenantNotResolvedException
     */
    public function get(): Tenant;

    public function find(): ?Tenant;

    public function isResolved(): bool;
}
